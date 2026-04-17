# Quick Index - Slim 4 Skill

## 📋 Documentation

1. **[SKILL.md](SKILL.md)** - Skill purpose and triggers
2. **[STRUCTURE.md](STRUCTURE.md)** - Folder structure, namespaces, bootstrap, entry point
3. **[PATTERNS.md](PATTERNS.md)** - Key patterns with code snippets
4. **[ADVANCED.md](ADVANCED.md)** - Advanced topics: Security, Logging, Versioning, Caching

## 💡 Examples (EXAMPLES/)

| File | Topics |
|------|--------|
| `01-action-crud-basic.php` | List, Show, Store, Update, Delete actions |
| `02-service-repository.php` | Service + Repository interface + PDO impl |
| `03-middlewares-authentication.php` | Auth JWT, CORS, Rate Limit, Error handling |
| `04-validators-exceptions.php` | Validators, Custom Exceptions |
| `05-bootstrap-index-complete.php` | DI Container setup + routes + middleware |
| `06-testing-best-practices.php` | PHPUnit tests + 10 best practices |

## 🎯 Key Concepts

See [PATTERNS.md](PATTERNS.md) for detailed code patterns and quick references.

## ⚙️ Initial Setup

```bash
# 1. Create project
composer create-project slim/slim-skeleton myapp
cd myapp

# 2. Install additional dependencies
composer require php-di/php-di firebase/php-jwt respect/validation

# 3. Structure (copy from STRUCTURE.md)
mkdir -p src/{Application/{Actions,Middleware,Validators},Domain/{Services,Repositories},Infrastructure/Persistence}
mkdir -p tests/{Application/{Actions,Validators},Infrastructure/Persistence}

# 4. Autoload in composer.json
"autoload": {
    "psr-4": {
        "App\\": "src/"
    }
}

# 5. Composer dump
composer dump-autoload

# 6. Copy bootstrap.php (EXAMPLES/05-*.php) to src/
# 7. Copy index.php (EXAMPLES/05-*.php) to public/
# 8. Create .env with config
```

## 🔍 Implementation Checklist

- [ ] `bootstrap.php` with DI Container
- [ ] `index.php` with middleware in correct order
- [ ] Routes organized in groups
- [ ] Actions with DI in constructor
- [ ] Services with business logic
- [ ] Repositories (interface + impl)
- [ ] Centralized validators
- [ ] Custom Exception classes
- [ ] ErrorMiddleware catching exceptions
- [ ] Tests (unit + integration)
- [ ] .env for configuration

## 🚨 Common Errors

| Problem | Cause | Solution |
|---------|-------|----------|
| "Action not found" | Class not auto-wired | Register in container or use `\DI\autowire()` |
| Middleware not applied | Wrong order | ErrorMiddleware last in registration |
| Empty Request/Response | Not writing body | `$response->getBody()->write(json_encode(...))` |
| Validation not working | Data not parsed | `$request->getParsedBody()` with correct Content-Type |
| Token not recognized | Wrong headers | Must be `Authorization: Bearer <token>` |

## 📚 Recommended Dependencies

```json
{
  "slim/slim": "^4.12",
  "php-di/php-di": "^7.0",
  "nyholm/psr7": "^1.5",
  "respect/validation": "^2.2",
  "firebase/php-jwt": "^6.0",
  "vlucas/phpdotenv": "^5.4"
}
```

## 🔗 Useful Links

- [Slim 4 Docs](https://www.slimframework.com/docs/v4/)
- [PSR-7 HTTP](https://www.php-fig.org/psr/psr-7/)
- [PSR-11 Container](https://www.php-fig.org/psr/psr-11/)
- [PHP-DI](https://php-di.org/)
- [Respect Validation](https://respect-validation.readthedocs.io/)
- [Firebase JWT](https://github.com/firebase/php-jwt)

---

**Final tips:**
- Read more patterns from `PATTERNS.md` if you need more context
- Copy examples from `EXAMPLES/` as a base for your actions
- Always test: at minimum validators, services, repositories
- In production: DI cache, HTTPS, JWT refresh tokens, rate limit
