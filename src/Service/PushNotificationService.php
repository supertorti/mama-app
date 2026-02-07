<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $vapidSubject,
        private readonly string $vapidPublicKey,
        private readonly string $vapidPrivateKey,
    ) {
    }

    /**
     * @param array{title: string, body: string, url?: string} $payload
     */
    public function sendToUser(User $user, array $payload): bool
    {
        $subscriptionData = $user->getPushSubscription();
        if ($subscriptionData === null) {
            return false;
        }

        $auth = [
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ];

        $webPush = new WebPush($auth);

        $subscription = Subscription::create([
            'endpoint' => $subscriptionData['endpoint'],
            'publicKey' => $subscriptionData['keys']['p256dh'],
            'authToken' => $subscriptionData['keys']['auth'],
        ]);

        $webPush->queueNotification($subscription, json_encode($payload, JSON_THROW_ON_ERROR));

        /** @var MessageSentReport $report */
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                return true;
            }

            $statusCode = $report->getResponse()?->getStatusCode();
            if ($statusCode === 404 || $statusCode === 410) {
                $this->logger->info('Push subscription expired for user {userId}, clearing.', [
                    'userId' => $user->getId(),
                ]);
                $user->setPushSubscription(null);
                $user->setPushUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
            } else {
                $this->logger->warning('Push notification failed for user {userId}: {reason}', [
                    'userId' => $user->getId(),
                    'reason' => $report->getReason(),
                ]);
            }

            return false;
        }

        return false;
    }

    public function getVapidPublicKey(): string
    {
        return $this->vapidPublicKey;
    }
}
