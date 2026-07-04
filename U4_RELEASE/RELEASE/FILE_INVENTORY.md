# File Inventory — AI Study Assistant Frontend Phase 2

**Team:** U4 (Frontend Lead)  
**Version:** 2.0.0  
**Date:** 2026-06-28  
**Total Files:** 80 (57 source · 16 public/assets · 7 test files)  
**Total Lines:** ~7,056  

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ New | Created by U4 Phase 2 |
| 🔄 Enhanced | Modified from Phase 0 base |
| 📦 Phase 0 | Unchanged from Phase 0 scaffold |

---

## 1. Application Shell

| File | Status | Purpose | Depends On |
|------|--------|---------|-----------|
| `src/main.jsx` | 🔄 Enhanced | React root entry point. Mounts `<BrowserRouter><App/>`. Captures `beforeinstallprompt` for PWA. | react, react-dom, react-router-dom |
| `src/app/App.jsx` | ✅ New | Root route tree — 25 routes, lazy imports, ProtectedRoute guards, AuthProvider wrapper | react, react-router-dom, all pages |
| `src/app/AuthProvider.jsx` | ✅ New | Validates persisted JWT on app boot via `GET /auth/me`. Shows spinner during init. Redirects unauthenticated users. | authStore, authApi |
| `src/app/ErrorBoundary.jsx` | ✅ New | Class component catching React render errors. Dev stack trace. Recover + home buttons. | react |
| `src/app/ProtectedRoute.jsx` | 🔄 Enhanced | Outlet-based guard. Unauthenticated → `/login`. Role mismatch → `/unauthorized`. | react-router-dom, authStore |

---

## 2. Layouts

| File | Status | Purpose | Depends On |
|------|--------|---------|-----------|
| `src/app/layouts/MainLayout.jsx` | 🔄 Enhanced | Application shell with sidebar navigation (NavLink), breadcrumbs hook, Toast, role badge, global loading indicator. Admin nav section visible to admin role only. | react-router-dom, authStore, uiStore, ui/index.jsx |
| `src/app/layouts/AuthLayout.jsx` | 🔄 Enhanced | Branded auth wrapper. Gradient background. Redirects authenticated users to `/dashboard`. | react-router-dom, authStore |

---

## 3. Shared Components

| File | Status | Purpose | Exports | Depends On |
|------|--------|---------|---------|-----------|
| `src/components/ui/index.jsx` | ✅ New | Shared UI primitives library. All Tailwind-only. | Spinner, Button, Badge, Card, PageHeader, EmptyState, Toast, Modal, Tabs, StatusBadge | react |
| `src/components/PWAInstallPrompt.jsx` | ✅ New | Captures `beforeinstallprompt`. Shows dismissible install banner. Persists dismiss to localStorage. | main.jsx (getPWAInstallPrompt) |

---

## 4. Custom Hooks

| File | Status | Purpose | Exports | Depends On |
|------|--------|---------|---------|-----------|
| `src/hooks/index.js` | ✅ New | Reusable hook library | useToast, useApi, usePolling, useDebounce, useLocalStorage | react, uiStore |

---

## 5. Zustand Stores

| File | Status | Purpose | State Shape | Depends On |
|------|--------|---------|-------------|-----------|
| `src/store/authStore.js` | 🔄 Enhanced | Auth session. JWT persistence via `persist` middleware. Role helpers. | `user, token, isAuthenticated, initializing` | zustand |
| `src/store/uiStore.js` | 🔄 Enhanced | Global UI state | `sidebarOpen, toast, globalLoading` | zustand |
| `src/store/featureStores.js` | ✅ New | Four feature domain stores: document upload/processing, chat streaming, quiz attempt, flashcard review | `documentStore, chatStore, quizStore, flashcardStore` | zustand |

---

## 6. API Client Modules

