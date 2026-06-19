<?php declare(strict_types=1);

namespace App\Services;

use App\Core\SmtpMailer;
use App\Repositories\AuditRepository;
use App\Services\EmailTemplateRenderer;

class NotificationService
{
    private const HR_CASES_PATH = '/hr/cases/';
    private const BADGE_DANGER = '#9d2722';
    private const BADGE_INFO = '#008AC4';
    private const BADGE_UPDATE = '#6f42c1';
    private const ROLE_CASE_MANAGER = 'Case Manager';
    private const CTA_VIEW_CASE = 'View Case';

    public function __construct(
        private AuditRepository $auditRepository,
        private SmtpMailer $mailer,
        private EmailTemplateRenderer $templateRenderer,
        private string $baseUrl = '',
        private bool $immediateNotificationsEnabled = true,
        private bool $scheduledNotificationsEnabled = true,
    ) {
        if (empty($this->baseUrl)) {
            $configured = getenv('APP_BASE_URL');
            $this->baseUrl = ($configured !== false && $configured !== '')
                ? $configured
                : self::detectBaseUrl();
        }
    }

    private function isDisallowedRecipient(string $email): bool
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || !str_contains($normalized, '@')) {
            return true;
        }

        $domain = substr(strrchr($normalized, '@') ?: '', 1);
        $blockedDomains = ['organization.com', 'example.com', 'example.local'];
        return in_array($domain, $blockedDomains, true);
    }

    
    private function resolveRecipientsByRole(string $role): array
    {
        $recipients = $this->auditRepository->getRecipientsByRole($role);
        $recipients = array_values(array_filter(
            $recipients,
            fn(string $email): bool => !$this->isDisallowedRecipient($email)
        ));
        return array_values(array_unique($recipients));
    }

    private function resolveDeveloperRecipients(): array
    {
        $raw = trim((string) (getenv('DEVELOPER_OVERRIDE_USERS') ?: ''));
        if ($raw === '') {
            return [];
        }

        $tokens = array_values(array_filter(array_map(
            static fn(string $value): string => trim($value),
            explode(',', $raw)
        ), static fn(string $value): bool => $value !== ''));

        if ($tokens === []) {
            return [];
        }

        $emails = $this->auditRepository->getActiveUserEmailsByIdentifiers($tokens);
        return array_values(array_filter(
            array_unique($emails),
            fn(string $email): bool => !$this->isDisallowedRecipient($email)
        ));
    }

    private function withDeveloperRecipients(array $recipients): array
    {
        $all = array_merge($recipients, $this->resolveDeveloperRecipients());
        $all = array_values(array_filter(array_unique($all), fn(string $email): bool => !$this->isDisallowedRecipient($email)));
        return $all;
    }

    private static function detectBaseUrl(): string
    {
        
        if (PHP_SAPI === 'cli') {
            return 'http://localhost';
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $port   = (int) ($_SERVER['SERVER_PORT'] ?? 80);

        
        if (!str_contains((string) $host, ':')) {
            $isNonDefaultPort = ($scheme === 'https' && $port !== 443)
                || ($scheme === 'http' && $port !== 80);
            if ($isNonDefaultPort) {
                $host .= ':' . $port;
            }
        }

        return $scheme . '://' . $host;
    }

    private function buildCaseUrl(string $reference): string
    {
        $internal = getenv('INTERNAL_APP_BASE_URL');
        $targetBase = ($internal !== false && trim($internal) !== '')
            ? (string) $internal
            : $this->baseUrl;

        return rtrim(trim($targetBase), '/') . self::HR_CASES_PATH . urlencode($reference);
    }

    public function notifyNewFeedback(string $feedbackId, string $reference, string $category): void
    {
        if (!$this->immediateNotificationsEnabled) {
            return;
        }

        $recipients = $this->withDeveloperRecipients($this->resolveRecipientsByRole(self::ROLE_CASE_MANAGER));
        if (empty($recipients)) {
            return;
        }
        $caseUrl = $this->buildCaseUrl($reference);
        $subject = "New anonymous feedback submitted ({$reference})";
        $plain   = "A new anonymous feedback case has been submitted.\n\nReference: {$reference}\nCategory:  {$category}\n\nReview it here:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title' => 'New Feedback Submitted',
            'badge' => 'NEW',
            'badgeColor' => self::BADGE_INFO,
            'reference' => $reference,
            'category' => $category,
            'message' => 'A new anonymous feedback case has been submitted and requires your attention.',
            'caseUrl' => $caseUrl,
            'ctaLabel' => 'View Case in HR Console',
            'submittedAt' => '',
        ]);
        foreach ($recipients as $recipient) {
            $this->mailer->sendHtml($recipient, $subject, $html, $plain);
            $this->auditRepository->logNotification($feedbackId, 'new_feedback', $recipient);
        }
    }

    public function notifyFollowUpSubmitted(string $feedbackId, string $reference, string $category): void
    {
        if (!$this->immediateNotificationsEnabled) {
            return;
        }

        $recipients = $this->withDeveloperRecipients($this->resolveRecipientsByRole(self::ROLE_CASE_MANAGER));
        if (empty($recipients)) {
            return;
        }
        $caseUrl = $this->buildCaseUrl($reference);
        $subject = "Reporter follow-up received ({$reference})";
        $plain   = "The reporter has submitted a follow-up update on case {$reference}.\n\nReference: {$reference}\nCategory:  {$category}\n\nReview it here:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title'       => 'Reporter Follow-up Received',
            'badge'       => 'UPDATE',
            'badgeColor'  => self::BADGE_UPDATE,
            'reference'   => $reference,
            'category'    => $category,
            'message'     => 'The reporter has submitted a follow-up update on their case. Please review.',
            'caseUrl'     => $caseUrl,
            'ctaLabel'    => 'View Case Update',
            'submittedAt' => '',
        ]);
        foreach ($recipients as $recipient) {
            $this->mailer->sendHtml($recipient, $subject, $html, $plain);
            $this->auditRepository->logNotification($feedbackId, 'followup_notif', $recipient);
        }
    }

    public function notifyCaseAssigned(
        string $feedbackId,
        string $reference,
        string $category,
        string $recipientEmail,
        string $assigneeName,
        string $assignedByName,
        bool $isReassignment = false
    ): void {
        if (!$this->immediateNotificationsEnabled) {
            return;
        }

        $recipient = trim($recipientEmail);
        if ($this->isDisallowedRecipient($recipient)) {
            return;
        }

        $caseUrl = $this->buildCaseUrl($reference);
        $verb = $isReassignment ? 'reassigned' : 'assigned';
        $subject = "Case {$verb} to you ({$reference})";
        $plain = "A feedback case has been {$verb} to you.\n\n"
            . "Reference: {$reference}\n"
            . "Category:  {$category}\n"
            . "Assigned to: {$assigneeName}\n"
            . "Assigned by: {$assignedByName}\n\n"
            . "Review it here:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title' => $isReassignment ? 'Case Reassigned to You' : 'Case Assigned to You',
            'badge' => $isReassignment ? 'REASSIGNED' : 'ASSIGNED',
            'badgeColor' => self::BADGE_INFO,
            'reference' => $reference,
            'category' => $category,
            'message' => "This case was {$verb} to you by {$assignedByName}.",
            'caseUrl' => $caseUrl,
            'ctaLabel' => 'Open Assigned Case',
            'submittedAt' => '',
        ]);

        $recipients = [$recipient];
        foreach ($recipients as $target) {
            $this->mailer->sendHtml($target, $subject, $html, $plain);
            $this->auditRepository->logNotification($feedbackId, 'assignment_notif', $target);
        }
    }

    public function notifyCaseUnassigned(
        string $feedbackId,
        string $reference,
        string $category,
        string $recipientEmail,
        string $assigneeName,
        string $unassignedByName
    ): void {
        if (!$this->immediateNotificationsEnabled) {
            return;
        }

        $recipient = trim($recipientEmail);
        if ($this->isDisallowedRecipient($recipient)) {
            return;
        }

        $caseUrl = $this->buildCaseUrl($reference);
        $subject = "Case unassigned from you ({$reference})";
        $plain = "A feedback case has been unassigned from you.\n\n"
            . "Reference: {$reference}\n"
            . "Category:  {$category}\n"
            . "Previously assigned to: {$assigneeName}\n"
            . "Updated by: {$unassignedByName}\n\n"
            . "View case details:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title' => 'Case Unassigned',
            'badge' => 'UNASSIGNED',
            'badgeColor' => self::BADGE_DANGER,
            'reference' => $reference,
            'category' => $category,
            'message' => "This case was unassigned from you by {$unassignedByName}.",
            'caseUrl' => $caseUrl,
            'ctaLabel' => self::CTA_VIEW_CASE,
            'submittedAt' => '',
        ]);

        $recipients = [$recipient];
        foreach ($recipients as $target) {
            $this->mailer->sendHtml($target, $subject, $html, $plain);
            $this->auditRepository->logNotification($feedbackId, 'unassignment_notif', $target);
        }
    }

    public function notifyCoInvestigatorAdded(
        string $feedbackId,
        string $reference,
        string $category,
        string $recipientEmail,
        string $coInvestigatorName,
        string $addedByName,
        string $leadInvestigatorDisplay = ''
    ): void {
        if (!$this->immediateNotificationsEnabled) {
            return;
        }

        $recipient = trim($recipientEmail);
        if ($this->isDisallowedRecipient($recipient)) {
            return;
        }

        $leadLabel = trim($leadInvestigatorDisplay) !== '' ? $leadInvestigatorDisplay : 'Unassigned';
        $caseUrl = $this->buildCaseUrl($reference);
        $subject = "Co-investigator assignment ({$reference}) | Lead: {$leadLabel}";
        $plain = "You have been added as a co-investigator on a feedback case.\n\n"
            . "Reference: {$reference}\n"
            . "Category:  {$category}\n"
            . "Lead investigator: {$leadLabel}\n"
            . "Co-investigator: {$coInvestigatorName}\n"
            . "Added by: {$addedByName}\n\n"
            . "Review the case:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title' => 'Added as Co-Investigator',
            'badge' => 'CO-INVESTIGATOR',
            'badgeColor' => self::BADGE_UPDATE,
            'reference' => $reference,
            'category' => $category,
            'message' => "You have been added as a co-investigator on this case by {$addedByName}. Lead investigator: {$leadLabel}.",
            'caseUrl' => $caseUrl,
            'ctaLabel' => self::CTA_VIEW_CASE,
            'submittedAt' => '',
        ]);

        $recipients = [$recipient];
        foreach ($recipients as $target) {
            $this->mailer->sendHtml($target, $subject, $html, $plain);
            $this->auditRepository->logNotification($feedbackId, 'co_investigator_added', $target);
        }
    }

    public function notifyCoInvestigatorRemoved(
        string $feedbackId,
        string $reference,
        string $category,
        string $recipientEmail,
        string $coInvestigatorName,
        string $removedByName
    ): void {
        if (!$this->immediateNotificationsEnabled) {
            return;
        }

        $recipient = trim($recipientEmail);
        if ($this->isDisallowedRecipient($recipient)) {
            return;
        }

        $caseUrl = $this->buildCaseUrl($reference);
        $subject = "You have been removed as co-investigator ({$reference})";
        $plain = "You have been removed as a co-investigator on a feedback case.\n\n"
            . "Reference: {$reference}\n"
            . "Category:  {$category}\n"
            . "Co-investigator: {$coInvestigatorName}\n"
            . "Removed by: {$removedByName}\n\n"
            . "View case details:\n{$caseUrl}";
        $html = $this->templateRenderer->renderNotification([
            'title' => 'Removed as Co-Investigator',
            'badge' => 'REMOVED',
            'badgeColor' => self::BADGE_DANGER,
            'reference' => $reference,
            'category' => $category,
            'message' => "You have been removed as a co-investigator on this case by {$removedByName}.",
            'caseUrl' => $caseUrl,
            'ctaLabel' => self::CTA_VIEW_CASE,
            'submittedAt' => '',
        ]);

        $recipients = [$recipient];
        foreach ($recipients as $target) {
            $this->mailer->sendHtml($target, $subject, $html, $plain);
            $this->auditRepository->logNotification($feedbackId, 'co_investigator_removed', $target);
        }
    }

    public function processScheduledNotifications(): array
    {
        if (!$this->scheduledNotificationsEnabled) {
            return [
                'new_feedback_sent' => 0,
                'followups_sent' => 0,
                'reminders_sent' => 0,
                'escalations_sent' => 0,
            ];
        }

        return [
            'new_feedback_sent' => $this->processPendingNewFeedbackNotifications(),
            'followups_sent' => $this->processPendingFollowUpNotifications(),
            'reminders_sent' => $this->processReminderNotifications(),
            'escalations_sent' => $this->processEscalationNotifications(),
        ];
    }

    private function processPendingNewFeedbackNotifications(): int
    {
        $sent = 0;
        $pending = $this->auditRepository->getPendingNewFeedbackNotifications();

        foreach ($pending as $report) {
            $recipients = $this->withDeveloperRecipients($this->resolveRecipientsByRole(self::ROLE_CASE_MANAGER));
            if (empty($recipients)) {
                continue;
            }

            $reference = (string) $report['reference_no'];
            $category  = (string) $report['category'];
            $caseUrl = $this->buildCaseUrl($reference);
            $subject = "New anonymous feedback submitted ({$reference})";
            $plain   = "A new anonymous feedback case has been submitted.\n\nReference: {$reference}\nCategory:  {$category}\n\nReview it here:\n{$caseUrl}";
            $html = $this->templateRenderer->renderNotification([
                'title' => 'New Feedback Submitted',
                'badge' => 'NEW',
                'badgeColor' => self::BADGE_INFO,
                'reference' => $reference,
                'category' => $category,
                'message' => 'A new anonymous feedback case has been submitted and requires your attention.',
                'caseUrl' => $caseUrl,
                'ctaLabel' => 'View Case in HR Console',
                'submittedAt' => (string) ($report['created_at'] ?? ''),
            ]);

            foreach ($recipients as $recipient) {
                $this->mailer->sendHtml($recipient, $subject, $html, $plain);
                $this->auditRepository->logNotification((string) $report['id'], 'new_feedback', $recipient);
                $sent++;
            }
        }

        return $sent;
    }

    private function processPendingFollowUpNotifications(): int
    {
        $sent = 0;
        $pending = $this->auditRepository->getPendingFollowUpNotifications();

        foreach ($pending as $report) {
            $recipients = $this->withDeveloperRecipients($this->resolveRecipientsByRole(self::ROLE_CASE_MANAGER));
            if (empty($recipients)) {
                continue;
            }

            $reference = (string) $report['reference_no'];
            $category  = (string) $report['category'];
            $caseUrl = $this->buildCaseUrl($reference);
            $subject = "Reporter follow-up received ({$reference})";
            $plain   = "The reporter has submitted a follow-up update on case {$reference}.\n\nReference: {$reference}\nCategory:  {$category}\n\nReview it here:\n{$caseUrl}";
            $html = $this->templateRenderer->renderNotification([
                'title'       => 'Reporter Follow-up Received',
                'badge'       => 'UPDATE',
                'badgeColor'  => self::BADGE_UPDATE,
                'reference'   => $reference,
                'category'    => $category,
                'message'     => 'The reporter has submitted a follow-up update on their case. Please review.',
                'caseUrl'     => $caseUrl,
                'ctaLabel'    => 'View Case Update',
                'submittedAt' => (string) ($report['created_at'] ?? ''),
            ]);

            foreach ($recipients as $recipient) {
                $this->mailer->sendHtml($recipient, $subject, $html, $plain);
                $kind = (string) ($report['notification_kind'] ?? 'followup_notif');
                $this->auditRepository->logNotification((string) $report['id'], $kind, $recipient);
                $sent++;
            }
        }

        return $sent;
    }

    private function processReminderNotifications(): int
    {
        $sent = 0;
        $pending = $this->auditRepository->getUnacknowledgedReportsNeedingNotification(48, 'reminder_48h');

        foreach ($pending as $report) {
            $recipients = $this->withDeveloperRecipients($this->resolveRecipientsByRole(self::ROLE_CASE_MANAGER));
            if (empty($recipients)) {
                continue;
            }

            $caseUrl = $this->buildCaseUrl((string) $report['reference_no']);
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
                $this->auditRepository->logNotification((string) $report['id'], 'reminder_48h', $recipient);
                $sent++;
            }
        }

        return $sent;
    }

    private function processEscalationNotifications(): int
    {
        $sent = 0;
        $pending = $this->auditRepository->getUnacknowledgedReportsNeedingNotification(72, 'escalation_72h');

        foreach ($pending as $report) {
            $recipients = $this->withDeveloperRecipients($this->resolveRecipientsByRole('ethics'));
            if (empty($recipients)) {
                continue;
            }

            $caseUrl = $this->buildCaseUrl((string) $report['reference_no']);
            $subject = "Escalation: feedback case not acknowledged ({$report['reference_no']})";
            $plain   = "This case has not been acknowledged within 72 hours and has been escalated.\n\nReference: {$report['reference_no']}\nCategory:  {$report['category']}\nSubmitted: {$report['created_at']}\n\nReview it here:\n{$caseUrl}";
            $html = $this->templateRenderer->renderNotification([
                'title' => '72-Hour Escalation',
                'badge' => 'ESCALATED',
                'badgeColor' => self::BADGE_DANGER,
                'reference' => (string) $report['reference_no'],
                'category' => (string) $report['category'],
                'message' => 'This feedback case has not been acknowledged within 72 hours and has been escalated to the Ethics Officer.',
                'caseUrl' => $caseUrl,
                'ctaLabel' => 'Review Escalated Case',
                'submittedAt' => (string) $report['created_at'],
            ]);

            foreach ($recipients as $recipient) {
                $this->mailer->sendHtml($recipient, $subject, $html, $plain);
                $this->auditRepository->logNotification((string) $report['id'], 'escalation_72h', $recipient);
                $sent++;
            }
        }

        return $sent;
    }

}
