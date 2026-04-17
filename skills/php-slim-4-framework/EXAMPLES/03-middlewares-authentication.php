<?php
/**
 * Example: Middleware (Auth JWT, CORS, Error handling)
 */

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Nyholm\Psr7\Response as Psr7Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

// ============ ERROR MIDDLEWARE (must be LAST in registration) ============
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
        $status = 500;
        $code = 'INTERNAL_ERROR';
        $message = 'Internal server error';

        // Customize based on exception type
        if (method_exists($e, 'getStatus')) {
            $status = $e->getStatus();
            $code = method_exists($e, 'getCode') ? (string)$e->getCode() : 'CUSTOM_ERROR';
            $message = $e->getMessage();
        }

        $response = new Psr7Response($status);
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

// ============ AUTH MIDDLEWARE (JWT) ============
final class AuthMiddleware implements MiddlewareInterface
{
    private const BEARER_PREFIX = 'Bearer ';
    private string $jwtSecret = 'your-super-secure-secret';

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, self::BEARER_PREFIX)) {
            return $this->unauthorized('Token not provided');
        }

        $token = substr($authHeader, strlen(self::BEARER_PREFIX));

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Add user to request for access in actions
            $request = $request->withAttribute('user', (array)$decoded);
            
        } catch (Throwable $e) {
            return $this->unauthorized('Invalid token: ' . $e->getMessage());
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new Psr7Response(401);
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message,
            'code' => 'UNAUTHORIZED'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

// ============ CORS MIDDLEWARE ============
final class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins = ['http://localhost:3000', 'https://yourdomain.com'];
    private array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
    private array $allowedHeaders = ['Content-Type', 'Authorization'];

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflightResponse($request);
        }

        $response = $handler->handle($request);

        // Add CORS headers to response
        $origin = $request->getHeaderLine('Origin');
        
        if ($this->isOriginAllowed($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function preflightResponse(Request $request): Response
    {
        $response = new Psr7Response(204);  // No Content
        
        $origin = $request->getHeaderLine('Origin');
        if ($this->isOriginAllowed($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', '86400');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}

// ============ RATE LIMIT MIDDLEWARE ============
final class RateLimitMiddleware implements MiddlewareInterface
{
    private array $store = [];  // In prod: Redis or similar
    private int $maxRequests = 100;
    private int $timeWindow = 3600;  // 1 hour

    public function process(Request $request, RequestHandler $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $key = "rate_limit:$clientIp";
        
        // Get counter (in prod: from Redis)
        $count = $this->store[$key] ?? 0;
        
        if ($count >= $this->maxRequests) {
            $response = new Psr7Response(429);  // Too Many Requests
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Too many requests',
                'code' => 'RATE_LIMIT_EXCEEDED'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Increment counter
        $this->store[$key] = $count + 1;

        $response = $handler->handle($request);
        
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)($this->maxRequests - $count - 1))
            ->withHeader('X-RateLimit-Reset', (string)(time() + $this->timeWindow));
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        // Try to get real IP (behind proxy)
        if (!empty($serverParams['HTTP_CLIENT_IP'])) {
            return $serverParams['HTTP_CLIENT_IP'];
        }
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $serverParams['HTTP_X_FORWARDED_FOR'])[0];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// ============ HOW TO REGISTER IN index.php ============
/*
$app->addErrorMiddleware(true, true, true);  // ErrorMiddleware built-in

// Important order: inside out
$app->add(ErrorMiddleware::class);              // 4. Catches errors
$app->add(RateLimitMiddleware::class);          // 3. Rate limit
$app->add(CorsMiddleware::class);               // 2. CORS
$app->add(AuthMiddleware::class);               // 1. Auth (first)

// Routes + handlers
*/

// ============ GENERATE JWT TOKENS ============
/*
$token = JWT::encode(
    [
        'sub' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'iat' => time(),
        'exp' => time() + (24 * 3600)  // Expires in 24h
    ],
    'your-super-secure-secret',
    'HS256'
);
*/