| File | Status | Purpose | Key Methods | Depends On |
|------|--------|---------|------------|-----------|
| `src/api/client.js` | 📦 Phase 0 | Axios instance. Auth header interceptor. 401 logout handler. | `apiClient` | axios, authStore |
| `src/api/auth.js` | 📦 Phase 0 | Authentication endpoints | `login, register, logout, me, forgotPassword, resetPassword` | client.js |
| `src/api/documents.js` | 📦 Phase 0 | Document CRUD + upload | `list, get, upload, importFromUrl, delete, processingStatus` | client.js |
| `src/api/quiz.js` | 📦 Phase 0 | Quiz lifecycle | `generate, listQuizzes, startAttempt, submitAttempt, getResult` | client.js |
| `src/api/flashcards.js` | 📦 Phase 0 | Flashcard sets + review | `generate, listSets, getSet, review` | client.js |
| `src/api/summaries.js` | 📦 Phase 0 | AI summaries | `generate, list` | client.js |
| `src/api/chatbot.js` | 📦 Phase 0 | Chatbot sessions + messages | `listSessions, createSession, sendMessage, quickAnswer` | client.js |
| `src/api/planner.js` | 📦 Phase 0 | Study plan management | `getPlan, createPlan, markItemDone` | client.js |
| `src/api/analytics.js` | 📦 Phase 0 | Learning analytics | `dashboard, studyTime, examPrediction` | client.js |
| `src/api/admin.js` | ✅ New | Admin management API | `listUsers, getUser, updateUser, deleteUser, listDocuments, deleteDocument, listJobs, retryJob, listLogs, stats` | client.js |

---

## 7. Feature Pages — Authentication

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/auth/pages/LoginPage.jsx` | 📦 Phase 0 | `/login` | Email/password login form. Sanctum token storage. | authApi, authStore |
| `src/features/auth/pages/RegisterPage.jsx` | ✅ New | `/register` | Registration form with validation and server error mapping. | authApi, authStore |
| `src/features/auth/pages/ForgotPasswordPage.jsx` | ✅ New | `/forgot-password` | Password reset request. Shows sent confirmation state. | authApi |

---

## 8. Feature Pages — Documents

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/documents/pages/DocumentListPage.jsx` | ✅ New | `/documents` | Searchable document list. Delete. Quick actions (summarise/quiz/chatbot). | documentsApi, documentStore, hooks |
| `src/features/documents/pages/DocumentUploadPage.jsx` | ✅ New | `/documents/upload` | Drag-and-drop + URL import upload. Progress bar. Processing info. | documentsApi, documentStore |
| `src/features/documents/pages/DocumentDetailPage.jsx` | ✅ New | `/documents/:id` | Step-by-step processing indicator with 3 s polling. Document metadata. Excerpt. | documentsApi, documentStore, usePolling |
| `src/features/summaries/pages/SummaryPage.jsx` | ✅ New | `/documents/:id/summary` | 7 AI summary types. On-demand generation. Cached results per type. | summariesApi, documentsApi |

---

## 9. Feature Pages — Learning (Quiz + Flashcards)

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/quiz/pages/QuizListPage.jsx` | 🔄 Enhanced | `/quizzes` | Fetched quiz list. Start attempt button. | quizApi, quizStore |
| `src/features/quiz/pages/QuizGeneratePage.jsx` | ✅ New | `/quizzes/generate` | Form: pick document, question type, count (5–50), difficulty. Calls Claude via backend. | quizApi, documentsApi |
| `src/features/quiz/pages/QuizAttemptPage.jsx` | 🔄 Enhanced | `/quizzes/attempts/:attemptId` | Timed quiz engine. Navigation dots. Flag questions. Short-answer mode. | quizApi, quizStore |
| `src/features/quiz/pages/QuizResultPage.jsx` | ✅ New | `/quizzes/attempts/:attemptId/result` | Animated SVG score circle. Per-question review with explanations. | quizApi |
| `src/features/flashcards/pages/FlashcardSetListPage.jsx` | ✅ New | `/flashcards` | Set list with due count. Generate-from-document modal. | flashcardsApi, flashcardStore |
| `src/features/flashcards/pages/FlashcardReviewPage.jsx` | ✅ New | `/flashcards/:setId/review` | CSS 3D flip animation. Spaced repetition (again/good/easy). Session completion stats. | flashcardsApi, flashcardStore |

---

## 10. Feature Pages — AI Chatbot

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/chatbot/pages/ChatbotPage.jsx` | 🔄 Enhanced | `/chatbot` | Sessions sidebar. SSE streaming with fetch + typewriter fallback. Inline citation badges (`[1]`). Citation source list per message. | chatbotApi, chatStore, authStore |

