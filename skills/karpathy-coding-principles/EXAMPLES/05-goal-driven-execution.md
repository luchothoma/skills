# Goal-Driven Execution — Examples

## Example 1: Vague vs. Verifiable (Python)

**User Request:** "Fix the authentication system"

**❌ What LLMs Do (Vague Approach)**

```
I'll fix the authentication system by:
1. Reviewing the code
2. Identifying issues
3. Making improvements
4. Testing the changes

[Proceeds to make changes without clear success criteria]
```

**✅ What Should Happen (Verifiable Goals)**

```
To "fix authentication," I need to define success criteria.
What specific issue are we solving?

If the issue is "users stay logged in after password change":

Plan:
1. Write test: Change password → verify old session invalidated
   Verify: Test fails (reproduces bug)

2. Implement: Invalidate sessions on password change
   Verify: Test passes

3. Check edge cases: Multiple active sessions, concurrent changes
   Verify: Additional tests pass

4. Verify no regression: Existing auth tests still pass
   Verify: Full test suite green

Current test coverage for auth: [shows coverage]
What's the specific auth issue you're seeing?
```

---

## Example 2: Multi-Step with Verification (Python)

**User Request:** "Add rate limiting to the API"

**❌ What LLMs Do (All at Once)**

```python
# Implements full rate limiting with Redis, multiple strategies,
# configuration system, and monitoring in one 300-line commit
# No clear verification steps
```

**✅ What Should Happen (Incremental with Verification)**

```
Plan for rate limiting:

1. Add basic in-memory rate limiting (single endpoint)
   Verify:
   - Test: 100 requests → first 10 succeed, rest get 429
   - Manual: curl endpoint 11 times, see rate limit error

2. Extract to middleware (apply to all endpoints)
   Verify:
   - Test: Rate limits apply to /users and /posts
   - Existing endpoint tests still pass

3. Add Redis backend (for multi-server)
   Verify:
   - Test: Rate limit persists across app restarts
   - Test: Two app instances share rate limit counter

4. Add configuration (rates per endpoint)
   Verify:
   - Test: /search allows 10/min, /users allows 100/min
   - Config file parsed correctly

Each step is independently verifiable and deployable.
Start with step 1?
```

---

## Example 3: Test-First Verification (Python)

**User Request:** "The sorting breaks when there are duplicate scores"

**❌ What LLMs Do (Fix Without Reproducing)**

```python
# Immediately changes sort logic without confirming the bug
def sort_scores(scores):
    return sorted(scores, key=lambda x: (-x['score'], x['name']))
```

**✅ What Should Happen (Reproduce First)**

```python
# 1. First, write a test that reproduces the issue
def test_sort_with_duplicate_scores():
    """Test sorting when multiple items have same score."""
    scores = [
        {'name': 'Alice', 'score': 100},
        {'name': 'Bob', 'score': 100},
        {'name': 'Charlie', 'score': 90},
    ]

    result = sort_scores(scores)

    # The bug: order is non-deterministic for duplicates
    # Run this test multiple times, it should be consistent
    assert result[0]['score'] == 100
    assert result[1]['score'] == 100
    assert result[2]['score'] == 90

# Verify: Run test 10 times → fails with inconsistent ordering

# 2. Now fix with stable sort
def sort_scores(scores):
    """Sort by score descending, then name ascending for ties."""
    return sorted(scores, key=lambda x: (-x['score'], x['name']))

# Verify: Test passes consistently
```

---

## Example 4: Race Condition Fix Without Reproduction (TypeScript)

**User Request:** "Fix the race condition when fetching user data"

**❌ What LLMs Do (Fix Without Reproducing)**

```typescript
// Immediately adds locks/abort controller without reproducing the bug
async function fetchUserData(userId: string) {
  const controller = new AbortController();

  try {
    const [profile, posts, settings] = await Promise.all([
      fetch(`/api/users/${userId}/profile`, { signal: controller.signal }),
      fetch(`/api/users/${userId}/posts`, { signal: controller.signal }),
      fetch(`/api/users/${userId}/settings`, { signal: controller.signal }),
    ]);

    if (!profile.ok || !posts.ok || !settings.ok) {
      throw new Error('One or more requests failed');
    }

    return {
      profile: await profile.json(),
      posts: await posts.json(),
      settings: await settings.json(),
    };
  } catch (err) {
    controller.abort();
    throw err;
  }
}
```

**Problem:** The race condition might not be about concurrent fetches — it could be about state mutations between reads. Adding `AbortController` without understanding the actual race is cargo-culting.

**✅ What Should Happen (Reproduce First)**

```typescript
describe('fetchUserData', () => {
  it('should return consistent data even when user is updated mid-fetch', async () => {
    // 1. Reproduce the race condition
    const userId = 'user-1';

    // Simulate: profile fetch takes 100ms, during which user updates
    let userVersion = 1;

    fetchMock
      .get(`/api/users/${userId}/profile`, async () => {
        await delay(100);
        return { name: 'Alice', version: userVersion };
      })
      .get(`/api/users/${userId}/posts`, async () => {
        // This resolves first, triggering a user update
        userVersion = 2;
        return { posts: [] };
      })
      .get(`/api/users/${userId}/settings`, async () => {
        return { theme: 'dark', version: userVersion };
      });

    const result = await fetchUserData(userId);

    // The bug: profile.version (1) !== settings.version (2)
    // User sees stale data mixed with fresh data
    expect(result.profile.version).toBe(result.settings.version);
    // Verify: Test fails — versions don't match
  });
});
```

```
Now I understand the actual race condition: it's a consistency
issue, not a request cancellation issue.

Plan:
1. Write test that reproduces the version mismatch (done above)
2. Implement: Use a single version check after all fetches resolve
3. If versions mismatch, retry the batch
4. Verify: Test passes consistently
5. Edge case: What if it keeps mismatching? Add max retries.
```

---

## Anti-Patterns Summary

| Principle | Anti-Pattern | Fix |
|-----------|-------------|-----|
| Think Before Coding | Silently assumes file format, fields, scope | List assumptions explicitly, ask for clarification |
| Simplicity First | Strategy pattern for single discount calculation | One function until complexity is actually needed |
| Read Before Write | Adds field to struct without reading callers | Read all consumers, tests, and init code first |
| Surgical Changes | Reformats quotes, adds type hints while fixing bug | Only change lines that fix the reported issue |
| Goal-Driven Execution | "I'll review and improve the code" | "Write test for bug X → make it pass → verify no regressions" |

## Key Insight

The "overcomplicated" examples aren't obviously wrong — they follow design patterns and best practices. The problem is **timing**: they add complexity before it's needed, which:

- Makes code harder to understand
- Introduces more bugs
- Takes longer to implement
- Harder to test

The "simple" versions are:
- Easier to understand
- Faster to implement
- Easier to test
- Can be refactored later when complexity is actually needed

**Good code is code that solves today's problem simply, not tomorrow's problem prematurely.**