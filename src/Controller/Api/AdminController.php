<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    /**
     * Get all children with their point balances.
     */
    #[Route('/children', name: 'api_admin_children', methods: ['GET'])]
    public function getChildren(UserRepository $userRepo): JsonResponse
    {
        /** @var User[] $children */
        $children = $userRepo->findBy(['isAdmin' => false]);

        $data = array_map(static fn (User $child): array => [
            'id' => $child->getId(),
            'name' => $child->getName(),
            'points' => $child->getPoints(),
        ], $children);

        return new JsonResponse([
            'success' => true,
            'data' => array_values($data),
        ]);
    }

    /**
     * Get all tasks (admin overview).
     */
    #[Route('/tasks', name: 'api_admin_tasks_list', methods: ['GET'])]
    public function getTasks(TaskRepository $taskRepo): JsonResponse
    {
        /** @var Task[] $tasks */
        $tasks = $taskRepo->findAll();

        $data = array_map(static fn (Task $task): array => [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'childName' => $task->getUser()?->getName(),
            'childId' => $task->getUser()?->getId(),
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
     * Create a new task for a child.
     */
    #[Route('/tasks', name: 'api_admin_tasks_create', methods: ['POST'])]
    public function createTask(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        PushNotificationService $pushService,
    ): JsonResponse {
        /** @var array{childId?: int, title?: string, description?: string, points?: int, dueDate?: string} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $errors = $this->validateTaskData($data, $userRepo);

        if ($errors !== []) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $child */
        $child = $userRepo->find($data['childId'] ?? 0);

        $task = new Task();
        $task->setUser($child);
        $task->setTitle($data['title'] ?? '');
        $task->setDescription($data['description'] ?? null);
        $task->setPoints($data['points'] ?? 0);
        $task->setDueDate(new \DateTimeImmutable($data['dueDate'] ?? 'now'));

        $em->persist($task);
        $em->flush();

        try {
            $pushService->sendToUser($child, [
                'title' => 'Neue Aufgabe!',
                'body' => $task->getTitle(),
                'url' => '/',
            ]);
        } catch (\Throwable) {
            // Push is best-effort — never fail task creation
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus()->value,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete a task.
     */
    #[Route('/tasks/{id}', name: 'api_admin_tasks_delete', methods: ['DELETE'])]
    public function deleteTask(
        int $id,
        TaskRepository $taskRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $task = $taskRepo->find($id);

        if ($task === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Aufgabe nicht gefunden',
            ], Response::HTTP_NOT_FOUND);
        }

        // Nullify task reference on related PointTransactions to preserve audit trail
        foreach ($task->getPointTransactions() as $transaction) {
            $transaction->setTask(null);
        }

        $em->remove($task);
        $em->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    /**
     * @param array{childId?: int, title?: string, description?: string, points?: int, dueDate?: string} $data
     * @return array<string, string>
     */
    private function validateTaskData(array $data, UserRepository $userRepo): array
    {
        $errors = [];

        # Validate childId
        $childId = $data['childId'] ?? null;
        if ($childId === null) {
            $errors['childId'] = 'childId ist erforderlich';
        } else {
            $child = $userRepo->find($childId);
            if ($child === null) {
                $errors['childId'] = 'Kind nicht gefunden';
            } elseif ($child->isAdmin()) {
                $errors['childId'] = 'User ist kein Kind';
            }
        }

        # Validate title
        $title = $data['title'] ?? '';
        if ($title === '') {
            $errors['title'] = 'Titel darf nicht leer sein';
        } elseif (strlen($title) > 255) {
            $errors['title'] = 'Titel darf maximal 255 Zeichen lang sein';
        }

        # Validate points
        $points = $data['points'] ?? null;
        if ($points === null) {
            $errors['points'] = 'Punkte sind erforderlich';
        } elseif ($points < 1) {
            $errors['points'] = 'Punkte müssen mindestens 1 sein';
        }

        # Validate dueDate
        $dueDate = $data['dueDate'] ?? '';
        if ($dueDate === '') {
            $errors['dueDate'] = 'Fälligkeitsdatum ist erforderlich';
        } else {
            try {
                new \DateTimeImmutable($dueDate);
            } catch (\Exception) {
                $errors['dueDate'] = 'Ungültiges Datumsformat';
            }
        }

        return $errors;
    }
}
