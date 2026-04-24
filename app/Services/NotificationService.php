<?php declare(strict_types=1);

namespace App\Services;

use App\Core\SmtpMailer;
use App\Repositories\FeedbackRepository;
use App\Services\EmailTemplateRenderer;

class NotificationService
{
    public function __construct(
        private FeedbackRepository $repository,
        private SmtpMailer $mailer,
        private EmailTemplateRenderer $templateRenderer,
        private string $defaultHrEmail,
        private string $defaultEthicsEmail,
        private string $baseUrl = 'http://localhost:8000',
    ) {}

    public function notifyNewFeedback(int $reportId, string $reference, string $category): void
    {
        $recipients = $this->repository->getRecipientsByRole('hr');
        if (empty($recipients)) {
            $recipients = [$this->defaultHrEmail];
        }
        $caseUrl = $this->baseUrl . '/hr/cases/' . urlencode($reference);
        $subject = "New anonymous feedback submitted ({$reference})";
        $plain   = "A new anonymous feedback case has been submitted.\n\nReference: {$reference}\nCategory:  {$category}\n\nReview it here:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title' => 'New Feedback Submitted',
            'badge' => 'NEW',
            'badgeColor' => '#008AC4',
            'reference' => $reference,
            'category' => $category,
            'message' => 'A new anonymous feedback case has been submitted and requires your attention.',
            'caseUrl' => $caseUrl,
            'ctaLabel' => 'View Case in HR Console',
            'submittedAt' => '',
        ]);
        foreach ($recipients as $recipient) {
            $this->mailer->sendHtml($recipient, $subject, $html, $plain);
            $this->repository->logNotification($reportId, 'new_feedback', $recipient);
        }
    }

    public function notifyFollowUpSubmitted(int $reportId, string $reference, string $category): void
    {
        $recipients = $this->repository->getRecipientsByRole('hr');
        if (empty($recipients)) {
            $recipients = [$this->defaultHrEmail];
        }
        $caseUrl = $this->baseUrl . '/hr/cases/' . urlencode($reference);
        $subject = "Reporter follow-up received ({$reference})";
        $plain   = "The reporter has submitted a follow-up update on case {$reference}.\n\nReference: {$reference}\nCategory:  {$category}\n\nReview it here:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title'       => 'Reporter Follow-up Received',
            'badge'       => 'UPDATE',
            'badgeColor'  => '#6f42c1',
            'reference'   => $reference,
            'category'    => $category,
            'message'     => 'The reporter has submitted a follow-up update on their case. Please review.',
            'caseUrl'     => $caseUrl,
            'ctaLabel'    => 'View Case Update',
            'submittedAt' => '',
        ]);
        foreach ($recipients as $recipient) {
            $this->mailer->sendHtml($recipient, $subject, $html, $plain);
            $this->repository->logNotification($reportId, 'followup_notification', $recipient);
        }
    }

    public function processScheduledNotifications(): array
    {
        $reminders   = 0;
        $escalations = 0;

        $pendingReminders = $this->repository->getUnacknowledgedReportsNeedingNotification(48, 'reminder_48h');
        foreach ($pendingReminders as $report) {
            $recipients = $this->repository->getRecipientsByRole('hr');
            if (empty($recipients)) {
                $recipients = [$this->defaultHrEmail];
            }
            $caseUrl = $this->baseUrl . '/hr/cases/' . urlencode($report['reference_no']);
            $subject = "Reminder: feedback case not acknowledged ({$report['reference_no']})";
            $plain   = "This case has not been acknowledged within 48 hours.\n\nReference: {$report['reference_no']}\nCategory:  {$report['category']}\nSubmitted: {$report['created_at']}\n\nReview it here:\n{$caseUrl}";
            $html = $this->templateRenderer->renderNotification([
                'title' => '48-Hour Reminder',
                'badge' => 'REMINDER',
                'badgeColor' => '#f0ad4e',
                'reference' => (string) $report['reference_no'],
                'category' => (string) $report['category'],
                'message' => 'This feedback case has not been acknowledged within 48 hours. Please review it promptly.',
                'caseUrl' => $caseUrl,
                'ctaLabel' => 'Acknowledge Case',
                'submittedAt' => (string) $report['created_at'],
            ]);
            foreach ($recipients as $recipient) {
                $this->mailer->sendHtml($recipient, $subject, $html, $plain);
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
            $caseUrl = $this->baseUrl . '/hr/cases/' . urlencode($report['reference_no']);
            $subject = "Escalation: feedback case not acknowledged ({$report['reference_no']})";
            $plain   = "This case has not been acknowledged within 72 hours and has been escalated.\n\nReference: {$report['reference_no']}\nCategory:  {$report['category']}\nSubmitted: {$report['created_at']}\n\nReview it here:\n{$caseUrl}";
            $html = $this->templateRenderer->renderNotification([
                'title' => '72-Hour Escalation',
                'badge' => 'ESCALATED',
                'badgeColor' => '#9d2722',
                'reference' => (string) $report['reference_no'],
                'category' => (string) $report['category'],
                'message' => 'This feedback case has not been acknowledged within 72 hours and has been escalated to the Ethics Officer.',
                'caseUrl' => $caseUrl,
                'ctaLabel' => 'Review Escalated Case',
                'submittedAt' => (string) $report['created_at'],
            ]);
            foreach ($recipients as $recipient) {
                $this->mailer->sendHtml($recipient, $subject, $html, $plain);
                $this->repository->logNotification((int) $report['id'], 'escalation_72h', $recipient);
                $escalations++;
            }
        }

        return ['reminders_sent' => $reminders, 'escalations_sent' => $escalations];
    }

}
