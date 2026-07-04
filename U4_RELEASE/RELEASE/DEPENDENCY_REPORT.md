# Dependency Report — AI Study Assistant Frontend Phase 2

**Team:** U4 (Frontend Lead)  
**Version:** 2.0.0  
**Date:** 2026-06-28

---

## 1. External Dependencies (npm)

### Runtime Dependencies (`dependencies`)

| Package | Version | Purpose | Used By |
|---------|---------|---------|---------|
| `react` | ^19.2.6 | UI framework | All components |
| `react-dom` | ^19.2.6 | DOM rendering | main.jsx |
| `react-router-dom` | ^7.18.0 | Client-side routing | App.jsx, all pages, MainLayout |
| `zustand` | ^5.0.14 | State management | All stores, hooks/index.js |
| `axios` | ^1.18.0 | HTTP client | api/client.js |

### Dev Dependencies (selected)

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^8.0.x | Build tool & dev server |
| `vite-plugin-pwa` | ^1.3.0 | PWA / service worker generation |
| `@vitejs/plugin-react` | ^6.0.x | React fast-refresh in Vite |
| `tailwindcss` | ^4.3.1 | CSS framework |
| `@tailwindcss/postcss` | ^4.3.1 | Tailwind PostCSS plugin |
| `autoprefixer` | ^10.5.0 | CSS vendor prefixes |
| `eslint` | ^10.x | Linting |
| `vitest` | (peer) | Test runner (add as devDep for tests) |

### Missing devDependencies (for testing — add before running tests)

```bash
npm install -D vitest @testing-library/react @testing-library/jest-dom jsdom
```

---

## 2. Provider Hierarchy

```
index.html
└── src/main.jsx
    └── <StrictMode>
        └── <BrowserRouter>                   ← React Router
            └── <App>
                └── <ErrorBoundary>            ← Error catching
                    └── <AuthProvider>         ← Token validation + init
                        └── <Suspense>         ← Lazy load fallback
                            └── <Routes>
                                ├── AuthLayout  ← Public pages
                                └── ProtectedRoute (no roles)
                                    └── <MainLayout>  ← App shell
                                        ├── Toast (from uiStore)
                                        └── <Outlet> → Page Components
                                            └── ProtectedRoute (roles=['admin'])
                                                └── Admin pages
```

---

## 3. Zustand Store Dependency Graph

```
authStore (persisted)
├── consumed by: AuthProvider, ProtectedRoute, MainLayout
├── consumed by: all protected pages (role checks)
└── consumed by: api/client.js (token for headers)

uiStore
├── consumed by: MainLayout (toast, sidebar, globalLoading)
└── consumed by: hooks/index.js (useToast wraps showToast)

featureStores
├── documentStore → consumed by: DocumentListPage, DocumentUploadPage, DocumentDetailPage
├── chatStore → consumed by: ChatbotPage
├── quizStore → consumed by: QuizAttemptPage, QuizListPage
└── flashcardStore → consumed by: FlashcardSetListPage, FlashcardReviewPage
```

---

## 4. API Module Dependency Graph

```
api/client.js (axios instance)
├── interceptors read: authStore.getState().token
├── 401 handler calls: authStore.getState().logout()
└── base URL: /api (proxied by Vite to backend:8000)

All API modules import from api/client.js:
├── api/auth.js          → /api/auth/*
├── api/documents.js     → /api/documents/*
├── api/quiz.js          → /api/quizzes/*
├── api/flashcards.js    → /api/flashcard-sets/*
├── api/summaries.js     → /api/summaries/*
├── api/chatbot.js       → /api/chat/*
├── api/planner.js       → /api/study-plan/*
├── api/analytics.js     → /api/analytics/*
└── api/admin.js         → /api/admin/*
```

---

## 5. Component Dependency Graph

```
components/ui/index.jsx (shared primitives)
├── Spinner, Button, Badge, Card, PageHeader, EmptyState
├── Toast, Modal, Tabs, StatusBadge
└── consumed by: ALL feature pages, layouts

components/PWAInstallPrompt.jsx
├── imports from: src/main.jsx (getPWAInstallPrompt)
└── consumed by: (standalone, add to App.jsx if needed)

hooks/index.js
├── useToast → wraps uiStore.showToast
├── useApi → generic API call wrapper
├── usePolling → DocumentDetailPage (3s interval)
├── useDebounce → DocumentListPage, AdminUsersPage, AdminDocumentsPage
└── useLocalStorage → PWAInstallPrompt
```

---

## 6. Route Dependency Map

