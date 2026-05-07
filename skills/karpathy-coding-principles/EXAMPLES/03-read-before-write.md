# Read Before Write — Examples

## Example 1: Adding a Field Without Reading Callers (Go)

**User Request:** "Add a `Timeout` field to the `Config` struct"

**❌ What LLMs Do (Write Without Reading)**

```go
// Reads only the struct definition, ignores all consumers
type Config struct {
    Host     string        `yaml:"host"`
    Port     int           `yaml:"port"`
    Timeout  time.Duration `yaml:"timeout"` // ← adds this field
}
```

**What the LLM missed by not reading:**

1. Three callers create `Config` literals — none compile anymore:
   ```go
   // caller1.go
   cfg := Config{Host: "localhost", Port: 8080}
   // compiler error: missing field Timeout

   // caller2.go
   cfg := Config{Host: "0.0.0.0", Port: 9000}
   // compiler error: missing field Timeout

   // caller3.go — in tests
   cfg := Config{Host: "test", Port: 9999}
   // compiler error: missing field Timeout
   ```

2. The YAML parsing test expects specific fields — doesn't test timeout parsing:
   ```go
   // config_test.go
   func TestParseConfig(t *testing.T) {
       data := []byte("host: localhost\nport: 8080\n")
       cfg, err := parseConfig(data)
       // works fine, but timeout defaults to 0 (no timeout)
       // Is that acceptable? Need to read the test to understand expectations
   }
   ```

3. The HTTP client initialization doesn't use the timeout:
   ```go
   // client.go
   func NewClient(cfg Config) *http.Client {
       return &http.Client{
           Transport: &http.Transport{...},
           // Timeout not set — was this intentional?
       }
   }
   ```

**✅ What Should Happen (Read First)**

```go
// 1. Read all files that reference Config
// 2. Understand existing patterns (YAML tags, default values)
// 3. Propose with full context

// Since Config{} literals are used without named fields,
// the cleanest approach depends on how many callers exist:

// Option A: Add field after existing fields (breaks positional callers)
// Option B: Use a builder/constructor pattern
// Option C: Make Timeout a pointer (*Duration) with nil = no timeout

// Let me check which pattern fits your codebase.
// I found 3 callers that need updating — do you want me to
// update them all, or use a constructor to centralize defaults?
```

---

## Example 2: Modifying a Handler Without Reading the Router (Go)

**User Request:** "Change the `/users` handler to return email too"

**❌ What LLMs Do (Edit in Isolation)**

```go
// Reads only the handler file, not the router setup
func usersHandler(w http.ResponseWriter, r *http.Request) {
    users := fetchUsers()
    // Adds email to response
    json.NewEncoder(w).Encode(map[string]any{
        "id":    users[0].ID,
        "name":  users[0].Name,
        "email": users[0].Email,  // ← adds this
    })
}
```

**What the LLM missed:**

The router registered the handler with middleware that strips emails for non-admin roles:

```go
// router.go — never read
router.Get("/users", requireAuth(requireAdmin(usersHandler)))
```

The handler now leaks emails to non-admin users.

**✅ What Should Happen**

```
Before changing the handler, I need to read the router setup to
understand auth/role middleware and whether this endpoint is
accessible to non-admin users.

If it's admin-only, adding email is safe. If not, we need to either:
- Scope the email addition to admin requests
- Create a separate admin endpoint
- Add field-level permissions in the response
```

---

## Key Lesson

**Always read the callers, the tests, and the configuration/initialization code before changing a definition.** What looks like a one-line change often ripples across the codebase in ways the file alone doesn't reveal.