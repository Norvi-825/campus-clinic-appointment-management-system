<?php
/**
 * Appointment Booking, Cancellation, Rescheduling & Status Management Service
 *
 * Uses PDO database transactions and SELECT ... FOR UPDATE row-level locking
 * to prevent concurrent double-booking race conditions while preserving cancelled
 * appointment history.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

class AppointmentService
{
    private PDO $pdo;
    private NotificationService $notifier;

    public function __construct(PDO $pdo, ?NotificationService $notifier = null)
    {
        $this->pdo = $pdo;
        $this->notifier = $notifier ?? new NotificationService($pdo);
    }

    /**
     * Books an available slot for a student inside an atomic row-locked transaction.
     *
     * @return array{success: bool, message: string, appointment_id?: int}
     */
    public function bookAppointment(int $studentId, int $doctorId, int $slotId, ?string $reason = null): array
    {
        $cleanReason = trim((string) ($reason ?? ''));
        if (mb_strlen($cleanReason) > 255) {
            return ['success' => false, 'message' => 'Appointment reason must be 255 characters or fewer.'];
        }

        if ($studentId <= 0 || $doctorId <= 0 || $slotId <= 0) {
            return ['success' => false, 'message' => 'Please select a valid doctor and appointment slot.'];
        }

        try {
            $this->pdo->beginTransaction();

            // Verify student account is active
            $stuStmt = $this->pdo->prepare(
                'SELECT user_id, role, account_status FROM users WHERE user_id = :sid LIMIT 1'
            );
            $stuStmt->execute(['sid' => $studentId]);
            $student = $stuStmt->fetch();
            if (!$student || $student['role'] !== 'student' || $student['account_status'] !== 'active') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Only active student accounts can book clinic appointments.'];
            }

            // Lock the target appointment slot row to block concurrent bookings
            $slotStmt = $this->pdo->prepare(
                'SELECT s.slot_id, s.doctor_id, s.start_time, s.end_time, s.status AS slot_status,
                        d.availability_status, u.account_status AS doctor_account_status
                 FROM appointment_slots s
                 INNER JOIN doctors d ON d.doctor_id = s.doctor_id
                 INNER JOIN users u ON u.user_id = d.user_id
                 WHERE s.slot_id = :slot_id
                 FOR UPDATE'
            );
            $slotStmt->execute(['slot_id' => $slotId]);
            $slot = $slotStmt->fetch();

            if (!$slot) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'The selected appointment slot does not exist.'];
            }

            // Validate doctor owns the selected slot
            if ((int) $slot['doctor_id'] !== $doctorId) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'The selected slot does not belong to the chosen doctor.'];
            }

            if ($slot['doctor_account_status'] !== 'active' || $slot['availability_status'] !== 'available') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'This doctor is currently unavailable for new bookings.'];
            }

            if ($slot['slot_status'] !== 'available') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'This appointment slot is no longer available.'];
            }

            if (strtotime((string) $slot['start_time']) <= time()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Cannot book an appointment slot in the past.'];
            }

            // Double-check no active appointment exists for this slot
            $activeCheck = $this->pdo->prepare(
                'SELECT appointment_id FROM appointments
                 WHERE slot_id = :slot_id AND status IN ("pending", "confirmed")
                 LIMIT 1
                 FOR UPDATE'
            );
            $activeCheck->execute(['slot_id' => $slotId]);
            if ($activeCheck->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Another student just booked this slot. Please choose a different time.'];
            }

            // Prevent student from booking overlapping active appointments at the same time
            $studentOverlap = $this->pdo->prepare(
                'SELECT a.appointment_id
                 FROM appointments a
                 INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
                 WHERE a.student_id = :sid
                   AND a.status IN ("pending", "confirmed")
                   AND s.start_time < :new_end
                   AND s.end_time > :new_start
                 LIMIT 1'
            );
            $studentOverlap->execute([
                'sid' => $studentId,
                'new_end' => $slot['end_time'],
                'new_start' => $slot['start_time'],
            ]);
            if ($studentOverlap->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'You already have another active appointment overlapping this time window.'];
            }

            // Create appointment record
            $insertStmt = $this->pdo->prepare(
                'INSERT INTO appointments (student_id, slot_id, reason, status)
                 VALUES (:student_id, :slot_id, :reason, "pending")'
            );
            $insertStmt->execute([
                'student_id' => $studentId,
                'slot_id' => $slotId,
                'reason' => $cleanReason !== '' ? $cleanReason : null,
            ]);
            $appointmentId = (int) $this->pdo->lastInsertId();

            // Mark slot as booked
            $updateSlot = $this->pdo->prepare(
                'UPDATE appointment_slots SET status = "booked" WHERE slot_id = :slot_id'
            );
            $updateSlot->execute(['slot_id' => $slotId]);

            $this->pdo->commit();

            // Dispatch notification outside the row-lock transaction
            $this->notifier->dispatchAppointmentEvent($appointmentId, 'booked');

            return [
                'success' => true,
                'message' => 'Appointment #' . $appointmentId . ' booked successfully and is pending clinic confirmation.',
                'appointment_id' => $appointmentId,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AppointmentService::bookAppointment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while booking your appointment. Please try again.'];
        }
    }

    /**
     * Cancels an existing appointment, preserves history, and frees future slots.
     *
     * @return array{success: bool, message: string}
     */
    public function cancelAppointment(int $appointmentId, int $actorUserId, string $actorRole): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT a.appointment_id, a.student_id, a.slot_id, a.status,
                        s.start_time, s.status AS slot_status
                 FROM appointments a
                 INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
                 WHERE a.appointment_id = :aid
                 FOR UPDATE'
            );
            $stmt->execute(['aid' => $appointmentId]);
            $appt = $stmt->fetch();

            if (!$appt) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Appointment record not found.'];
            }

            if ($actorRole === 'student' && (int) $appt['student_id'] !== $actorUserId) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'You are not authorized to cancel this appointment.'];
            }

            if (!in_array($appt['status'], ['pending', 'confirmed'], true)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Only pending or confirmed appointments can be cancelled.'];
            }

            if ($actorRole === 'student' && !can_student_modify_appointment($appt)) {
                $cutoff = (int) (ccams_config()['app']['cancellation_cutoff_hours'] ?? 2);
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Appointments cannot be cancelled less than ' . $cutoff . ' hours before the scheduled start time.',
                ];
            }

            // Preserve record by setting status = cancelled
            $updAppt = $this->pdo->prepare(
                'UPDATE appointments SET status = "cancelled" WHERE appointment_id = :aid'
            );
            $updAppt->execute(['aid' => $appointmentId]);

            // Release slot if it is still in the future and was booked
            if ($appt['slot_status'] === 'booked' && strtotime((string) $appt['start_time']) > time()) {
                $updSlot = $this->pdo->prepare(
                    'UPDATE appointment_slots SET status = "available" WHERE slot_id = :sid'
                );
                $updSlot->execute(['sid' => (int) $appt['slot_id']]);
            }

            $this->pdo->commit();

            $this->notifier->dispatchAppointmentEvent($appointmentId, 'cancelled');

            return [
                'success' => true,
                'message' => 'Appointment #' . $appointmentId . ' has been cancelled. Its historical record has been preserved.',
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AppointmentService::cancelAppointment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cancel appointment. Please try again.'];
        }
    }

    /**
     * Reschedules a student's appointment to a new available slot while preserving
     * the original appointment record as cancelled history.
     *
     * @return array{success: bool, message: string, new_appointment_id?: int}
     */
    public function rescheduleAppointment(int $appointmentId, int $studentId, int $newSlotId, ?string $reason = null): array
    {
        if ($newSlotId <= 0) {
            return ['success' => false, 'message' => 'Please select a valid new appointment slot.'];
        }

        try {
            $this->pdo->beginTransaction();

            // Fetch old appointment first without locking slot yet to determine lock order
            $oldStmt = $this->pdo->prepare(
                'SELECT a.appointment_id, a.student_id, a.slot_id, a.reason, a.status, s.start_time
                 FROM appointments a
                 INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
                 WHERE a.appointment_id = :aid
                 FOR UPDATE'
            );
            $oldStmt->execute(['aid' => $appointmentId]);
            $oldAppt = $oldStmt->fetch();

            if (!$oldAppt || (int) $oldAppt['student_id'] !== $studentId) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Appointment not found or access denied.'];
            }

            if ((int) $oldAppt['slot_id'] === $newSlotId) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Please choose a different slot from your current appointment.'];
            }

            if (!can_student_modify_appointment($oldAppt)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'This appointment is no longer eligible for rescheduling under clinic rules.'];
            }

            // Lock slots in deterministic ascending order to prevent deadlocks
            $oldSlotId = (int) $oldAppt['slot_id'];
            $lockIds = [$oldSlotId, $newSlotId];
            sort($lockIds);

            $lockStmt = $this->pdo->prepare(
                'SELECT s.slot_id, s.doctor_id, s.start_time, s.end_time, s.status AS slot_status,
                        d.availability_status, u.account_status AS doctor_account_status
                 FROM appointment_slots s
                 INNER JOIN doctors d ON d.doctor_id = s.doctor_id
                 INNER JOIN users u ON u.user_id = d.user_id
                 WHERE s.slot_id IN (:id1, :id2)
                 FOR UPDATE'
            );
            $lockStmt->execute(['id1' => $lockIds[0], 'id2' => $lockIds[1]]);
            $lockedRows = [];
            foreach ($lockStmt->fetchAll() as $row) {
                $lockedRows[(int) $row['slot_id']] = $row;
            }

            $newSlot = $lockedRows[$newSlotId] ?? null;
            if (!$newSlot) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Selected new slot does not exist.'];
            }

            if ($newSlot['doctor_account_status'] !== 'active' || $newSlot['availability_status'] !== 'available') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'The doctor for the new slot is currently unavailable.'];
            }

            if ($newSlot['slot_status'] !== 'available' || strtotime((string) $newSlot['start_time']) <= time()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'The chosen new slot is no longer available.'];
            }

            // Ensure no active booking exists on the new slot
            $activeCheck = $this->pdo->prepare(
                'SELECT appointment_id FROM appointments
                 WHERE slot_id = :slot_id AND status IN ("pending", "confirmed")
                 LIMIT 1
                 FOR UPDATE'
            );
            $activeCheck->execute(['slot_id' => $newSlotId]);
            if ($activeCheck->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'The selected slot was just taken by another student.'];
            }

            // 1. Preserve old appointment history by marking it cancelled
            $this->pdo->prepare('UPDATE appointments SET status = "cancelled" WHERE appointment_id = :aid')
                ->execute(['aid' => $appointmentId]);

            // 2. Free old slot if still in the future
            if (isset($lockedRows[$oldSlotId]) && $lockedRows[$oldSlotId]['slot_status'] === 'booked' && strtotime((string) $lockedRows[$oldSlotId]['start_time']) > time()) {
                $this->pdo->prepare('UPDATE appointment_slots SET status = "available" WHERE slot_id = :sid')
                    ->execute(['sid' => $oldSlotId]);
            }

            // 3. Create new appointment record & book new slot
            $finalReason = trim((string) ($reason ?? $oldAppt['reason'] ?? ''));
            $ins = $this->pdo->prepare(
                'INSERT INTO appointments (student_id, slot_id, reason, status)
                 VALUES (:student_id, :slot_id, :reason, "pending")'
            );
            $ins->execute([
                'student_id' => $studentId,
                'slot_id' => $newSlotId,
                'reason' => $finalReason !== '' ? mb_substr($finalReason, 0, 255) : null,
            ]);
            $newAppointmentId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare('UPDATE appointment_slots SET status = "booked" WHERE slot_id = :sid')
                ->execute(['sid' => $newSlotId]);

            $this->pdo->commit();

            $this->notifier->dispatchAppointmentEvent(
                $appointmentId,
                'cancelled',
                sprintf('Appointment #%d was closed due to rescheduling to Appointment #%d.', $appointmentId, $newAppointmentId)
            );
            $this->notifier->dispatchAppointmentEvent($newAppointmentId, 'rescheduled');

            return [
                'success' => true,
                'message' => 'Rescheduled successfully! New Appointment #' . $newAppointmentId . ' has been created while preserving Appointment #' . $appointmentId . ' in your history.',
                'new_appointment_id' => $newAppointmentId,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AppointmentService::rescheduleAppointment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not reschedule appointment. Please try again.'];
        }
    }

    /**
     * Updates an appointment's status by an authorized doctor or administrator.
     *
     * @return array{success: bool, message: string}
     */
    public function updateStatus(int $appointmentId, string $newStatus, int $actorUserId, string $actorRole): array
    {
        $allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return ['success' => false, 'message' => 'Invalid appointment status selected.'];
        }

        if (!in_array($actorRole, ['doctor', 'admin'], true)) {
            return ['success' => false, 'message' => 'Unauthorized action.'];
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT a.appointment_id, a.slot_id, a.status,
                        s.start_time, s.status AS slot_status,
                        d.user_id AS doctor_user_id
                 FROM appointments a
                 INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
                 INNER JOIN doctors d ON d.doctor_id = s.doctor_id
                 WHERE a.appointment_id = :aid
                 FOR UPDATE'
            );
            $stmt->execute(['aid' => $appointmentId]);
            $appt = $stmt->fetch();

            if (!$appt) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Appointment not found.'];
            }

            if ($actorRole === 'doctor' && (int) $appt['doctor_user_id'] !== $actorUserId) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Doctors can only manage appointments assigned to them.'];
            }

            $this->pdo->prepare('UPDATE appointments SET status = :status WHERE appointment_id = :aid')
                ->execute(['status' => $newStatus, 'aid' => $appointmentId]);

            // Release future slot if rejected or cancelled
            if (in_array($newStatus, ['cancelled', 'rejected'], true)
                && $appt['slot_status'] === 'booked'
                && strtotime((string) $appt['start_time']) > time()
            ) {
                $this->pdo->prepare('UPDATE appointment_slots SET status = "available" WHERE slot_id = :sid')
                    ->execute(['sid' => (int) $appt['slot_id']]);
            }

            $this->pdo->commit();

            $this->notifier->dispatchAppointmentEvent($appointmentId, $newStatus);

            return [
                'success' => true,
                'message' => 'Appointment #' . $appointmentId . ' status updated to ' . ucfirst($newStatus) . '.',
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AppointmentService::updateStatus error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update appointment status.'];
        }
    }
}
