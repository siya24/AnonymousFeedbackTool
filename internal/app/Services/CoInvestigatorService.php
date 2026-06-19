<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Repositories\CoInvestigatorRepository;
use App\Repositories\FeedbackRepository;

class CoInvestigatorService {
    private const MSG_FEEDBACK_NOT_FOUND = 'Feedback not found';
    private const DEFAULT_HR_ACTOR       = 'HR User';

    private static function logWithTimestamp(string $message): void
    {
        error_log(sprintf('[%s] %s', (new \DateTimeImmutable('now'))->format(DATE_ATOM), $message));
    }

    public function __construct(
        private FeedbackRepository $feedbackRepository,
        private CoInvestigatorRepository $coInvestigatorRepository,
        private NotificationService $notificationService
    ) {}

    private function buildLeadInvestigatorDisplay(array $feedback): string
    {
        $name = trim((string) ($feedback['assigned_to_name'] ?? ''));
        $email = trim((string) ($feedback['assigned_to_email'] ?? ''));
        $label = 'Unassigned';

        if ($name !== '' && $email !== '') {
            $label = $name . ' (' . $email . ')';
        } elseif ($name !== '') {
            $label = $name;
        } elseif ($email !== '') {
            $label = $email;
        }

        return $label;
    }

    /** @return array{feedback:array,feedback_id:string} */
    private function resolveFeedbackContext(string $feedbackIdentifier): array
    {
        $identifier = trim($feedbackIdentifier);
        if ($identifier === '') {
            throw new NotFoundException(self::MSG_FEEDBACK_NOT_FOUND);
        }

        $feedback = $this->feedbackRepository->findById($identifier);
        if (!$feedback) {
            $feedback = $this->feedbackRepository->findByReference(strtoupper($identifier));
        }
        if (!$feedback) {
            throw new NotFoundException(self::MSG_FEEDBACK_NOT_FOUND);
        }

        return [
            'feedback' => $feedback,
            'feedback_id' => (string) ($feedback['id'] ?? ''),
        ];
    }

    public function addCoInvestigator(string $feedbackIdentifier, string $userId, ?string $addedByUserId = null): array {
        $context = $this->resolveFeedbackContext($feedbackIdentifier);
        $feedback = $context['feedback'];
        $feedbackId = $context['feedback_id'];

        if ($feedback['assigned_to_user_id'] === $userId) {
            throw new \App\Exceptions\ValidationException('Primary investigator cannot be added as co-investigator');
        }

        $added = $this->coInvestigatorRepository->addCoInvestigator($feedbackId, $userId, $addedByUserId);
        if (!$added) {
            throw new ConflictException('User is already a co-investigator');
        }

        try {
            $coInvestigator = $this->coInvestigatorRepository->getUserById($userId);
            $addedByUser = $addedByUserId ? $this->coInvestigatorRepository->getUserById($addedByUserId) : null;

            if ($coInvestigator && $coInvestigator['email']) {
                $this->notificationService->notifyCoInvestigatorAdded(
                    $feedbackId,
                    (string) $feedback['reference_no'],
                    (string) $feedback['category'],
                    (string) $coInvestigator['email'],
                    (string) $coInvestigator['name'],
                    $addedByUser ? (string) $addedByUser['name'] : self::DEFAULT_HR_ACTOR,
                    $this->buildLeadInvestigatorDisplay($feedback)
                );
            }
        } catch (\Exception $e) {
            self::logWithTimestamp("Failed to send co-investigator notification: " . $e->getMessage());
        }

        return $this->coInvestigatorRepository->getCoInvestigators($feedbackId);
    }

