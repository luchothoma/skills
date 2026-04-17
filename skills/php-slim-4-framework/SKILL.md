---
name: php-slim-4-framework
description: Expert in PHP Slim Framework v4 - Build professional REST APIs with actions, middleware, DI, and scalable architecture (2026).
version: 1.0
triggers: ["slim", "slim4", "slim framework", "php slim", "rest", "api slim", "psr-7"]
license: MIT
---

## 🎯 What I Do
I build **professional REST APIs and web applications** with **Slim Framework 4.x** following current standards and best practices.

### Core (always applied):
- **Slim 4.x** + **PSR-7** (nyholm/psr7, laminas-diactoros)
- **Dependency Injection** (PHP-DI or PSR-11 Container)
- **Action classes** (1 class = 1 route, `__invoke()` method)
- **Named routes** (`name: 'users.list'`) for URL generation
- **Middleware** in correct order (logging → auth → business → error)
- **Typed Request/Response** (never `$_POST`, `$_GET` directly)
- **Consistent JSON responses** (`{success, data, meta}` or `{error, code}`)
- **Professional exception handling** with HTTP status codes
- **Validation** with modern libraries (Respect\Validation, symfony/validator)
- **Separation of concerns**: Actions (HTTP) → Services (business) → Repositories (data)

## ✅ When to Use Me
- Create/refactor REST APIs with Slim 4
- Structure new projects from scratch
- Implement authentication (JWT, bearer tokens)
- Validation, error handling, custom middleware
- Migrate from Slim 3 → 4
- Questions about routing, DI, PSR standards, or best practices

## 🚀 Recommended Structure
See `STRUCTURE.md` for folder layout and organization.

## 📘 Key Patterns
See `PATTERNS.md` for:
- Action classes
- Service-Driven architecture
- Custom middleware
- Advanced routing
- Validation strategies
- Error handling & custom exceptions
- Request/Response handling

## 💡 Code Examples
Folder `EXAMPLES/` contains ready-to-copy examples:
- CRUD actions
- Services & Repositories
- Authentication (JWT)

## 📚 Documentation & Examples

### Core Files
- **[STRUCTURE.md](STRUCTURE.md)** - Project structure, namespaces, bootstrap
- **[PATTERNS.md](PATTERNS.md)** - Code patterns with quick references
- **[ADVANCED.md](ADVANCED.md)** - Security, Logging, Versioning, Caching

### Code Examples (EXAMPLES/)
- `01-action-crud-basic.php` - CRUD actions
- `02-service-repository.php` - Service + Repository pattern
- `03-middlewares-authentication.php` - Auth, CORS, error handling
- `04-validators-exceptions.php` - Validation & custom exceptions
- `05-bootstrap-index-complete.php` - DI setup & routing
- `06-testing-best-practices.php` - PHPUnit tests

### Quick Setup
```bash
composer create-project slim/slim-skeleton myapp
cd myapp
composer require php-di/php-di firebase/php-jwt respect/validation
```

---
**For the AI using this skill**: Focus on PSR-7, Action classes, DI, and the patterns in PATTERNS.md. Always prefer modern PHP practices and security best practices from ADVANCED.md. Assume Slim 4.x with PHP-DI. Ask about container/auth if unclear. Prioritize Action + DI pattern. Follow PSR-12 coding standards.