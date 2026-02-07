<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/push')]
#[IsGranted('ROLE_USER')]
class PushController extends AbstractController
{
    #[Route('/vapid-public-key', name: 'api_push_vapid_public_key', methods: ['GET'])]
    public function getVapidPublicKey(PushNotificationService $pushService): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => [
                'key' => $pushService->getVapidPublicKey(),
            ],
        ]);
    }

    #[Route('/subscribe', name: 'api_push_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Nicht authentifiziert',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $endpoint = $data['endpoint'] ?? '';
        $p256dh = $data['keys']['p256dh'] ?? '';
        $auth = $data['keys']['auth'] ?? '';

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return new JsonResponse([
                'success' => false,
                'error' => 'Ungueltige Push-Subscription',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPushSubscription([
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth,
            ],
        ]);
        $user->setPushUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'data' => ['subscribed' => true],
        ]);
    }

    #[Route('/unsubscribe', name: 'api_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Nicht authentifiziert',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->setPushSubscription(null);
        $user->setPushUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'data' => ['subscribed' => false],
        ]);
    }
}
