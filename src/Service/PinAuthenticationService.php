<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Handles PIN-based authentication and JWT token generation.
 */
class PinAuthenticationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /**
     * Authenticate user by PIN and generate JWT token.
     *
     * @throws UnauthorizedHttpException If PIN is invalid
     * @return array{success: true, token: string, role: string, userId: int}
     */
    public function authenticateByPin(string $pin): array
    {
        $user = $this->userRepository->findByPin($pin);

        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid PIN');
        }

        $userId = $user->getId();

        if ($userId === null) {
            throw new \LogicException('User ID must not be null after database load');
        }

        $token = $this->jwtManager->create($user);

        return [
            'success' => true,
            'token' => $token,
            'role' => $user->isAdmin() ? 'admin' : 'child',
            'userId' => $userId,
        ];
    }
}
