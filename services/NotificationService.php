<?php
/**
 * Decoupled Notification Service & Channel Adapters
 *
 * Separates notification delivery from core appointment booking logic.
 * Supports:
 *  1. In-app database notifications (delivery_status = 'sent')
 *  2. Mock development adapter (delivery_status = 'simulated')
 *  3. Optional external SMS Sandbox HTTP adapter (honestly records 'queued' or 'failed')
 */

declare(strict_types=1);

interface NotificationAdapterInterface
{
    /**
     * @param array<string, mixed> $context
     * @return array{channel: string, delivery_status: string, sent_at: ?string}
     */
    public function send(string $recipientPhone, string $message, array $context = []): array;
}

class InAppNotificationAdapter implements NotificationAdapterInterface
{
    public function send(string $recipientPhone, string $message, array $context = []): array
    {
        return [
            'channel' => 'in_app',
            'delivery_status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ];
    }
}

class MockNotificationAdapter implements NotificationAdapterInterface
{
    public function send(string $recipientPhone, string $message, array $context = []): array
    {
        // Simulates external dispatch without claiming real carrier delivery
        return [
            'channel' => 'mock',
            'delivery_status' => 'simulated',
            'sent_at' => date('Y-m-d H:i:s'),
        ];
    }
}

class SmsSandboxAdapter implements NotificationAdapterInterface
{
    private string $endpoint;
    private string $apiKey;
    private string $senderId;

    public function __construct(string $endpoint, string $apiKey, string $senderId = 'USTEDClinic')
    {
        $this->endpoint = trim($endpoint);
        $this->apiKey = trim($apiKey);
        $this->senderId = $senderId;
    }

    public function isConfigured(): bool
    {
        return $this->endpoint !== '' && $this->apiKey !== '' && function_exists('curl_init');
    }

    public function send(string $recipientPhone, string $message, array $context = []): array
    {
        if (!$this->isConfigured() || trim($recipientPhone) === '') {
            // Honest fallback: report simulated instead of claiming SMS delivery
            return [
                'channel' => 'mock',
                'delivery_status' => 'simulated',
                'sent_at' => date('Y-m-d H:i:s'),
            ];
        }

        $payload = json_encode([
            'sender' => $this->senderId,
            'recipient' => $recipientPhone,
            'message' => $message,
            'sandbox' => true,
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            // Sandbox API accepted request for queuing (not claiming carrier delivery)
            return [
                'channel' => 'sms',
                'delivery_status' => 'queued',
                'sent_at' => date('Y-m-d H:i:s'),
            ];
        }

        return [
            'channel' => 'sms',
            'delivery_status' => 'failed',
            'sent_at' => null,
        ];
    }
}

class NotificationService
{
    private PDO $pdo;
    private InAppNotificationAdapter $inAppAdapter;
    private NotificationAdapterInterface $externalAdapter;

    public function __construct(PDO $pdo, ?NotificationAdapterInterface $externalAdapter = null)
    {
        $this->pdo = $pdo;
        $this->inAppAdapter = new InAppNotificationAdapter();

        if ($externalAdapter !== null) {
            $this->externalAdapter = $externalAdapter;
        } else {
            $notifCfg = ccams_config()['notifications'] ?? [];
            $mode = $notifCfg['default_external_mode'] ?? 'mock';
            if ($mode === 'sms_sandbox') {
                $this->externalAdapter = new SmsSandboxAdapter(
                    (string) ($notifCfg['sms_sandbox_endpoint'] ?? ''),
                    (string) ($notifCfg['sms_sandbox_api_key'] ?? ''),
                    (string) ($notifCfg['sms_sender_id'] ?? 'USTEDClinic')
                );
            } else {
                $this->externalAdapter = new MockNotificationAdapter();
            }
        }
    }

    /**
     * Generates and persists both in-app and external (mock/sandbox) notifications
     * for an appointment lifecycle event.
     */
    public function dispatchAppointmentEvent(int $appointmentId, string $eventType, string $customMessage = ''): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.appointment_id, a.status, s.start_time,
                    stu.full_name AS student_name, stu.phone AS student_phone,
                    doc_u.full_name AS doctor_name, d.specialization
             FROM appointments a
             INNER JOIN users stu ON stu.user_id = a.student_id
             INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
             INNER JOIN doctors d ON d.doctor_id = s.doctor_id
             INNER JOIN users doc_u ON doc_u.user_id = d.user_id
             WHERE a.appointment_id = :aid
             LIMIT 1'
        );
        $stmt->execute(['aid' => $appointmentId]);
        $info = $stmt->fetch();

        if (!$info) {
            return;
        }

        $when = format_datetime((string) $info['start_time']);
        $doc = (string) $info['doctor_name'];

        $message = $customMessage !== '' ? $customMessage : match ($eventType) {
            'booked' => sprintf(
                'Appointment #%d booked with %s (%s) on %s. Status: Pending confirmation.',
                $appointmentId,
                $doc,
                $info['specialization'],
                $when
            ),
            'cancelled' => sprintf(
                'Appointment #%d with %s on %s has been cancelled.',
                $appointmentId,
                $doc,
                $when
            ),
            'rescheduled' => sprintf(
                'Appointment #%d has been rescheduled with %s for %s.',
                $appointmentId,
                $doc,
                $when
            ),
            'confirmed' => sprintf(
                'Good news! Appointment #%d with %s on %s has been confirmed.',
                $appointmentId,
                $doc,
                $when
            ),
            'completed' => sprintf(
                'Appointment #%d with %s (%s) has been marked as completed.',
                $appointmentId,
                $doc,
                $when
            ),
            'rejected' => sprintf(
                'Appointment #%d with %s on %s was declined by the clinic. Please book another slot.',
                $appointmentId,
                $doc,
                $when
            ),
            default => sprintf(
                'Update on Appointment #%d with %s (%s): status is now %s.',
                $appointmentId,
                $doc,
                $when,
                ucfirst((string) $info['status'])
            ),
        };

        $phone = (string) ($info['student_phone'] ?? '');

        // 1. Always record in-app notification
        $inAppResult = $this->inAppAdapter->send($phone, $message, $info);
        $this->persistNotification($appointmentId, $inAppResult['channel'], $message, $inAppResult['delivery_status'], $inAppResult['sent_at']);

        // 2. Dispatch decoupled external/mock/sandbox notification
        $extMessage = '[USTED Clinic] ' . $message;
        $extResult = $this->externalAdapter->send($phone, $extMessage, $info);
        $this->persistNotification($appointmentId, $extResult['channel'], $extMessage, $extResult['delivery_status'], $extResult['sent_at']);
    }

    private function persistNotification(
        int $appointmentId,
        string $channel,
        string $message,
        string $deliveryStatus,
        ?string $sentAt
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (appointment_id, channel, message, delivery_status, is_read, sent_at)
             VALUES (:appointment_id, :channel, :message, :delivery_status, :is_read, :sent_at)'
        );
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'channel' => $channel,
            'message' => $message,
            'delivery_status' => $deliveryStatus,
            'is_read' => $channel === 'in_app' ? 0 : 1,
            'sent_at' => $sentAt,
        ]);
    }
}
