# Key Patterns in Slim 4

This document includes quick reference snippets and detailed implementations. For complete runnable examples, see the EXAMPLES/ directory.

## Quick Patterns

### Action Classes
One class = One route. `__invoke()` method receives `(Request, Response, array $args)`

```php
final class ShowAction {
    public function __construct(private UserService $service) {}
    public function __invoke(Request $req, Response $res, array $args): Response { ... }
}
```

### Routes
Grouped with middleware. Named routes for URL generation.

```php
$app->group('/api/users', fn($g) => {
    $g->get('', ListAction::class)->setName('users.list');
    $g->post('', StoreAction::class)->setName('users.store');
})->add(AuthMiddleware::class);
```

### Middleware
Processed in reverse registration order. ErrorMiddleware always last.

```php
$app->add(ErrorMiddleware::class);    // 3. processes last
$app->add(AuthMiddleware::class);     // 2. processes second
// routes                              // 1. processes first
```

### Services
Business logic. Injected into Actions.

```php
class UserService {
    public function __construct(private UserRepository $repo) {}
    public function create(array $data): array { ... }
}
```

### Repositories
Data access. Interface + implementation (PDO, etc).

```php
interface UserRepository { /*methods*/ }
class PDOUserRepository implements UserRepository { /*impl*/ }
```

### Validators
Centralized rules. Returns null or array of errors.

```php
public function validateCreate(array $data): ?array {
    return $this->validate($data, [
        'email' => v::email(),
        'name' => v::stringType()->length(3, 100),
    ]);
}
```

### Request/Response
PSR-7. Never use `$_POST` or `$_GET` directly.

```php
$data = $request->getParsedBody();        // POST data
$id = $args['id'] ?? null;                // route param
$user = $request->getAttribute('user');   // added by middleware
return $response->withStatus(200)->write(json_encode($data));
```

## Detailed Patterns

## 1. Action Classes (Core Pattern)

```php
<?php
namespace App\Application\Actions\User;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Domain\Services\UserService;

final class ListAction
{
    public function __construct(
        private UserService $userService
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $users = $this->userService->getAll();
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200)
            ->write(json_encode([
                'success' => true,
                'data' => $users,
                'meta' => ['count' => count($users)]
            ]));
    }
}
```

## 2. Routing with Groups

```php
<?php
// src/Application/Routes/api.php
use Slim\App;
use App\Application\Actions\User\{ListAction, ShowAction, StoreAction};
use App\Application\Middleware\AuthMiddleware;

return function(App $app) {
    // Group /api/users
    $app->group('/api/users', function ($group) {
        $group->get('', ListAction::class)->setName('users.list');
        $group->get('/{id}', ShowAction::class)->setName('users.show');
        $group->post('', StoreAction::class)->setName('users.store');
    })->add(AuthMiddleware::class);  // Middleware on group
};
```

## 3. Custom Middleware

```php
<?php
namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $request->getHeaderLine('Authorization');
        
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return $this->unauthorized();
        }

        // Verify token (JWT, etc.)
        $user = verifyJWT(substr($token, 7));
        
        if (!$user) {
            return $this->unauthorized();
        }

        // Pass user to next middleware/action
        return $handler->handle(
            $request->withAttribute('user', $user)
        );
    }

    private function unauthorized(): Response
    {
        $response = new \Nyholm\Psr7\Response(401);
        return $response->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Unauthorized']));
    }
}
```

## 4. Validation in Actions

```php
<?php
namespace App\Application\Actions\User;

use App\Application\Validators\UserValidator;

final class StoreAction
{
    public function __construct(
        private UserValidator $validator,
        private UserService $service
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate
        $errors = $this->validator->validate($data);
        if ($errors) {
            return $response
                ->withStatus(422)
                ->write(json_encode(['success' => false, 'errors' => $errors]));
        }

        // Create user
        $user = $this->service->create($data);
        
        return $response
            ->withStatus(201)
            ->write(json_encode(['success' => true, 'data' => $user]));
    }
}
```

## 5. Reusable Validator

```php
<?php
namespace App\Application\Validators;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class UserValidator
{
    public function validate(array $data): ?array
    {
        try {
            v::key('email', v::email())
             ->key('name', v::stringType()->length(2, 100))
             ->key('password', v::stringType()->length(8))
             ->assert($data);
            
            return null;  // No errors
        } catch (NestedValidationException $e) {
            return $e->getMessages();
        }
    }
}
```

## 6. Global Error Handling

```php
<?php
namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Throwable;

final class ErrorMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(Throwable $e): Response
    {
        $status = method_exists($e, 'getStatus') ? $e->getStatus() : 500;
        $response = new \Nyholm\Psr7\Response($status);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]));
    }
}
```

## 7. Request Data Extraction

```php
<?php
// GET/POST
$data = $request->getParsedBody();  // POST data
$query = $request->getQueryParams();  // ?key=value
$id = $args['id'] ?? null;  // Route: /users/{id}
$headers = $request->getHeaders();
$user = $request->getAttribute('user');  // Added by middleware
```

## 8. Consistent Responses

```php
<?php
// Success
return $response
    ->withStatus(200)
    ->withHeader('Content-Type', 'application/json')
    ->write(json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['timestamp' => time()]
    ]));

// Error
return $response
    ->withStatus(400)
    ->withHeader('Content-Type', 'application/json')
    ->write(json_encode([
        'success' => false,
        'error' => 'Bad request',
        'code' => 'INVALID_INPUT'
    ]));
```

## 9. Dependency Injection

```php
<?php
// In bootstrap.php
$definitions = [
    // Interface -> Implementation
    UserRepository::class => \DI\create(\Infrastructure\Persistence\PDOUserRepository::class),
    
    // Auto-wiring (automatic)
    UserService::class => \DI\autowire(),
    
    // Factory (callables)
    'pdo' => function() {
        return new PDO('mysql:host=localhost', 'user', 'pass');
    },
];

// In Actions (automatic via Constructor)
class ListAction
{
    public function __construct(
        private UserService $service,  // Auto-injected
        private UserRepository $repo
    ) {}
}
```

## 10. Testing an Action

```php
<?php
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr7Factory;

class ListActionTest extends TestCase
{
    public function testListUsers()
    {
        $userService = $this->createMock(UserService::class);
        $userService->method('getAll')->willReturn([
            ['id' => 1, 'name' => 'John']
        ]);

        $action = new ListAction($userService);
        
        $factory = new Psr7Factory();
        $request = $factory->createServerRequest('GET', '/users');
        $response = $factory->createResponse(200);

        $result = $action($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
    }
}
```
