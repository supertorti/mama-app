<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ChildAccessVoter;
use App\Service\PointService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/child')]
#[IsGranted('ROLE_USER')]
class ChildController extends AbstractController
{
    /**
     * Get all tasks for a specific child.
     */
    #[Route('/{childId}/tasks', name: 'api_child_tasks', methods: ['GET'])]
    public function getTasks(int $childId, UserRepository $userRepo, TaskRepository $taskRepo): JsonResponse
    {
        $child = $this->loadAndAuthorizeChild($childId, $userRepo);

        /** @var Task[] $tasks */
        $tasks = $taskRepo->findBy(['user' => $child], ['dueDate' => 'ASC']);

        $data = array_map(static fn (Task $task): array => [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'points' => $task->getPoints(),
            'dueDate' => $task->getDueDate()?->format(\DateTimeInterface::ATOM),
            'status' => $task->getStatus()->value,
        ], $tasks);

        return new JsonResponse([
            'success' => true,
            'data' => array_values($data),
        ]);
    }

    /**
     * Mark a task as completed and award points.
     */
    #[Route('/tasks/{id}/complete', name: 'api_child_task_complete', methods: ['POST'])]
    public function completeTask(
        int $id,
        Request $request,
        TaskRepository $taskRepo,
        PointService $pointService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $task = $taskRepo->find($id);

        if ($task === null) {
            throw new NotFoundHttpException('Aufgabe nicht gefunden');
        }

        # Verify the task belongs to the authenticated user
        $user = $this->getUser();
        if (!$user instanceof User || $task->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Zugriff verweigert',
            ], Response::HTTP_FORBIDDEN);
        }

        # Check if task is still open
        if ($task->getStatus() !== TaskStatus::OPEN) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Aufgabe bereits erledigt',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        # Verify PIN for extra security
        /** @var array{pin?: string} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];
        $pin = $data['pin'] ?? '';

        if ($pin === '' || !$user->verifyPin($pin)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Ungültige PIN',
            ], Response::HTTP_UNAUTHORIZED);
        }

        # Complete the task
        $task->setStatus(TaskStatus::COMPLETED);
        $task->setCompletedAt(new \DateTimeImmutable());
        $em->flush();

        # Award points
        $pointService->addPoints(
            $user,
            $task->getPoints(),
            'Aufgabe erledigt: ' . $task->getTitle(),
            $task,
        );

        return new JsonResponse([
            'success' => true,
            'data' => [
                'taskId' => $task->getId(),
                'newPoints' => $user->getPoints(),
                'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    /**
     * Get current point balance for a child.
     */
    #[Route('/{childId}/points', name: 'api_child_points', methods: ['GET'])]
    public function getPoints(int $childId, UserRepository $userRepo): JsonResponse
    {
        $child = $this->loadAndAuthorizeChild($childId, $userRepo);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'points' => $child->getPoints(),
            ],
        ]);
    }

    # Load child by ID and verify access via ChildAccessVoter
    private function loadAndAuthorizeChild(int $childId, UserRepository $userRepo): User
    {
        $child = $userRepo->find($childId);

        if ($child === null || $child->isAdmin()) {
            throw new NotFoundHttpException('Kind nicht gefunden');
        }

        $this->denyAccessUnlessGranted(ChildAccessVoter::CHILD_ACCESS, $child);

        return $child;
    }
}
