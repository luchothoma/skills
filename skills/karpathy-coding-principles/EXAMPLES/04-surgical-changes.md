# Surgical Changes — Examples

## Example 1: Drive-by Refactoring (Python)

**User Request:** "Fix the bug where empty emails crash the validator"

**❌ What LLMs Do (Too Much)**

```diff
  def validate_user(user_data):
-     # Check email format
-     if not user_data.get('email'):
+     """Validate user data."""
+     email = user_data.get('email', '').strip()
+
+     # Validate email
+     if not email:
          raise ValueError("Email required")
-
-     # Basic email validation
-     if '@' not in user_data['email']:
+     if '@' not in email or '.' not in email.split('@')[1]:
          raise ValueError("Invalid email")

-     # Check username
-     if not user_data.get('username'):
+     # Validate username
+     username = user_data.get('username', '').strip()
+     if not username:
          raise ValueError("Username required")
+     if len(username) < 3:
+         raise ValueError("Username too short")
+     if not username.isalnum():
+         raise ValueError("Username must be alphanumeric")

      return True
```

**Problems:**
- "Improved" email validation beyond the bug fix
- Added username validation nobody asked for
- Changed comments and added docstring

**✅ What Should Happen (Surgical)**

```diff
  def validate_user(user_data):
      # Check email format
-     if not user_data.get('email'):
+     email = user_data.get('email', '')
+     if not email or not email.strip():
          raise ValueError("Email required")

      # Basic email validation
-     if '@' not in user_data['email']:
+     if '@' not in email:
          raise ValueError("Invalid email")

      # Check username
      if not user_data.get('username'):
          raise ValueError("Username required")

      return True
```

**Only changed:** The specific lines that fix empty email handling.

---

## Example 2: Style Drift (Python)

**User Request:** "Add logging to the upload function"

**❌ What LLMs Do (Reformat Everything)**

```diff
- def upload_file(file_path, destination):
+ def upload_file(file_path: str, destination: str) -> bool:
+     """Upload file to destination with logging."""
+     logger.info(f"Uploading {file_path} to {destination}")
+
      try:
-         with open(file_path, 'rb') as f:
-             data = f.read()
-
-         response = requests.post(destination, files={'file': data})
-
-         if response.status_code == 200:
-             return True
-         else:
-             return False
+         with open(file_path, "rb") as f:
+             data = f.read()
+         response = requests.post(destination, files={"file": data})
+         success = response.status_code == 200
+
+         if success:
+             logger.info(f"Upload successful: {file_path}")
+         else:
+             logger.error(f"Upload failed: {file_path}, status={response.status_code}")
+
+         return success
      except Exception as e:
-         print(f"Error: {e}")
+         logger.exception(f"Upload error for {file_path}: {e}")
          return False
```

**Problems:**
- Changed quote style ('' to "")
- Added type hints and docstring
- Reformatted whitespace
- Changed boolean return logic

**✅ What Should Happen (Match Existing Style)**

```diff
+ import logging
+
+ logger = logging.getLogger(__name__)
+
  def upload_file(file_path, destination):
+     logger.info(f'Starting upload: {file_path}')
      try:
          with open(file_path, 'rb') as f:
              data = f.read()

          response = requests.post(destination, files={'file': data})

          if response.status_code == 200:
+             logger.info(f'Upload successful: {file_path}')
              return True
          else:
+             logger.error(f'Upload failed: {file_path}, status={response.status_code}')
              return False
      except Exception as e:
-         print(f"Error: {e}")
+         logger.exception(f'Upload error: {file_path}')
          return False
```

**Matched:** Single quotes, no type hints, existing boolean pattern, spacing style.

---

## Example 3: Fixing a Null-Ref Without Refactoring the Class (C#)

**User Request:** "Fix NullReferenceException in `GetOrderSummary` when order has no items"

**❌ What LLMs Do (Refactor Everything)**

```diff
  public class OrderService
  {
      private readonly IOrderRepository _repo;
-     private readonly IEmailService _email;
-     private readonly ICacheService _cache;

-     public OrderService(IOrderRepository repo, IEmailService email, ICacheService cache)
+     public OrderService(IOrderRepository repo)
      {
          _repo = repo;
-         _email = email;
-         _cache = cache;
      }

-     public OrderSummary GetOrderSummary(int orderId)
+     public async Task<OrderSummary> GetOrderSummaryAsync(int orderId, CancellationToken ct = default)
      {
-         var order = _repo.GetById(orderId);
-         if (order == null) return null;
+         var order = await _repo.GetByIdAsync(orderId, ct);
+         if (order is null) return OrderSummary.Empty;

-         var summary = new OrderSummary
-         {
-             OrderId = order.Id,
-             Total = order.Items.Sum(i => i.Price * i.Quantity),
-             ItemCount = order.Items.Count
-         };
+         var summary = new OrderSummary(order.Id, order.OrderDate);
+         if (order.Items is { Count: > 0 })
+         {
+             summary.Total = order.Items.Sum(i => i.Price * i.Quantity);
+             summary.ItemCount = order.Items.Count;
+         }

-         _cache.Set($"order:{orderId}", summary);
-         _email.SendReceipt(order.CustomerEmail, summary);
          return summary;
      }
  }
```

**Problems:**
- Removed `IEmailService` and `ICacheService` (unrelated cleanup)
- Changed sync to async (not requested)
- Added `CancellationToken` parameter
- Renamed method (breaks callers)
- Changed null-handling pattern (`==` to `is`)
- Added `OrderSummary.Empty` (doesn't exist yet)
- Changed constructor signature (breaks DI registration)
- Removed caching and email logic (business behavior change)

**✅ What Should Happen (Surgical)**

```diff
  public class OrderService
  {
      private readonly IOrderRepository _repo;
      private readonly IEmailService _email;
      private readonly ICacheService _cache;

      public OrderService(IOrderRepository repo, IEmailService email, ICacheService cache)
      {
          _repo = repo;
          _email = email;
          _cache = cache;
      }

      public OrderSummary GetOrderSummary(int orderId)
      {
          var order = _repo.GetById(orderId);
          if (order == null) return null;

          var summary = new OrderSummary
          {
              OrderId = order.Id,
-             Total = order.Items.Sum(i => i.Price * i.Quantity),
-             ItemCount = order.Items.Count
+             Total = order.Items?.Sum(i => i.Price * i.Quantity) ?? 0,
+             ItemCount = order.Items?.Count ?? 0
          };

          _cache.Set($"order:{orderId}", summary);
          _email.SendReceipt(order.CustomerEmail, summary);
          return summary;
      }
  }
```

**Only changed:** Added null-conditional operators (`?.`) and null-coalescing (`??`) on the two lines that access `order.Items`. Everything else — DI, sync/async, caching, email — stays untouched.