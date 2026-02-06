<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    # Find user by verifying plain PIN against all stored bcrypt hashes.
    # Acceptable O(n) for a small family app with few users.
    public function findByPin(string $pin): ?User
    {
        /** @var User[] $users */
        $users = $this->findAll();

        foreach ($users as $user) {
            if ($user->verifyPin($pin)) {
                return $user;
            }
        }

        return null;
    }
}
