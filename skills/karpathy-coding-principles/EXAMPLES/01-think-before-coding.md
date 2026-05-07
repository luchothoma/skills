# Think Before Coding — Examples

## Example 1: Hidden Assumptions (Python)

**User Request:** "Add a feature to export user data"

**❌ What LLMs Do (Wrong Assumptions)**

```python
def export_users(format='json'):
    """Export all users to a file."""
    users = User.query.all()

    if format == 'json':
        with open('users.json', 'w') as f:
            json.dump([u.to_dict() for u in users], f)
    elif format == 'csv':
        with open('users.csv', 'w') as f:
            writer = csv.DictWriter(f, fieldnames=['id', 'email', 'name'])
            writer.writeheader()
            writer.writerows([u.to_dict() for u in users])

    return f"Exported {len(users)} users"
```

**Problems:**
- Assumed it should export ALL users (what about pagination? privacy?)
- Assumed file location without asking
- Assumed which fields to include
- Assumed CSV fieldnames without checking actual data structure

**✅ What Should Happen (Surface Assumptions)**

```
Before implementing, I need to clarify:

1. **Scope**: Export all users or filtered subset? (privacy implications)
2. **Format**: You mentioned "export" — did you mean:
   - Download file in browser?
   - Background job with email notification?
   - API endpoint returning data?
3. **Fields**: Which user fields? (some might be sensitive)
4. **Volume**: How many users typically? (affects approach)

Simplest approach: Add an API endpoint that returns paginated JSON.
Would need more info for file-based exports.

What's your preference?
```

---

## Example 2: Multiple Interpretations (Python)

**User Request:** "Make the search faster"

**❌ What LLMs Do (Pick Silently)**

```python
# Adds caching, database indexes, and async processing
# without asking which "faster" matters
@lru_cache(maxsize=1000)
async def search(query: str) -> List[Result]:
    # ... 200 lines of optimization code
```

**✅ What Should Happen**

```
"Make the search faster" could mean:

1. **Faster response time** (< 100ms instead of 500ms)
   - Add database indexes
   - Cache frequent queries
   - Estimated effort: 2 hours

2. **Handle more concurrent searches** (throughput)
   - Add async/await
   - Connection pooling
   - Estimated effort: 4 hours

3. **Faster perceived speed** (UX)
   - Show partial results immediately
   - Progressive loading
   - Estimated effort: 3 hours

Current search takes ~500ms for typical queries.
Which aspect matters most for your use case?
```

---

## Example 3: API Endpoint Assumptions (TypeScript / Node.js)

**User Request:** "Add a search endpoint for products"

**❌ What LLMs Do (Wrong Assumptions)**

```typescript
import { Router, Request, Response } from 'express';
import { Product } from '../models/Product';

const router = Router();

// ❌ Assumes ElasticSearch, pagination defaults, auth handled elsewhere
router.get('/products/search', async (req: Request, res: Response) => {
  const { q, page = '1', size = '20' } = req.query;

  const results = await Product.search({
    query: {
      multi_match: {
        query: q,
        fields: ['name', 'description', 'tags'],
      },
    },
    from: (Number(page) - 1) * Number(size),
    size: Number(size),
  });

  res.json({
    data: results.hits.hits.map((h: any) => h._source),
    total: results.hits.total.value,
    page: Number(page),
    size: Number(size),
  });
});
```

**Problems:**
- Assumed ElasticSearch is available and appropriate
- Assumed pagination defaults without confirming
- No auth checks, no input validation
- Assumed fields to search (name, description, tags)
- No error handling for malformed queries

**✅ What Should Happen (Surface Assumptions)**

```
Before implementing product search, I need to clarify:

1. **Search backend**: Are you using ElasticSearch, PostgreSQL full-text,
   or simple LIKE queries? Each requires a different approach.

2. **Auth**: Should all products be searchable, or filter by user
   permissions / organization?

3. **Fields**: Which product fields should be searchable?
   Name only? Description? SKU? Tags?

4. **Pagination**: What's the default page size? Any max limit?

5. **Performance expectations**: How many products? Expected QPS?
   (affects whether we need caching or indexing)

Simplest starting point: a SQL LIKE query on name + description,
paginated at 20 results per page. Easy to swap search backends later.

Does that work, or do you need something more specific?
```