```
App.jsx routes → Component → API Modules consumed → Stores consumed
/login              LoginPage              authApi                authStore
/register           RegisterPage           authApi                authStore
/forgot-password    ForgotPasswordPage     authApi                —
/unauthorized       UnauthorizedPage       —                      —
/dashboard          DashboardPage          analyticsApi           authStore
/documents          DocumentListPage       documentsApi           documentStore
/documents/upload   DocumentUploadPage     documentsApi           documentStore
/documents/:id      DocumentDetailPage     documentsApi           documentStore
/documents/:id/summary SummaryPage        summariesApi, documentsApi —
/flashcards         FlashcardSetListPage   flashcardsApi, documentsApi flashcardStore
/flashcards/:id/review FlashcardReviewPage flashcardsApi          flashcardStore
/quizzes            QuizListPage           quizApi                quizStore
/quizzes/generate   QuizGeneratePage       quizApi, documentsApi  —
/quizzes/attempts/:id QuizAttemptPage      quizApi                quizStore
/quizzes/attempts/:id/result QuizResultPage quizApi              —
/chatbot            ChatbotPage            chatbotApi             chatStore, authStore
/planner            StudyPlannerPage       plannerApi             —
/analytics          AnalyticsPage          analyticsApi           —
/admin              AdminDashboardPage     adminApi               —
/admin/users        AdminUsersPage         adminApi               —
/admin/documents    AdminDocumentsPage     adminApi               —
/admin/queue        QueueMonitorPage       adminApi               —
/admin/logs         AdminLogsPage          adminApi               —
*                   NotFoundPage           —                      —
```

---

## 7. Layout Hierarchy

```
AuthLayout (src/app/layouts/AuthLayout.jsx)
├── Used for: /login, /register, /forgot-password
├── Redirects authenticated users → /dashboard
└── Children via: <Outlet>

MainLayout (src/app/layouts/MainLayout.jsx)
├── Used for: all authenticated routes
├── Sidebar: NavLink items for student + admin roles
├── Breadcrumbs: auto-generated from pathname
├── Toast: from uiStore
├── Global loading: from uiStore.globalLoading
└── Children via: <Outlet>
```

---

## 8. PWA Dependency Map

```
vite.config.js
└── VitePWA({ strategies: 'injectManifest', srcDir: 'src', filename: 'sw.js' })
    └── At build time, injects self.__WB_MANIFEST into src/sw.js

src/sw.js (custom Workbox service worker)
├── imports from: workbox-precaching, workbox-routing, workbox-strategies,
│                 workbox-expiration, workbox-cacheable-response
└── Reads: self.__WB_MANIFEST (injected by vite-plugin-pwa at build)

public/manifest.json
├── icons: /icons/icon-{72,96,128,144,152,192,384,512}x*.png
├── shortcuts: /documents/upload, /chatbot, /quizzes
└── linked from: index.html <link rel="manifest">

src/main.jsx
├── captures: window.addEventListener('beforeinstallprompt')
├── exposes: getPWAInstallPrompt() global function
└── triggers: CustomEvent 'pwa-installable' / 'pwa-installed'

components/PWAInstallPrompt.jsx
├── listens for: 'pwa-installable', 'pwa-installed' events
└── calls: getPWAInstallPrompt().prompt() on user click
```

---

## 9. Circular Import Analysis

**Result: NO circular dependencies detected.**

Dependency flow is strictly hierarchical:

```
pages → api modules → api/client.js → [no further imports]
pages → stores → zustand → [no further imports]
pages → components/ui → react → [no further imports]
pages → hooks → stores → [checked above, clean]
App.jsx → layouts → pages → [checked above, clean]
```

The only potential concern is `api/client.js` importing from `authStore` — this is
a deliberate one-way dependency (store → never imports from api).

---

## 10. Inter-Team Interface Contracts

### U4 (Frontend) expects from U1 (Backend/Auth):
- `POST /api/auth/login` → `{ user: {...}, token: string }`
- `GET /api/auth/me` → `{ id, name, email, role }`
- `POST /api/auth/register` → `{ user, token }`
- `POST /api/auth/forgot-password` → `{ message }`
- Laravel Sanctum token passed as `Authorization: Bearer <token>`

### U4 expects from U2 (AI Pipeline):
- `GET /api/documents/:id/status` → `{ status: 'pending'|'processing'|'done'|'failed' }`
- `POST /api/summaries/generate` → `{ content: string, type: string }`
- `POST /api/chat/sessions/:id/messages` → SSE stream OR `{ message: { content, citations } }`

### U4 expects from U3 (Feature Backend):
- `GET /api/documents` → `[{ id, title, filename, mime_type, processing_status, size_bytes, created_at }]`
- `GET /api/quizzes/attempts/:id` → `{ id, quiz, questions: [...], time_limit_seconds }`
- `POST /api/quizzes/attempts/:id/submit` → `{ score, total_questions }`
- `GET /api/analytics/dashboard` → `{ stats, weekly_study_minutes, recent_documents, ... }`
- All paginated responses: `{ data: [...], meta: { total, per_page, current_page } }`

---

## 12. Source File Reference Index

For exact file paths corresponding to the store and module names referenced above:

| Logical Name | Exact File Path |
|---------------|-----------------|
| authStore | `src/store/authStore.js` |
| uiStore | `src/store/uiStore.js` |
| documentStore, chatStore, quizStore, flashcardStore | `src/store/featureStores.js` |
| apiClient | `src/api/client.js` |

API module test coverage is verified in `src/__tests__/api/ApiIntegration.test.js`, which asserts every method listed in Section 4 exists on its respective module export.

---

## 13. Environment Variables


| Variable | Required | Default | Used In |
|----------|----------|---------|---------|
| `VITE_API_PROXY_TARGET` | Dev only | `http://backend:8000` | vite.config.js proxy |
| `VITE_API_BASE_URL` | Prod only | `/api` | ChatbotPage (SSE URL) |

No secrets are embedded in frontend source. All API keys live on the Laravel backend.
