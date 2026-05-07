# Simplicity First — Examples

## Example 1: Over-abstraction (Python)

**User Request:** "Add a function to calculate discount"

**❌ What LLMs Do (Overengineered)**

```python
from abc import ABC, abstractmethod
from enum import Enum
from typing import Protocol, Union
from dataclasses import dataclass

class DiscountStrategy(ABC):
    @abstractmethod
    def calculate(self, amount: float) -> float:
        pass

class PercentageDiscount(DiscountStrategy):
    def __init__(self, percentage: float):
        self.percentage = percentage

    def calculate(self, amount: float) -> float:
        return amount * (self.percentage / 100)

class FixedDiscount(DiscountStrategy):
    def __init__(self, fixed_amount: float):
        self.fixed_amount = fixed_amount

    def calculate(self, amount: float) -> float:
        return min(self.fixed_amount, amount)

@dataclass
class DiscountConfig:
    strategy: DiscountStrategy
    min_purchase: float = 0.0
    max_discount: float = float('inf')

class DiscountCalculator:
    def __init__(self, config: DiscountConfig):
        self.config = config

    def apply_discount(self, amount: float) -> float:
        if amount < self.config.min_purchase:
            return 0
        discount = self.config.strategy.calculate(amount)
        return min(discount, self.config.max_discount)

# Usage requires 30+ lines of setup for a simple calculation
```

**✅ What Should Happen (Simple)**

```python
def calculate_discount(amount: float, percent: float) -> float:
    """Calculate discount amount. percent should be 0-100."""
    return amount * (percent / 100)

# Usage
discount = calculate_discount(100.0, 10.0)  # $10 off
```

**When to add complexity:** Only when you actually need multiple discount types. If that requirement comes later, refactor then.

---

## Example 2: Speculative Features (Python)

**User Request:** "Save user preferences to database"

**❌ What LLMs Do (Unnecessary Features)**

```python
class PreferenceManager:
    def __init__(self, db, cache=None, validator=None):
        self.db = db
        self.cache = cache or InMemoryCache()
        self.validator = validator or DefaultValidator()

    def save(self, user_id: int, prefs: dict,
             merge: bool = True,
             validate: bool = True,
             notify: bool = False) -> bool:
        """Save preferences with optional merging, validation, and notifications."""

        if validate:
            errors = self.validator.validate(prefs)
            if errors:
                raise ValidationError(errors)

        existing = self.get(user_id) if merge else {}

        if merge:
            existing.update(prefs)
            prefs = existing

        self.db.execute(
            "UPDATE users SET preferences = ? WHERE id = ?",
            (json.dumps(prefs), user_id)
        )

        if self.cache:
            self.cache.set(f"prefs:{user_id}", prefs)

        if notify:
            self.notify_preference_change(user_id, prefs)

        return True

    def notify_preference_change(self, user_id: int, prefs: dict):
        # Another 30 lines for a feature nobody asked for
        pass
```

**✅ What Should Happen (Just What Was Asked)**

```python
def save_preferences(db, user_id: int, preferences: dict):
    """Save user preferences to database."""
    db.execute(
        "UPDATE users SET preferences = ? WHERE id = ?",
        (json.dumps(preferences), user_id)
    )
```

**Add later if needed:** Caching (when performance matters), validation (when bad data appears), merging (when requirement emerges).

---

## Example 3: Over-engineering a Filter Function (PHP)

**User Request:** "Get all active users from the array"

**❌ What LLMs Do (Overengineered)**

```php
interface UserFilterInterface
{
    public function filter(User $user): bool;
}

class ActiveUserFilter implements UserFilterInterface
{
    public function filter(User $user): bool
    {
        return $user->isActive();
    }
}

class UserCollection
{
    private array $users;
    private UserFilterInterface $filter;

    public function __construct(array $users, UserFilterInterface $filter)
    {
        $this->users = $users;
        $this->filter = $filter;
    }

    public function apply(): array
    {
        return array_values(
            array_filter($this->users, fn(User $user) => $this->filter->filter($user))
        );
    }
}

// Usage
$activeUsers = (new UserCollection($allUsers, new ActiveUserFilter()))->apply();
```

**✅ What Should Happen (Simple)**

```php
$activeUsers = array_filter($allUsers, fn($user) => $user->isActive());
```

**When to add complexity:** When you need multiple filter strategies (active, admin, recent) AND they compose together. Until then, `array_filter` with a closure is clearer and faster.