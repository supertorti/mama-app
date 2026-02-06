<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\PinAuthenticationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PinAuthController extends AbstractController
{
    /**
     * Authenticate user via PIN and issue JWT token.
     */
    #[Route('/api/pin/check', name: 'api_pin_check', methods: ['POST'])]
    public function checkPin(Request $request, PinAuthenticationService $authService): JsonResponse
    {
        /** @var array{pin?: string} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $pin = $data['pin'] ?? '';

        if ($pin === '' || !preg_match('/^\d{4}$/', $pin)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'PIN muss aus 4 Ziffern bestehen',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $authService->authenticateByPin($pin);

            # For child users, add childId for convenience
            if ($result['role'] === 'child') {
                $result['childId'] = $result['userId'];
            }

            return new JsonResponse($result);
        } catch (UnauthorizedHttpException) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Ungültige PIN',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
