<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PointTransaction;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Manages point transactions and user point balances.
 */
class PointService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Add points to a user and create transaction record.
     *
     * @throws \InvalidArgumentException If amount is 0
     */
    public function addPoints(User $user, int $amount, string $reason, ?Task $task = null): void
    {
        if ($amount === 0) {
            throw new \InvalidArgumentException('Point amount must not be 0');
        }

        $transaction = new PointTransaction();
        $transaction->setUser($user);
        $transaction->setAmount($amount);
        $transaction->setReason($reason);

        if ($task !== null) {
            $transaction->setTask($task);
        }

        $user->setPoints($user->getPoints() + $amount);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }
}
