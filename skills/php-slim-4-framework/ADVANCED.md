# Advanced Slim 4 Topics

This document covers advanced concepts not covered in the basic patterns: Security, Logging, API Versioning, and Caching.

## 🔒 Security

### JWT Authentication
```php
<?php
// src/Application/Middleware/JwtAuthMiddleware.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    private string $secret = 'your-secret-key';

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Missing or invalid token');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $request = $request->withAttribute('user', (array)$decoded);
        } catch (Throwable $e) {
            return $this->unauthorized('Invalid token: ' . $e->getMessage());
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new Nyholm\Psr7\Response(401);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

### CSRF Protection
```php
<?php
// src/Application/Middleware/CsrfMiddleware.php
final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $csrfToken = $request->getHeaderLine('X-CSRF-Token');
            $sessionToken = $_SESSION['csrf_token'] ?? '';

            if (!$csrfToken || !hash_equals($sessionToken, $csrfToken)) {
                $response = new Nyholm\Psr7\Response(403);
                $response->getBody()->write(json_encode(['error' => 'CSRF token mismatch']));
                return $response->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }
}
```

### Input Sanitization
```php
<?php
// src/Application/Middleware/SanitizationMiddleware.php
final class SanitizationMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $parsedBody = $request->getParsedBody();

        if ($parsedBody) {
            $sanitized = $this->sanitizeArray($parsedBody);
            $request = $request->withParsedBody($sanitized);
        }

        return $handler->handle($request);
    }

    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
```

## 📝 Logging

### Monolog Integration
```php
<?php
// src/Infrastructure/Logging/LoggerFactory.php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\UidProcessor;

