<?php
/**
 * CCAMS Automated PHPUnit Integration & Security Test Suite
 * Covers all 16 minimum test cases against the campus_clinic MySQL database.
 *
 * Run locally on XAMPP:
 *   vendor/bin/phpunit
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CCAMSIntegrationTest extends TestCase
{
    private PDO $pdo;
    private AppointmentService $appointmentService;
    private array $createdUserIds = [];
    private array $createdSlotIds = [];

    protected function setUp(): void
    {
        $this->pdo = get_pdo();
        $this->appointmentService = new AppointmentService($this->pdo, new NotificationService($this->pdo));
    }

    protected function tearDown(): void
    {
        // Clean up temporary test appointments, slots, and users created during this test
        if (!empty($this->createdUserIds)) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            $this->pdo->prepare("DELETE FROM users WHERE user_id IN ($placeholders)")
                ->execute($this->createdUserIds);
        }
        if (!empty($this->createdSlotIds)) {
            $placeholders = implode(',', array_fill(0, count($this->createdSlotIds), '?'));
            $this->pdo->prepare("DELETE FROM appointment_slots WHERE slot_id IN ($placeholders)")
                ->execute($this->createdSlotIds);
        }
    }

    private function createTestStudent(string $suffix): int
    {
        $email = 'test.student.' . $suffix . '@st.usted.edu.gh';
        $res = AuthService::registerStudent($this->pdo, [
            'full_name' => 'Test Student ' . $suffix,
            'email' => $email,
            'phone' => '+233 54 999 0001',
            'password' => 'TestPass@2026',
            'confirm_password' => 'TestPass@2026',
        ]);
        $this->assertTrue($res['success'], 'Helper student registration should succeed');
        $uid = (int) $res['user_id'];
        $this->createdUserIds[] = $uid;
        return $uid;
    }

    private function createFutureSlot(int $doctorId = 1, int $hoursAhead = 48): int
    {
        $start = date('Y-m-d H:i:s', time() + ($hoursAhead * 3600));
        $end = date('Y-m-d H:i:s', time() + ($hoursAhead * 3600) + 1800);
        $stmt = $this->pdo->prepare(
            'INSERT INTO appointment_slots (doctor_id, start_time, end_time, status)
             VALUES (:did, :start, :end, "available")'
        );
        $stmt->execute(['did' => $doctorId, 'start' => $start, 'end' => $end]);
        $slotId = (int) $this->pdo->lastInsertId();
        $this->createdSlotIds[] = $slotId;
        return $slotId;
    }

    /** TC-01 & TC-02: Student Registration & Duplicate Email Prevention */
    public function testStudentRegistrationAndDuplicateEmailPrevention(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $email = "akua.mensah.$suffix@st.usted.edu.gh";

        $first = AuthService::registerStudent($this->pdo, [
            'full_name' => 'Akua Mensah',
            'email' => $email,
            'phone' => '+233 54 111 2222',
            'password' => 'Clinic@2026',
            'confirm_password' => 'Clinic@2026',
        ]);
        $this->assertTrue($first['success']);
        $this->createdUserIds[] = (int) $first['user_id'];

        $duplicate = AuthService::registerStudent($this->pdo, [
            'full_name' => 'Akua Mensah Duplicate',
            'email' => $email,
            'phone' => '+233 54 111 3333',
            'password' => 'Clinic@2026',
            'confirm_password' => 'Clinic@2026',
        ]);
        $this->assertFalse($duplicate['success']);
        $this->assertArrayHasKey('email', $duplicate['errors'] ?? []);
    }

    /** TC-03 & TC-04: Login with Valid & Invalid Credentials */
    public function testAuthenticationValidAndInvalidCredentials(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $email = "kofi.boahen.$suffix@st.usted.edu.gh";
        $res = AuthService::registerStudent($this->pdo, [
            'full_name' => 'Kofi Boahen',
            'email' => $email,
            'phone' => '+233 24 555 1212',
            'password' => 'SecurePass#99',
            'confirm_password' => 'SecurePass#99',
        ]);
        $this->createdUserIds[] = (int) $res['user_id'];

        $valid = AuthService::authenticate($this->pdo, $email, 'SecurePass#99');
        $this->assertTrue($valid['success']);
        $this->assertSame('student', $valid['user']['role']);

        $invalid = AuthService::authenticate($this->pdo, $email, 'WrongPassword!');
        $this->assertFalse($invalid['success']);
    }

    /** TC-05 & TC-06: Role Dashboard Routing & RBAC / CSRF Token Checks */
    public function testRoleDashboardMappingAndCsrfVerification(): void
    {
        $this->assertSame('student/dashboard.php', role_dashboard_path('student'));
        $this->assertSame('doctor/dashboard.php', role_dashboard_path('doctor'));
        $this->assertSame('admin/dashboard.php', role_dashboard_path('admin'));

        $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
        $this->assertTrue(verify_csrf_token($_SESSION['csrf_token']));
        $this->assertFalse(verify_csrf_token('tampered_token'));
    }

    /** TC-07 & TC-08: Doctor Listing & Available Slots Query */
    public function testDoctorListingAndSlotAvailability(): void
    {
        $slotId = $this->createFutureSlot(1, 50);

        $doctors = $this->pdo->query(
            'SELECT d.doctor_id, u.full_name, d.specialization
             FROM doctors d INNER JOIN users u ON u.user_id = d.user_id
             WHERE u.account_status = "active"'
        )->fetchAll();
        $this->assertNotEmpty($doctors);

        $slotCheck = $this->pdo->prepare('SELECT status FROM appointment_slots WHERE slot_id = :sid');
        $slotCheck->execute(['sid' => $slotId]);
        $this->assertSame('available', $slotCheck->fetchColumn());
    }

    /** TC-09, TC-10, TC-11 & TC-16: Booking, Occupied Slot Prevention & Notification Generation */
    public function testAppointmentBookingOccupiedRejectionAndNotifications(): void
    {
        $student1 = $this->createTestStudent('s1_' . bin2hex(random_bytes(3)));
        $student2 = $this->createTestStudent('s2_' . bin2hex(random_bytes(3)));
        $slotId = $this->createFutureSlot(1, 60);

        // First booking succeeds
        $book1 = $this->appointmentService->bookAppointment($student1, 1, $slotId, 'Routine checkup');
        $this->assertTrue($book1['success']);
        $apptId = (int) $book1['appointment_id'];

        // Second simultaneous/occupied attempt must fail cleanly
        $book2 = $this->appointmentService->bookAppointment($student2, 1, $slotId, 'Second attempt');
        $this->assertFalse($book2['success']);

        // Verify notification records were generated (in_app + mock/sms)
        $nStmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE appointment_id = :aid');
        $nStmt->execute(['aid' => $apptId]);
        $this->assertGreaterThanOrEqual(2, (int) $nStmt->fetchColumn());
    }

    /** TC-12 & TC-13: Cancellation Preserves History & Frees Slot, Rescheduling Works */
    public function testCancellationHistoryPreservationAndRescheduling(): void
    {
        $studentId = $this->createTestStudent('resched_' . bin2hex(random_bytes(3)));
        $slotA = $this->createFutureSlot(1, 72);
        $slotB = $this->createFutureSlot(1, 96);

        $booked = $this->appointmentService->bookAppointment($studentId, 1, $slotA, 'Eye check');
        $this->assertTrue($booked['success']);
        $apptA = (int) $booked['appointment_id'];

        // Reschedule from slotA to slotB
        $resched = $this->appointmentService->rescheduleAppointment($apptA, $studentId, $slotB, 'Moved to afternoon');
        $this->assertTrue($resched['success']);
        $apptB = (int) $resched['new_appointment_id'];

        // Original appointment preserved as cancelled, slotA freed, slotB booked
        $statusA = $this->pdo->query("SELECT status FROM appointments WHERE appointment_id = $apptA")->fetchColumn();
        $slotAStatus = $this->pdo->query("SELECT status FROM appointment_slots WHERE slot_id = $slotA")->fetchColumn();
        $slotBStatus = $this->pdo->query("SELECT status FROM appointment_slots WHERE slot_id = $slotB")->fetchColumn();

        $this->assertSame('cancelled', $statusA);
        $this->assertSame('available', $slotAStatus);
        $this->assertSame('booked', $slotBStatus);

        // Cancel the rescheduled appointment
        $cancel = $this->appointmentService->cancelAppointment($apptB, $studentId, 'student');
        $this->assertTrue($cancel['success']);
        $slotBStatusAfter = $this->pdo->query("SELECT status FROM appointment_slots WHERE slot_id = $slotB")->fetchColumn();
        $this->assertSame('available', $slotBStatusAfter);
    }

    /** TC-14 & TC-15: Doctor Ownership Enforcement & Status Management */
    public function testDoctorAppointmentManagementAndOwnershipEnforcement(): void
    {
        $studentId = $this->createTestStudent('docmgmt_' . bin2hex(random_bytes(3)));
        $slotId = $this->createFutureSlot(1, 80); // Belongs to doctor_id 1 (user_id 2)

        $booked = $this->appointmentService->bookAppointment($studentId, 1, $slotId, 'Consultation');
        $this->assertTrue($booked['success']);
        $apptId = (int) $booked['appointment_id'];

        // Another doctor (user_id 3 -> doctor_id 2) must be denied
        $wrongDoc = $this->appointmentService->updateStatus($apptId, 'confirmed', 3, 'doctor');
        $this->assertFalse($wrongDoc['success']);

        // Assigned doctor (user_id 2 -> doctor_id 1) can confirm and complete
        $confirm = $this->appointmentService->updateStatus($apptId, 'confirmed', 2, 'doctor');
        $this->assertTrue($confirm['success']);

        $complete = $this->appointmentService->updateStatus($apptId, 'completed', 2, 'doctor');
        $this->assertTrue($complete['success']);
    }
}
