<?php
/**
 * Example: bootstrap.php + index.php (Complete Configuration)
 */

// ============ bootstrap.php ============
<?php
// src/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use App\Domain\Services\UserService;
use App\Domain\Services\ProductService;
use App\Domain\Repositories\UserRepository;
use App\Domain\Repositories\ProductRepository;
use App\Infrastructure\Persistence\PDOUserRepository;
use App\Infrastructure\Persistence\PDOProductRepository;
use App\Application\Validators\UserValidator;
use App\Application\Validators\ProductValidator;

$builder = new ContainerBuilder();

// Enable cache in production
if (getenv('APP_ENV') === 'production') {
    $builder->enableCompilation(__DIR__ . '/../var/di-cache');
}

$builder->addDefinitions([
    // ============ CONFIGURATION ============
    'pdo' => function() {
        $dsn = getenv('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../var/db.sqlite';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';
        
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    },

    // ============ REPOSITORIES ============
    UserRepository::class => \DI\create(PDOUserRepository::class),
    ProductRepository::class => \DI\create(PDOProductRepository::class),

    // ============ SERVICES ============
    UserService::class => \DI\autowire(),
    ProductService::class => \DI\autowire(),

    // ============ VALIDATORS ============
    UserValidator::class => \DI\autowire(),
    ProductValidator::class => \DI\autowire(),

    // ============ ACTIONS (Optional: auto-wiring) ============
    // No need to register if using autowire:
    // Slim will auto-inject constructor dependencies
]);

return $builder->build();

// ============ index.php ============
<?php
// public/index.php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use App\Application\Middleware\{ErrorMiddleware as CustomErrorMiddleware, AuthMiddleware, CorsMiddleware, RateLimitMiddleware};

// DI Container
$container = require __DIR__ . '/../src/bootstrap.php';
AppFactory::setContainer($container);

// Create app
$app = AppFactory::create();

// ============ MIDDLEWARE (IMPORTANT ORDER) ============
// Processed in REVERSE order (bottom to top when request arrives)

// 4. Error handling (first in registration = last in processing)
$errorMiddleware = $app->addErrorMiddleware(
    (bool)getenv('APP_DEBUG'),  // displayErrorDetails
    true,                        // logErrors
    true                         // logErrorDetails
);
$errorMiddleware->setDefaultErrorHandler(new Slim\Handlers\ErrorHandler(
    $app->getCallableResolver(),
    $app->getResponseFactory()
));

// 3. Rate limiting
$app->add(RateLimitMiddleware::class);

// 2. CORS
$app->add(CorsMiddleware::class);

// 1. Auth (first in processing = last in registration)
// Do NOT add globally if only some endpoints need it
// In that case, add to specific group

// ============ ROUTES ============

// Health check (no auth)
$app->get('/health', function($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
})->setName('health');

// Auth (login - no protection)
$app->post('/api/auth/login', \App\Application\Actions\Auth\LoginAction::class)
    ->setName('auth.login');

// API (all protected with auth)
$app->group('/api', function ($group) {
    
    // ---- USERS ----
    $group->group('/users', function ($users) {
        $users->get('', \App\Application\Actions\User\ListAction::class)
            ->setName('users.list');
        $users->get('/{id}', \App\Application\Actions\User\ShowAction::class)
            ->setName('users.show');
        $users->post('', \App\Application\Actions\User\StoreAction::class)
            ->setName('users.store');
        $users->put('/{id}', \App\Application\Actions\User\UpdateAction::class)
            ->setName('users.update');
        $users->delete('/{id}', \App\Application\Actions\User\DeleteAction::class)
            ->setName('users.delete');
    });

    // ---- PRODUCTS ----
    $group->group('/products', function ($products) {
        $products->get('', \App\Application\Actions\Product\ListAction::class)
            ->setName('products.list');
        $products->get('/{id}', \App\Application\Actions\Product\ShowAction::class)
            ->setName('products.show');
        $products->post('', \App\Application\Actions\Product\StoreAction::class)
            ->setName('products.store');
        $products->put('/{id}', \App\Application\Actions\Product\UpdateAction::class)
            ->setName('products.update');
        $products->delete('/{id}', \App\Application\Actions\Product\DeleteAction::class)
            ->setName('products.delete');
    });

})->add(AuthMiddleware::class);  // Entire API requires auth

// ============ NOT FOUND ROUTES (404) ============
// Customize 404 response if desired:
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{paths:.+}', function($request, $response) {
    $response->getBody()->write(json_encode([
        'success' => false,
        'error' => 'Route not found',
        'code' => 'NOT_FOUND'
    ]));
    return $response
        ->withStatus(404)
        ->withHeader('Content-Type', 'application/json');
});

// ============ RUN ============
$app->run();

// ============ .env (EXAMPLE) ============
/*
APP_ENV=development
APP_DEBUG=true

DB_DSN=mysql:host=localhost;dbname=myapp
DB_USER=root
DB_PASS=password

JWT_SECRET=your-super-secure-secret-change-in-production

CORS_ORIGINS=http://localhost:3000,https://yourdomain.com
*/

// ============ composer.json ============
/*
{
  "name": "myapp/api",
  "require": {
    "php": "^8.0",
    "slim/slim": "^4.12",
    "nyholm/psr7": "^1.5",
    "nyholm/psr7-server": "^1.0",
    "php-di/php-di": "^7.0",
    "respect/validation": "^2.2",
    "firebase/php-jwt": "^6.0",
    "vlucas/phpdotenv": "^5.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
*/