final class LoggerFactory
{
    public static function create(string $name = 'app'): Logger
    {
        $logger = new Logger($name);

        // Add unique ID to each log entry
        $logger->pushProcessor(new UidProcessor());

        // Rotating file handler (daily rotation, keep 30 days)
        $fileHandler = new RotatingFileHandler(
            __DIR__ . '/../../../logs/app.log',
            30,
            Logger::DEBUG
        );

        // Error handler for errors and above
        $errorHandler = new StreamHandler(
            __DIR__ . '/../../../logs/errors.log',
            Logger::ERROR
        );

        $logger->pushHandler($fileHandler);
        $logger->pushHandler($errorHandler);

        return $logger;
    }
}
```

### Logging Middleware
```php
<?php
// src/Application/Middleware/LoggingMiddleware.php
use Psr\Log\LoggerInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $start = microtime(true);
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $this->logger->info("Request: $method $path");

        try {
            $response = $handler->handle($request);
            $status = $response->getStatusCode();
            $duration = round((microtime(true) - $start) * 1000, 2);

            $this->logger->info("Response: $status in {$duration}ms");

            return $response;
        } catch (Throwable $e) {
            $this->logger->error("Exception: " . $e->getMessage(), [
                'method' => $method,
                'path' => $path,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
```

### Structured Logging in Actions
```php
<?php
// Example in an Action
final class CreateUserAction
{
    public function __construct(
        private UserService $service,
        private LoggerInterface $logger
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $this->logger->info('Creating user', [
            'email' => $data['email'] ?? 'unknown',
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);

        try {
            $user = $this->service->create($data);

            $this->logger->info('User created successfully', [
                'user_id' => $user['id'],
                'email' => $user['email']
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $user
            ]));

            return $response->withStatus(201);
        } catch (Throwable $e) {
            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}
```

## 🏷️ API Versioning

### URL Path Versioning
```php
<?php
// public/index.php
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    // V1 routes
    $group->get('/users', V1\User\ListAction::class);
    $group->post('/users', V1\User\StoreAction::class);
});

$app->group('/api/v2', function (RouteCollectorProxy $group) {
    // V2 routes with new features
    $group->get('/users', V2\User\ListAction::class);
    $group->post('/users', V2\User\StoreAction::class);
    $group->get('/users/{id}/profile', V2\User\ProfileAction::class); // New endpoint
});
```

### Accept Header Versioning
```php
<?php
// src/Application/Middleware/VersioningMiddleware.php
final class VersioningMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $accept = $request->getHeaderLine('Accept');

        // Extract version from Accept header: application/vnd.api.v2+json
        if (preg_match('/application\/vnd\.api\.v(\d+)\+json/', $accept, $matches)) {
            $version = $matches[1];
            $request = $request->withAttribute('api_version', $version);
        }

        return $handler->handle($request);
    }
}

// Usage in routes
$app->get('/users', function (Request $request, Response $response) {
    $version = $request->getAttribute('api_version', '1');

    if ($version === '2') {
        // V2 logic
        return $response->withJson(['version' => '2', 'data' => []]);
    }

    // V1 logic
    return $response->withJson(['version' => '1', 'data' => []]);
})->add(VersioningMiddleware::class);
```

### Version Negotiation in Actions
```php
<?php
// src/Application/Actions/User/ListAction.php
final class ListAction
{
    public function __construct(private UserService $service) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $version = $request->getAttribute('api_version', '1');

        if ($version === '2') {
            // V2: Include additional fields
            $users = $this->service->getAllWithProfiles();
            $data = array_map(fn($user) => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'profile' => $user['profile'] ?? null, // New in V2
            ], $users);
        } else {
            // V1: Basic fields only
            $users = $this->service->getAll();
            $data = array_map(fn($user) => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
            ], $users);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data,
            'version' => $version
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

## 💾 Caching

### Response Caching with PSR-6
```php
<?php
// src/Infrastructure/Cache/CacheFactory.php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Cache\CacheItemPoolInterface;

final class CacheFactory
{
    public static function create(): CacheItemPoolInterface
    {
        return new FilesystemAdapter(
            namespace: 'slim_app',
            defaultLifetime: 3600, // 1 hour
            directory: __DIR__ . '/../../../var/cache'
        );
    }
}
```

### HTTP Response Caching
```php
<?php
// src/Application/Middleware/CacheMiddleware.php
use Psr\Cache\CacheItemPoolInterface;

final class CacheMiddleware implements MiddlewareInterface
{
    public function __construct(private CacheItemPoolInterface $cache) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Only cache GET requests
        if ($method !== 'GET') {
            return $handler->handle($request);
        }

        $cacheKey = 'response_' . md5($path . serialize($request->getQueryParams()));

        $cachedResponse = $this->cache->getItem($cacheKey);
        if ($cachedResponse->isHit()) {
            // Return cached response
            $data = $cachedResponse->get();
            $response = new Nyholm\Psr7\Response(200);
            $response->getBody()->write($data);
            return $response->withHeader('X-Cache', 'HIT');
        }

        // Generate fresh response
        $response = $handler->handle($request);

        // Cache successful GET responses for 5 minutes
        if ($response->getStatusCode() === 200) {
            $cachedResponse->set((string)$response->getBody());
            $cachedResponse->expiresAfter(300); // 5 minutes
            $this->cache->save($cachedResponse);
        }

        return $response->withHeader('X-Cache', 'MISS');
    }
}
```

### Database Query Caching
```php
<?php
// src/Domain/Repositories/CachedUserRepository.php
use Psr\Cache\CacheItemPoolInterface;

final class CachedUserRepository implements UserRepository
{
    public function __construct(
        private UserRepository $repository,
        private CacheItemPoolInterface $cache
    ) {}

    public function findById(int $id): ?array
    {
        $cacheKey = "user_$id";
        $cachedUser = $this->cache->getItem($cacheKey);

        if ($cachedUser->isHit()) {
            return $cachedUser->get();
        }

        $user = $this->repository->findById($id);

        if ($user) {
            $cachedUser->set($user);
            $cachedUser->expiresAfter(600); // 10 minutes
            $this->cache->save($cachedUser);
        }

        return $user;
    }

    public function save(array $user): array
    {
        $saved = $this->repository->save($user);

        // Invalidate cache
        $this->cache->deleteItem("user_{$saved['id']}");

        return $saved;
    }

    // Implement other methods by delegating to $this->repository
    // and adding appropriate caching logic
}
```

### Cache Invalidation Strategies
```php
<?php
// src/Domain/Services/CacheInvalidationService.php
final class CacheInvalidationService
{
    public function __construct(private CacheItemPoolInterface $cache) {}

    public function invalidateUser(int $userId): void
    {
        $this->cache->deleteItem("user_$userId");
        $this->cache->deleteItem("users_list"); // Invalidate list cache
    }

    public function invalidateAllUsers(): void
    {
        // For Redis, you could use pattern deletion
        // For filesystem cache, you might need to clear directory
        $this->cache->clear();
    }

    public function warmCache(): void
    {
        // Pre-populate cache with frequently accessed data
        // This could be called during application startup
    }
}
```

## 🚀 Production Considerations

### Environment-Specific Configuration
```php
<?php
// config/config.php
return [
    'cache' => [
        'enabled' => getenv('CACHE_ENABLED') === 'true',
        'ttl' => (int)getenv('CACHE_TTL') ?: 3600,
    ],
    'logging' => [
        'level' => getenv('LOG_LEVEL') ?: 'INFO',
        'file' => getenv('LOG_FILE') ?: '/var/log/app.log',
    ],
    'security' => [
        'jwt_secret' => getenv('JWT_SECRET') ?: 'default-secret-change-in-prod',
        'csrf_enabled' => getenv('CSRF_ENABLED') !== 'false',
    ],
];
```

### Health Check Endpoint
```php
<?php
// public/index.php
$app->get('/health', function (Request $request, Response $response) {
    // Check database connection
    try {
        $pdo = $container->get(PDO::class);
        $pdo->query('SELECT 1');
        $dbStatus = 'ok';
    } catch (Throwable $e) {
        $dbStatus = 'error: ' . $e->getMessage();
    }

    // Check cache
    try {
        $cache = $container->get(CacheItemPoolInterface::class);
        $cache->getItem('health_check')->set('ok')->expiresAfter(60);
        $cache->save($cache->getItem('health_check'));
        $cacheStatus = 'ok';
    } catch (Throwable $e) {
        $cacheStatus = 'error: ' . $e->getMessage();
    }

    $status = ($dbStatus === 'ok' && $cacheStatus === 'ok') ? 200 : 503;

    $response->getBody()->write(json_encode([
        'status' => $status === 200 ? 'healthy' : 'unhealthy',
        'checks' => [
            'database' => $dbStatus,
            'cache' => $cacheStatus,
        ],
        'timestamp' => time(),
    ]));

    return $response->withStatus($status);
});
```

These advanced patterns help build production-ready Slim 4 applications with proper security, observability, scalability, and maintainability.