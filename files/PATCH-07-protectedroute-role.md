# INTEGRATION PATCH-07
## DEFECT-11: ProtectedRoute role comparison type mismatch

### Root Cause
U4 `ProtectedRoute.jsx` and `MainLayout.jsx` compare `user.role` as a **string**:
```js
// ProtectedRoute.jsx
roles.includes(user?.role)          // expects role = 'admin' (string)

// MainLayout.jsx
user?.role === 'admin'              // expects role = 'admin' (string)
```

U3 `/api/auth/me` returns `user.role` as a **nested object** via `UserResource`:
```json
{
  "id": 1,
  "name": "Admin User",
  "role": { "id": 1, "name": "Administrator", "slug": "admin" }
}
```

`roles.includes({id:1,name:"Administrator",slug:"admin"})` → **always false**.  
`user?.role === 'admin'` → **always false** (object !== string).

**Impact:**
- Admin navigation link never appears for admin users
- `<ProtectedRoute roles={['admin']}>` redirects admins to `/dashboard`
- All admin-only pages inaccessible regardless of actual role

### Affected Files
- `frontend/src/app/ProtectedRoute.jsx`
- `frontend/src/app/layouts/MainLayout.jsx`

### Exact Fix

**ProtectedRoute.jsx** — change role comparison to use `user.role.slug`:
```jsx
// BEFORE
if (roles && !roles.includes(user?.role)) {
  return <Navigate to="/dashboard" replace />
}

// AFTER (PATCH-07)
if (roles && !roles.includes(user?.role?.slug ?? user?.role)) {
  return <Navigate to="/dashboard" replace />
}
```

**MainLayout.jsx** — change role check to use `user.role.slug`:
```jsx
// BEFORE
{user?.role === 'admin' && (

// AFTER (PATCH-07)
{(user?.role?.slug ?? user?.role) === 'admin' && (
```

### Why this fix
The optional chaining `user?.role?.slug ?? user?.role` handles both:
1. Current U3 response: `role = {slug: 'admin'}` → extracts 'admin'
2. Legacy/alternative format: `role = 'admin'` → falls back to string directly

This makes the fix forward-compatible without requiring backend changes.

### Validation
- Admin user logs in → `/auth/me` returns `role: {slug: 'admin'}`
- `user?.role?.slug` evaluates to `'admin'`
- `roles.includes('admin')` → `true` → admin pages accessible ✓
- Non-admin user: `role: {slug: 'student'}` → `'student'` not in `['admin']` → redirect ✓

### Compatibility Impact
- No backend changes required
- No API contract changes
- No other frontend files affected

### Merge Risk: LOW
Surgical two-line change. Both files confirmed in U4 delivery.
