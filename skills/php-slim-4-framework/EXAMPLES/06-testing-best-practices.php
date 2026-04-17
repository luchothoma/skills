<?php
/**
 * Example: Testing + Best Practices
 */

// ============ PHPUNIT TEST ============
<?php
// tests/Application/Actions/User/ListActionTest.php

namespace App\Tests\Application\Actions\User;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr7Factory;
use App\Application\Actions\User\ListAction;
use App\Domain\Services\UserService;

final class ListActionTest extends TestCase
{
    private Psr7Factory $factory;
    private UserService $userService;
    private ListAction $action;

    protected function setUp(): void
    {
        $this->factory = new Psr7Factory();
        $this->userService = $this->createMock(UserService::class);
        $this->action = new ListAction($this->userService);
    }

    public function testListUsersSuccess(): void
    {
        // Mock data
        $mockUsers = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        // Set expectation
        $this->userService
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($mockUsers);

        // Create request & response
        $request = $this->factory->createServerRequest('GET', '/api/users');
        $response = $this->factory->createResponse(200);

        // Invoke action
        $result = ($this->action)($request, $response);

        // Assert
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));

        // Parse response body
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);
    }

    public function testListUsersEmpty(): void
    {
        $this->userService
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);

        $request = $this->factory->createServerRequest('GET', '/api/users');
        $response = $this->factory->createResponse(200);

        $result = ($this->action)($request, $response);

        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertEmpty($body['data']);
    }
}

// ============ TEST VALIDATOR ============
<?php
// tests/Application/Validators/UserValidatorTest.php

namespace App\Tests\Application\Validators;

use PHPUnit\Framework\TestCase;
use App\Application\Validators\UserValidator;

final class UserValidatorTest extends TestCase
{
    private UserValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UserValidator();
    }

    public function testValidCreateWithValidData(): void
    {
        $data = [
            'email' => 'test@example.com',
            'name' => 'John Doe',
            'password' => 'securepass123'
        ];

        $errors = $this->validator->validateCreate($data);

        $this->assertNull($errors);
    }

    public function testValidCreateWithInvalidEmail(): void
    {
        $data = [
            'email' => 'invalid-email',
            'name' => 'John Doe',
            'password' => 'securepass123'
        ];

        $errors = $this->validator->validateCreate($data);

        $this->assertNotNull($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidCreateWithShortName(): void
    {
        $data = [
            'email' => 'test@example.com',
            'name' => 'Jo',  // < 3 chars
            'password' => 'securepass123'
        ];

        $errors = $this->validator->validateCreate($data);

        $this->assertNotNull($errors);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testValidCreateWithShortPassword(): void
    {
        $data = [
            'email' => 'test@example.com',
            'name' => 'John Doe',
            'password' => 'short'  // < 8 chars
        ];

        $errors = $this->validator->validateCreate($data);

        $this->assertNotNull($errors);
        $this->assertArrayHasKey('password', $errors);
    }
}

// ============ TEST REPOSITORY ============
<?php
// tests/Infrastructure/Persistence/PDOUserRepositoryTest.php

namespace App\Tests\Infrastructure\Persistence;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\PDOUserRepository;

final class PDOUserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PDOUserRepository $repository;

    protected function setUp(): void
    {
        // SQLite in-memory for tests
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create table
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                password TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ');

        $this->repository = new PDOUserRepository($this->pdo);
    }

    public function testInsertUser(): void
    {
        $data = [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'password' => password_hash('pass123', PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->repository->insert($data);

        $this->assertIsArray($result);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals(1, $result['id']);
    }

    public function testFindById(): void
    {
        // Insert
        $this->pdo->exec("
            INSERT INTO users (email, name, password, created_at)
            VALUES ('john@example.com', 'John Doe', 'hashed', '2026-01-01 10:00:00')
        ");

        // Find
        $user = $this->repository->findById(1);

        $this->assertNotNull($user);
        $this->assertEquals('john@example.com', $user['email']);
    }

    public function testFindByIdNotFound(): void
    {
        $user = $this->repository->findById(999);

        $this->assertNull($user);
    }

    public function testFindByEmail(): void
    {
        $this->pdo->exec("
            INSERT INTO users (email, name, password, created_at)
            VALUES ('jane@example.com', 'Jane Doe', 'hashed', '2026-01-01 10:00:00')
        ");

        $user = $this->repository->findByEmail('jane@example.com');

        $this->assertNotNull($user);
        $this->assertEquals('Jane Doe', $user['name']);
    }

    public function testDeleteById(): void
    {
        $this->pdo->exec("
            INSERT INTO users (email, name, password, created_at)
            VALUES ('del@example.com', 'Delete Me', 'hashed', '2026-01-01 10:00:00')
        ");

        $deleted = $this->repository->deleteById(1);

        $this->assertTrue($deleted);
        $this->assertNull($this->repository->findById(1));
    }
}

// ============ BEST PRACTICES ============
/*

1. FOLDER STRUCTURE
   - src/
     - Application/     (HTTP - Actions, Middleware, Validators)
     - Domain/          (Business logic - Services, Repositories, Entities)
     - Infrastructure/  (Data access - PDORepository, etc)
   - tests/            (Tests parallel to src structure)

2. DEPENDENCIES
   - ALWAYS inject in constructor (never "new" inside methods)
   - Use interfaces for Repositories
   - Container handles creation automatically (autowire)

3. JSON RESPONSES
   Success:
   {
     "success": true,
     "data": {...},
     "meta": {...}           // optional
   }
   
   Error:
   {
     "success": false,
     "error": "message",
     "code": "ERROR_CODE",
     "errors": {...}         // only if validation
   }

4. HTTP STATUS CODES
   200 - OK
   201 - Created
   204 - No Content
   400 - Bad Request
   401 - Unauthorized
   403 - Forbidden
   404 - Not Found
   409 - Conflict
   422 - Unprocessable Entity (validation)
   429 - Too Many Requests
   500 - Internal Server Error

5. VALIDATION
   - Use Respect\Validation or symfony/validator
   - Centralize rules in Validator classes
   - Return array of errors per field

6. AUTHENTICATION
   - JWT (Firebase/php-jwt) preferred
   - Bearer token in Authorization header
   - Middleware AuthMiddleware injects into request attribute

7. TESTING
   - Unit tests for services and validators
   - Integration tests for repositories (real/SQLite DB)
   - Test actions with mocked services
   - Use PHPUnit Mocks and Stubs

8. ERROR HANDLING
   - Custom exceptions for domains
   - ErrorMiddleware catches and serializes
   - NEVER expose stack traces in production

9. SECURITY
   - PDO prepared statements (SQL injection prevention)
   - password_hash() for passwords
   - Input validation always
   - CORS middleware
   - Rate limiting
   - HTTPS in production

10. PERFORMANCE
    - Cache DI container in production
    - Database indexes
    - Lazy-loading (DI can do this)
    - Paginate large result sets

*/
