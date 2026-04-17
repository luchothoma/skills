<?php
/**
 * Example: Service + Repository (Business Logic)
 * Pattern: Service contains logic, Repository accesses data
 */

namespace App\Domain\Services;

use App\Domain\Repositories\UserRepository;
use App\Domain\Entities\User;

// ============ SERVICE ============
final class UserService
{
    public function __construct(
        private UserRepository $repository
    ) {}

    public function getAll(): array
    {
        return $this->repository->findAll();
    }

    public function paginate(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        return $this->repository->findPaginated($offset, $limit);
    }

    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): array
    {
        // Business validations/transformations
        $password = password_hash($data['password'], PASSWORD_BCRYPT);

        return $this->repository->insert([
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => $password,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update(int $id, array $data): ?array
    {
        $existing = $this->repository->findById($id);
        
        if (!$existing) {
            return null;
        }

        // Only update allowed fields
        $allowed = ['name', 'email'];
        $updates = array_intersect_key($data, array_flip($allowed));
        $updates['updated_at'] = date('Y-m-d H:i:s');

        return $this->repository->updateById($id, $updates);
    }

    public function delete(int $id): bool
    {
        return $this->repository->deleteById($id);
    }

    public function getByEmail(string $email): ?array
    {
        return $this->repository->findByEmail($email);
    }
}

// ============ REPOSITORY INTERFACE ============
namespace App\Domain\Repositories;

interface UserRepository
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function findPaginated(int $offset, int $limit): array;
    public function insert(array $data): array;
    public function updateById(int $id, array $data): ?array;
    public function deleteById(int $id): bool;
}

// ============ IMPLEMENTATION (PDO) ============
namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\UserRepository;
use PDO;

final class PDOUserRepository implements UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, email, name, created_at FROM users ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function findPaginated(int $offset, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, name, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): array
    {
        $sql = 'INSERT INTO users (email, name, password, created_at) VALUES (?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['email'],
            $data['name'],
            $data['password'],
            $data['created_at']
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return ['id' => $id, ...$data];
    }

    public function updateById(int $id, array $data): ?array
    {
        // Dynamically build update fields
        $fields = array_keys($data);
        $set = implode(', ', array_map(fn($f) => "$f = ?", $fields));
        
        $sql = "UPDATE users SET $set WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([...$data, $id]);

        return $success ? $this->findById($id) : null;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

// ============ BOOTSTRAP.PHP (REGISTER) ============
/*
$builder->addDefinitions([
    UserRepository::class => \DI\create(\App\Infrastructure\Persistence\PDOUserRepository::class),
    UserService::class => \DI\autowire(),
]);
*/
