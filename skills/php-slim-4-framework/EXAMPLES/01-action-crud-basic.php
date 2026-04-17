<?php
/**
 * Example: Basic CRUD for Users
 * Structure: One Action class per operation
 */

namespace App\Application\Actions\User;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Domain\Services\UserService;
use Nyholm\Psr7\Response as Psr7Response;

// ============ LIST ============
final class ListAction
{
    public function __construct(private UserService $service) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $page = $request->getQueryParams()['page'] ?? 1;
        $limit = $request->getQueryParams()['limit'] ?? 10;

        $users = $this->service->paginate($page, $limit);

        return $this->json($response, 200, [
            'success' => true,
            'data' => $users,
            'meta' => ['page' => $page, 'limit' => $limit]
        ]);
    }

    private function json(Response $response, int $status, array $data): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

// ============ SHOW ============
final class ShowAction
{
    public function __construct(private UserService $service) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        
        if (!$id) {
            return $this->error($response, 400, 'ID required');
        }

        $user = $this->service->getById($id);
        
        if (!$user) {
            return $this->error($response, 404, 'User not found');
        }

        return $this->json($response, 200, [
            'success' => true,
            'data' => $user
        ]);
    }

    private function json(Response $response, int $status, array $data): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function error(Response $response, int $status, string $message): Response
    {
        return $this->json($response, $status, [
            'success' => false,
            'error' => $message
        ]);
    }
}

// ============ STORE ============
final class StoreAction
{
    public function __construct(
        private UserService $service,
        private UserValidator $validator
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        // Validate input
        $errors = $this->validator->validateCreate($data);
        if ($errors) {
            return $this->error($response, 422, 'Invalid data', $errors);
        }

        // Create user
        $user = $this->service->create($data);

        return $this->json($response, 201, [
            'success' => true,
            'data' => $user,
            'message' => 'User created'
        ]);
    }

    private function json(Response $response, int $status, array $data): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function error(Response $response, int $status, string $message, ?array $errors = null): Response
    {
        $body = ['success' => false, 'error' => $message];
        if ($errors) $body['errors'] = $errors;
        return $this->json($response, $status, $body);
    }
}

// ============ UPDATE ============
final class UpdateAction
{
    public function __construct(
        private UserService $service,
        private UserValidator $validator
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            return $this->error($response, 400, 'ID required');
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validator->validateUpdate($data);
        
        if ($errors) {
            return $this->error($response, 422, 'Invalid data', $errors);
        }

        $user = $this->service->update($id, $data);
        
        if (!$user) {
            return $this->error($response, 404, 'User not found');
        }

        return $this->json($response, 200, [
            'success' => true,
            'data' => $user,
            'message' => 'User updated'
        ]);
    }

    private function json(Response $response, int $status, array $data): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function error(Response $response, int $status, string $message, ?array $errors = null): Response
    {
        $body = ['success' => false, 'error' => $message];
        if ($errors) $body['errors'] = $errors;
        return $this->json($response, $status, $body);
    }
}

// ============ DELETE ============
final class DeleteAction
{
    public function __construct(private UserService $service) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        
        if (!$id) {
            return $this->error($response, 400, 'ID required');
        }

        $deleted = $this->service->delete($id);
        
        if (!$deleted) {
            return $this->error($response, 404, 'User not found');
        }

        return $this->json($response, 200, [
            'success' => true,
            'message' => 'User deleted'
        ]);
    }

    private function json(Response $response, int $status, array $data): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function error(Response $response, int $status, string $message): Response
    {
        return $this->json($response, $status, [
            'success' => false,
            'error' => $message
        ]);
    }
}

// ============ ROUTES (routes.php) ============
/*
$app->group('/api/users', function ($group) {
    $group->get('', ListAction::class)->setName('users.list');
    $group->get('/{id}', ShowAction::class)->setName('users.show');
    $group->post('', StoreAction::class)->setName('users.store');
    $group->put('/{id}', UpdateAction::class)->setName('users.update');
    $group->delete('/{id}', DeleteAction::class)->setName('users.delete');
})->add(AuthMiddleware::class);
*/