    public function removeCoInvestigator(string $feedbackIdentifier, string $userId, ?string $removedByUserId = null): array {
        $context = $this->resolveFeedbackContext($feedbackIdentifier);
        $feedback = $context['feedback'];
        $feedbackId = $context['feedback_id'];

        $this->coInvestigatorRepository->removeCoInvestigator($feedbackId, $userId);

        try {
            $coInvestigator = $this->coInvestigatorRepository->getUserById($userId);
            $removedByUser = $removedByUserId ? $this->coInvestigatorRepository->getUserById($removedByUserId) : null;

            if ($coInvestigator && $coInvestigator['email']) {
                $this->notificationService->notifyCoInvestigatorRemoved(
                    $feedbackId,
                    (string) $feedback['reference_no'],
                    (string) $feedback['category'],
                    (string) $coInvestigator['email'],
                    (string) $coInvestigator['name'],
                    $removedByUser ? (string) $removedByUser['name'] : self::DEFAULT_HR_ACTOR
                );
            }
        } catch (\Exception $e) {
            self::logWithTimestamp("Failed to send co-investigator removal notification: " . $e->getMessage());
        }

        return $this->coInvestigatorRepository->getCoInvestigators($feedbackId);
    }

    public function getCoInvestigators(string $feedbackIdentifier): array {
        $context = $this->resolveFeedbackContext($feedbackIdentifier);
        $feedbackId = $context['feedback_id'];

        return $this->coInvestigatorRepository->getCoInvestigators($feedbackId);
    }

    public function replaceCoInvestigators(string $feedbackIdentifier, array $userIds, ?string $updatedByUserId = null): array {
        $context = $this->resolveFeedbackContext($feedbackIdentifier);
        $feedback = $context['feedback'];
        $feedbackId = $context['feedback_id'];

        $currentIds = array_column($this->coInvestigatorRepository->getCoInvestigators($feedbackId), 'user_id');
        $this->coInvestigatorRepository->removeAllCoInvestigators($feedbackId);

        $this->notifyRemovedCoInvestigators($feedbackId, $feedback, $currentIds, $updatedByUserId);
        $this->addAndNotifyNewCoInvestigators($feedbackId, $feedback, $userIds, $updatedByUserId);

        return $this->coInvestigatorRepository->getCoInvestigators($feedbackId);
    }

    private function notifyRemovedCoInvestigators(string $feedbackId, array $feedback, array $userIds, ?string $removedByUserId): void
    {
        try {
            $removedByUser = $removedByUserId ? $this->coInvestigatorRepository->getUserById($removedByUserId) : null;
            $actorName = $removedByUser ? (string) $removedByUser['name'] : self::DEFAULT_HR_ACTOR;
            foreach ($userIds as $userId) {
                $coInvestigator = $this->coInvestigatorRepository->getUserById($userId);
                if ($coInvestigator && $coInvestigator['email']) {
                    $this->notificationService->notifyCoInvestigatorRemoved(
                        $feedbackId,
                        (string) $feedback['reference_no'],
                        (string) $feedback['category'],
                        (string) $coInvestigator['email'],
                        (string) $coInvestigator['name'],
                        $actorName
                    );
                }
            }
        } catch (\Exception $e) {
            self::logWithTimestamp("Failed to send co-investigator removal notifications: " . $e->getMessage());
        }
    }

    private function addAndNotifyNewCoInvestigators(string $feedbackId, array $feedback, array $userIds, ?string $addedByUserId): void
    {
        try {
            $addedByUser = $addedByUserId ? $this->coInvestigatorRepository->getUserById($addedByUserId) : null;
            $actorName   = $addedByUser ? (string) $addedByUser['name'] : self::DEFAULT_HR_ACTOR;
            foreach ($userIds as $userId) {
                if ($feedback['assigned_to_user_id'] === $userId) {
                    continue;
                }
                $this->coInvestigatorRepository->addCoInvestigator($feedbackId, $userId, $addedByUserId);
                $coInvestigator = $this->coInvestigatorRepository->getUserById($userId);
                if ($coInvestigator && $coInvestigator['email']) {
                    $this->notificationService->notifyCoInvestigatorAdded(
                        $feedbackId,
                        (string) $feedback['reference_no'],
                        (string) $feedback['category'],
                        (string) $coInvestigator['email'],
                        (string) $coInvestigator['name'],
                        $actorName,
                        $this->buildLeadInvestigatorDisplay($feedback)
                    );
                }
            }
        } catch (\Exception $e) {
            self::logWithTimestamp("Failed to add co-investigators or send notifications: " . $e->getMessage());
        }
    }
}
