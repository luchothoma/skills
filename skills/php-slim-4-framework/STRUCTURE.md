# Project Structure for Slim 4

## Recommended (PSR-4 + Action Classes)

```
project/
├── src/
│   ├── Application/
│   │   ├── Actions/              # One class per route
│   │   │   ├── User/
│   │   │   │   ├── ListAction.php
│   │   │   │   ├── ShowAction.php
│   │   │   │   ├── StoreAction.php
│   │   │   │   └── UpdateAction.php
│   │   │   └── Home/
│   │   │       └── HomeAction.php
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── ValidationMiddleware.php
│   │   │   └── CorsMiddleware.php
│   │   ├── Validators/           # Validation logic
│   │   │   ├── UserValidator.php
│   │   │   └── BaseValidator.php
│   │   ├── Exceptions/
│   │   │   ├── HttpException.php
│   │   │   └── ValidationException.php
│   │   └── Routes/
│   │       └── api.php            # Route definitions
│   ├── Domain/                    # Business logic (independent of Slim)
│   │   ├── Services/
│   │   │   ├── UserService.php
│   │   │   └── EmailService.php
│   │   ├── Repositories/
│   │   │   └── UserRepository.php
│   │   └── Entities/
│   │       └── User.php
│   ├── Infrastructure/            # Integration (DB, cache, etc.)
│   │   ├── Persistence/
│   │   │   └── PDOUserRepository.php
│   │   └── config.php
│   └── bootstrap.php              # DI Container setup
├── public/
│   └── index.php                  # Entry point
├── tests/
├── composer.json
└── .env (config via DotEnv)
```

## Key Differences
- **Actions**: 1 class = 1 route, `__invoke(Request $req, Response $res)` method
- **Services**: Reusable business logic
- **Repositories**: Data access (injectable)
- **Validators**: Centralized validation
- **Middleware**: Auth, CORS, rate-limit, etc.

## composer.json (Minimal Dependencies)
```json
{
  "require": {
    "slim/slim": "^4.0",
    "nyholm/psr7": "^1.5",
    "nyholm/psr7-server": "^1.0",
    "php-di/php-di": "^7.0",
    "respect/validation": "^2.2"
  }
}
```

## bootstrap.php (DI Container)
```php
<?php
use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->enableCompilation(__DIR__ . '/../var/di-cache');

// Register services
$builder->addDefinitions([
    UserRepository::class => \DI\create(\Infrastructure\Persistence\PDOUserRepository::class),
    UserService::class => \DI\autowire(),
    // ... other services
]);

return $builder->build();
```

## index.php (Entry Point)
```php
<?php
use Slim\Factory\AppFactory;

$container = require __DIR__ . '/../src/bootstrap.php';
AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware (in order)
$app->add(\Middleware\ErrorMiddleware::class);
$app->add(\Middleware\CorsMiddleware::class);
$app->add(\Middleware\AuthMiddleware::class);

// Routes
require __DIR__ . '/../src/Application/Routes/api.php';

$app->run();
```