---

## 11. Feature Pages — Planning

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/planner/pages/StudyPlannerPage.jsx` | 🔄 Enhanced | `/planner` | AI plan creation modal (exam date + topics). Mini SVG-free calendar component. Date-grouped task list. Mark done. | plannerApi |

---

## 12. Feature Pages — Analytics & Dashboard

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/dashboard/pages/DashboardPage.jsx` | ✅ New | `/dashboard` | Greeting, 4 stat cards, bar chart (weekly study), recent documents, recent quiz results, upcoming plan items, quick-action grid. | analyticsApi, authStore |
| `src/features/analytics/pages/AnalyticsPage.jsx` | 🔄 Enhanced | `/analytics` | Period tabs (week/month/all). SVG line charts (study time, quiz scores). Donut charts (mastery/pass-rate). Subject bar chart. | analyticsApi |

---

## 13. Feature Pages — Admin (role=admin only)

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/admin/pages/AdminDashboardPage.jsx` | ✅ New | `/admin` | System stats cards. Quick navigation to sub-modules. | adminApi |
| `src/features/admin/pages/AdminUsersPage.jsx` | 🔄 Enhanced | `/admin/users` | Searchable user table. Role-change modal. Delete with confirm. | adminApi, hooks |
| `src/features/admin/pages/AdminDocumentsPage.jsx` | ✅ New | `/admin/documents` | All-users document table. Search. Delete. | adminApi, hooks |
| `src/features/admin/pages/QueueMonitorPage.jsx` | ✅ New | `/admin/queue` | Job status cards. Filter tabs. Auto-refresh every 5 s. Retry failed jobs. | adminApi |
| `src/features/admin/pages/AdminLogsPage.jsx` | 🔄 Enhanced | `/admin/logs` | Activity log table. Search by user/action. Colour-coded action badges. | adminApi, hooks |

---

## 14. Error Pages

| File | Status | Route | Purpose | Depends On |
|------|--------|-------|---------|-----------|
| `src/features/errors/pages/ErrorPages.jsx` | ✅ New | `/unauthorized`, `*` | Named exports: `NotFoundPage` (404), `UnauthorizedPage` (403). Back and home buttons. | react-router-dom, ui/index.jsx |

---

## 15. Progressive Web App (PWA)

| File | Status | Purpose |
|------|--------|---------|
| `src/sw.js` | ✅ New | Workbox injectManifest service worker. Cache-first static assets, Network-first API, offline fallback to `offline.html`. |
| `public/manifest.json` | ✅ New | Full PWA manifest — name, icons (8 sizes), shortcuts (upload/chatbot/quiz), categories |
| `public/offline.html` | ✅ New | Branded offline page. Auto-reconnect check every 10 s. Redirects when online. |
| `public/favicon.svg` | 📦 Phase 0 | SVG favicon |
| `public/icons.svg` | 📦 Phase 0 | Icon source SVG |
| `public/icons/icon.svg` | ✅ New | Generated app icon SVG |
| `public/icons/icon-72x72.png` | ✅ New | PWA icon 72 × 72 |
| `public/icons/icon-96x96.png` | ✅ New | PWA icon 96 × 96 |
| `public/icons/icon-128x128.png` | ✅ New | PWA icon 128 × 128 |
| `public/icons/icon-144x144.png` | ✅ New | PWA icon 144 × 144 |
| `public/icons/icon-152x152.png` | ✅ New | PWA icon 152 × 152 |
| `public/icons/icon-192x192.png` | ✅ New | PWA icon 192 × 192 |
| `public/icons/icon-384x384.png` | ✅ New | PWA icon 384 × 384 |
| `public/icons/icon-512x512.png` | ✅ New | PWA icon 512 × 512 |
| `public/pwa-192x192.png` | ✅ New | Vite PWA plugin icon 192 × 192 |
| `public/pwa-512x512.png` | ✅ New | Vite PWA plugin icon 512 × 512 |

---

## 16. Config & Entry Files

| File | Status | Purpose |
|------|--------|---------|
| `frontend/index.html` | 🔄 Enhanced | HTML entry — manifest link, Apple PWA meta tags, theme-color |
| `frontend/vite.config.js` | 🔄 Enhanced | Vite 8 + VitePWA (injectManifest), dev proxy `/api → backend:8000` |
| `frontend/package.json` | 📦 Phase 0 | NPM manifest — React 19, Vite 8, Tailwind 4, Zustand 5, react-router-dom 7 |
| `src/index.css` | 📦 Phase 0 | Tailwind CSS v4 directives |
| `src/assets/.gitkeep` | 📦 Phase 0 | Placeholder to track empty assets directory |

---

## 17. Test Files

| File | Status | Tests | Coverage |
|------|--------|-------|---------|
| `src/__tests__/stores/authStore.test.js` | ✅ New | 8 | setSession, logout, hasRole, isAdmin, updateUser, initial state |
| `src/__tests__/stores/featureStores.test.js` | ✅ New | 12 | documentStore CRUD, chatStore streaming, quizStore attempt |
| `src/__tests__/routes/ProtectedRoute.test.jsx` | ✅ New | 5 | Auth redirect, role guard pass/fail, unauthorized redirect |
| `src/__tests__/routes/RouteIntegration.test.jsx` | ✅ New | 20+ | All page module exports, store init, public/protected routing |
| `src/__tests__/pages/DashboardPage.test.jsx` | ✅ New | 6 | Loading state, greeting, stat values, error handling, quick actions |
| `src/__tests__/pages/ChatbotPage.test.jsx` | ✅ New | 5 | Render, empty state, session UI, streaming store operations |
| `src/__tests__/api/ApiIntegration.test.js` | ✅ New | 9 | All 10 API module method signatures |

---

## 18. Release Documentation

| File | Purpose |
|------|---------|
| `README.md` | Project overview, quick start, architecture diagram, feature table |
| `CHANGELOG.md` | Full v2.0.0 changelog including v1.0.0 base |
| `IMPLEMENTATION_REPORT.md` | Detailed implementation account, decisions, defects fixed |
| `VALIDATION_REPORT.md` | Automated audit results, PWA checks, route coverage |
| `ACCEPTANCE_REPORT.md` | Formal AC verification — all 11 groups PASSED |
| `FILE_INVENTORY.md` | This file — complete file listing |
| `RELEASE_MANIFEST.md` | Machine-readable release summary |
| `DEPENDENCY_REPORT.md` | External and internal dependency analysis |
| `project_memory.md` | Project architecture single source of truth |

---

## Summary Counts

| Category | Count |
|----------|-------|
| App Shell | 5 |
| Layouts | 2 |
| Shared Components | 2 |
| Hooks | 1 |
| Zustand Stores | 3 |
| API Modules | 10 |
| Auth Pages | 3 |
| Document Pages | 4 |
| Learning Pages | 6 |
| AI Chatbot Page | 1 |
| Planning Page | 1 |
| Analytics Pages | 2 |
| Admin Pages | 5 |
| Error Pages | 1 |
| PWA Files | 16 |
| Config Files | 5 |
| Test Files | 7 |
| Documentation | 9 |
| **TOTAL** | **83** |
