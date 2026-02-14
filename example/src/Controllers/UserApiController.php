<?php

declare(strict_types=1);

namespace Example\Controllers;

use Example\Services\UserServiceInterface;
use Melodic\Controller\ApiController;
use Melodic\Http\JsonResponse;
use Melodic\Http\Response;

class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAll();

        return $this->json(array_map(fn($u) => $u->toArray(), $users));
    }

    public function show(string $id): JsonResponse
    {
        $user = $this->userService->getById((int) $id);

        if ($user === null) {
            return $this->notFound(['error' => 'User not found']);
        }

        return $this->json($user->toArray());
    }

    public function store(): JsonResponse
    {
        $body = $this->request->body();
        $username = $body['username'] ?? null;
        $email = $body['email'] ?? null;

        if ($username === null || $email === null) {
            return $this->badRequest(['error' => 'Username and email are required']);
        }

        $id = $this->userService->create($username, $email);
        $user = $this->userService->getById($id);

        return $this->created($user->toArray(), "/api/users/{$id}");
    }

    public function destroy(string $id): Response
    {
        $deleted = $this->userService->delete((int) $id);

        if (!$deleted) {
            return $this->notFound(['error' => 'User not found']);
        }

        return $this->noContent();
    }
}
