<?php
/**
 * Example: Validators + Custom Exceptions
 */

namespace App\Application\Validators;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

// ============ USER VALIDATOR ============
final class UserValidator
{
    public function validateCreate(array $data): ?array
    {
        return $this->validate($data, [
            'email' => v::notEmpty()->email(),
            'name' => v::notEmpty()->stringType()->length(3, 100),
            'password' => v::notEmpty()->stringType()->length(8, 255),
        ]);
    }

    public function validateUpdate(array $data): ?array
    {
        // Update: all fields are optional (but if sent, must be valid)
        return $this->validate($data, [
            'email' => v::optional(v::email()),
            'name' => v::optional(v::stringType()->length(3, 100)),
        ]);
    }

    public function validateLogin(array $data): ?array
    {
        return $this->validate($data, [
            'email' => v::notEmpty()->email(),
            'password' => v::notEmpty()->stringType(),
        ]);
    }

    private function validate(array $data, array $rules): ?array
    {
        try {
            // Build validator dynamically
            $validator = v::allOf();
            foreach ($rules as $field => $fieldValidator) {
                $validator = $validator->key($field, $fieldValidator);
            }
            
            $validator->assert($data);
            return null;  // Valid
            
        } catch (NestedValidationException $e) {
            // Return errors per field
            $errors = [];
            foreach ($e->getMessages() as $field => $message) {
                $errors[$field] = $message;
            }
            return $errors;
        }
    }
}

// ============ PRODUCT VALIDATOR ============
final class ProductValidator
{
    public function validateCreate(array $data): ?array
    {
        return $this->validate($data, [
            'name' => v::notEmpty()->stringType()->length(3, 255),
            'sku' => v::notEmpty()->stringType()->regex('/^[A-Z0-9\-]{3,50}$/'),
            'price' => v::notEmpty()->number()->positive(),
            'stock' => v::notEmpty()->intVal()->min(0),
            'description' => v::optional(v::stringType()),
            'category_id' => v::notEmpty()->intVal()->positive(),
        ]);
    }

    private function validate(array $data, array $rules): ?array
    {
        try {
            $validator = v::allOf();
            foreach ($rules as $field => $fieldValidator) {
                $validator = $validator->key($field, $fieldValidator);
            }
            
            $validator->assert($data);
            return null;
        } catch (NestedValidationException $e) {
            $errors = [];
            foreach ($e->getMessages() as $field => $message) {
                $errors[$field] = $message;
            }
            return $errors;
        }
    }
}

// ============ CUSTOM EXCEPTIONS ============
namespace App\Domain\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    public function getStatus(): int
    {
        return 500;
    }

    public function getErrorCode(): string
    {
        return 'INTERNAL_ERROR';
    }

    public function toJson(): array
    {
        return [
            'success' => false,
            'error' => $this->getMessage(),
            'code' => $this->getErrorCode()
        ];
    }
}

// Resource not found
final class ResourceNotFoundException extends DomainException
{
    public function __construct(string $resource = 'Resource')
    {
        parent::__construct("$resource not found");
    }

    public function getStatus(): int { return 404; }
    public function getErrorCode(): string { return 'NOT_FOUND'; }
}

// Validation failed
final class ValidationException extends DomainException
{
    private array $errors = [];

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getStatus(): int { return 422; }
    public function getErrorCode(): string { return 'VALIDATION_ERROR'; }
    public function getErrors(): array { return $this->errors; }

    public function toJson(): array
    {
        return [
            'success' => false,
            'error' => $this->getMessage(),
            'code' => $this->getErrorCode(),
            'errors' => $this->errors
        ];
    }
}

// Duplicate resource
final class DuplicateResourceException extends DomainException
{
    public function __construct(string $field, string $value)
    {
        parent::__construct("Resource with $field: $value already exists");
    }

    public function getStatus(): int { return 409; }
    public function getErrorCode(): string { return 'CONFLICT'; }
}

// Access denied
final class UnauthorizedException extends DomainException
{
    public function __construct(string $message = 'Not authorized')
    {
        parent::__construct($message);
    }

    public function getStatus(): int { return 403; }
    public function getErrorCode(): string { return 'FORBIDDEN'; }
}

// Example usage in an Action
namespace App\Application\Actions\User;

use App\Domain\Services\UserService;
use App\Domain\Exceptions\{ValidationException, DuplicateResourceException, ResourceNotFoundException};
use App\Application\Validators\UserValidator;

final class LoginAction
{
    public function __construct(
        private UserService $userService,
        private UserValidator $validator
    ) {}

    public function __invoke($request, $response)
    {
        $data = $request->getParsedBody() ?? [];

        // Validate input
        $errors = $this->validator->validateLogin($data);
        if ($errors) {
            throw new ValidationException($errors, 'Invalid credentials');
        }

        try {
            // Find user
            $user = $this->userService->getByEmail($data['email']);
            if (!$user) {
                throw new ResourceNotFoundException('User');
            }

            // Verify password
            if (!password_verify($data['password'], $user['password'])) {
                throw new UnauthorizedException('Incorrect password');
            }

            // Return token
            $token = $this->userService->generateToken($user);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => ['token' => $token, 'user' => $user]
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');

        } catch (DomainException $e) {
            throw $e;
        }
    }
}

// REGISTER EXCEPTIONS IN ERROR MIDDLEWARE
/*
catch (ValidationException $e) {
    return $response->withStatus($e->getStatus())
        ->write(json_encode($e->toJson()));
}
catch (DomainException $e) {
    return $response->withStatus($e->getStatus())
        ->write(json_encode($e->toJson()));
}
*/
