<?php declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeedbackRepository;

class NotificationService {
    public function __construct(
        private FeedbackRepository $repository,
        private string $fromEmail,
        private string $defaultHrEmail,
        private string $defaultEthicsEmail
    ) {}

    /**
     * Send notification for newly submitted feedback.
     */
    public function notifyNewFeedback(int $reportId, string $reference, string $category): void {
        $recipients = $this->repository->getRecipientsByRole('hr');
        if (empty($recipients)) {
            $recipients = [$this->defaultHrEmail];
        }

        $subject = "New anonymous feedback submitted ({$reference})";
        $body = "A new anonymous feedback case has been submitted.\n\nReference: {$reference}\nCategory: {$category}\n\nPlease review it in the HR Console.";

        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient, $subject, $body);
            $this->repository->logNotification($reportId, 'new_feedback', $recipient);
        }
    }

    /**
     * Process 48-hour reminders and 72-hour escalations for unacknowledged reports.
     */
    public function processScheduledNotifications(): array {
        $reminders = 0;
        $escalations = 0;

        $pendingReminders = $this->repository->getUnacknowledgedReportsNeedingNotification(48, 'reminder_48h');
        foreach ($pendingReminders as $report) {
            $recipients = $this->repository->getRecipientsByRole('hr');
            if (empty($recipients)) {
                $recipients = [$this->defaultHrEmail];
            }

            $subject = "Reminder: feedback case not acknowledged ({$report['reference_no']})";
            $body = "This case has not been acknowledged within 48 hours.\n\nReference: {$report['reference_no']}\nCategory: {$report['category']}\nCreated At: {$report['created_at']}";

            foreach ($recipients as $recipient) {
                $this->sendEmail($recipient, $subject, $body);
                $this->repository->logNotification((int) $report['id'], 'reminder_48h', $recipient);
                $reminders++;
            }
        }

        $pendingEscalations = $this->repository->getUnacknowledgedReportsNeedingNotification(72, 'escalation_72h');
        foreach ($pendingEscalations as $report) {
            $recipients = $this->repository->getRecipientsByRole('ethics');
            if (empty($recipients)) {
                $recipients = [$this->defaultEthicsEmail];
            }

            $subject = "Escalation: feedback case not acknowledged ({$report['reference_no']})";
            $body = "This case has not been acknowledged within 72 hours and is now escalated.\n\nReference: {$report['reference_no']}\nCategory: {$report['category']}\nCreated At: {$report['created_at']}";

            foreach ($recipients as $recipient) {
                $this->sendEmail($recipient, $subject, $body);
                $this->repository->logNotification((int) $report['id'], 'escalation_72h', $recipient);
                $escalations++;
            }
        }

        return [
            'reminders_sent' => $reminders,
            'escalations_sent' => $escalations,
        ];
    }

    private function sendEmail(string $to, string $subject, string $body): void {
        $headers = [
            'From: ' . $this->fromEmail,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        @mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
