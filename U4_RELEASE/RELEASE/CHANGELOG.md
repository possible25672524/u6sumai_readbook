# Changelog — AI Study Assistant Frontend

All notable changes to the frontend are documented in this file.

---

## [2.0.0] — 2026-06-28 (Phase 2 Release)

### Added — Authentication
- `AuthProvider.jsx` — validates persisted token on app boot via `/auth/me`
- `ProtectedRoute.jsx` — role-based route guards (admin/teacher/student)
- `ErrorBoundary.jsx` — class component with dev stack trace and recover action
- `RegisterPage.jsx` — full registration with validation
- `ForgotPasswordPage.jsx` — password reset request flow

### Added — Core Infrastructure
- `App.jsx` — complete route tree (24 routes, lazy-loaded features)
- `AuthLayout.jsx` — branded auth wrapper with gradient background
- `MainLayout.jsx` — sidebar navigation + breadcrumbs + Toast + role badge
- `components/ui/index.jsx` — shared primitives (Button, Card, Badge, Modal, Toast, Tabs, Spinner, StatusBadge, EmptyState, PageHeader)
- `hooks/index.js` — useToast, useApi, usePolling, useDebounce, useLocalStorage
- `store/featureStores.js` — documentStore, chatStore, quizStore, flashcardStore
- Enhanced `store/authStore.js` — adds `initializing`, `updateUser`, `isAdmin()`, `isTeacher()`
- Enhanced `store/uiStore.js` — adds `globalLoading`

### Added — Feature Pages
- `DocumentUploadPage.jsx` — drag-drop, URL import, upload progress bar
- `DocumentListPage.jsx` — search, delete, quick actions
- `DocumentDetailPage.jsx` — processing status polling (3s interval), step-by-step indicator
- `SummaryPage.jsx` — 7 AI summary types, generate on demand
- `FlashcardSetListPage.jsx` — generate from document modal
- `FlashcardReviewPage.jsx` — flip animation, spaced repetition (again/good/easy)
- `QuizListPage.jsx` — list all quizzes, start attempt
- `QuizGeneratePage.jsx` — 5 question types, difficulty, count slider
- `QuizAttemptPage.jsx` — timed quiz, flagging, navigation dots
- `QuizResultPage.jsx` — score circle SVG, answer review with explanations
- `ChatbotPage.jsx` — SSE streaming, conversation history, inline citation badges
- `StudyPlannerPage.jsx` — mini calendar, grouped task list, AI plan creation
- `AnalyticsPage.jsx` — SVG line charts, donut charts, subject breakdown
- `DashboardPage.jsx` — greeting, 4 stat cards, bar chart, recent docs/quizzes
- `AdminDashboardPage.jsx` — system stats, quick nav
- `AdminUsersPage.jsx` — table with role change modal, delete
- `AdminDocumentsPage.jsx` — all documents across users, search, delete
- `QueueMonitorPage.jsx` — live job monitor (auto-refresh 5s), retry failed jobs
- `ErrorPages.jsx` — NotFoundPage (404), UnauthorizedPage

### Added — PWA
- `public/manifest.json` — full PWA manifest with shortcuts
- `src/sw.js` — Workbox service worker (Cache-first static, Network-first API, offline fallback)
- `public/offline.html` — styled offline page with auto-reconnect
- `public/icons/icon-{72..512}.png` — full icon set (8 sizes)
- `public/pwa-192x192.png`, `public/pwa-512x512.png`
- `components/PWAInstallPrompt.jsx` — install banner
- Updated `index.html` — manifest link + Apple meta tags

### Added — Tests (7 files, 65+ test cases)
- `__tests__/stores/authStore.test.js`
- `__tests__/stores/featureStores.test.js`
- `__tests__/routes/ProtectedRoute.test.jsx`
- `__tests__/routes/RouteIntegration.test.jsx`
- `__tests__/pages/DashboardPage.test.jsx`
- `__tests__/pages/ChatbotPage.test.jsx`
- `__tests__/api/ApiIntegration.test.js`

### Added — Admin API
- `api/admin.js` — listUsers, updateUser, deleteUser, listDocuments, deleteDocument, listJobs, retryJob, listLogs, stats

### Fixed (Defects resolved during validation)
- **D1** `featureStores.setCurrent` — upgraded to function updater pattern
- **D2** `featureStores.setSets` — upgraded to function updater pattern  
- **D3** `featureStores.setSessions` — upgraded to function updater pattern
- **D4** `App.jsx` — removed duplicate Toast render (was also in MainLayout)
- **D5** `ChatbotPage.jsx` — removed unused `apiClient` import
- **D6** `ProtectedRoute.test.jsx` — fixed relative import paths (`../../app/`)
- **D7** `featureStores.test.js` — fixed relative import paths (`../../store/`)
- **D8** `PWAInstallPrompt.jsx` — fixed main.jsx import path (`../main`)
- **D9** `ErrorPages.jsx` — fixed ui import path (`../../../components/ui`)

---

## [1.0.0] — 2026-06-22 (Phase 0 Base)

### Added
- Docker Compose project scaffold
- React + Vite + PWA base setup
- API client modules (auth, documents, quiz, flashcards, summaries, chatbot, planner, analytics)
- Zustand stores (authStore, uiStore)
- LoginPage (fully wired reference implementation)
- Route skeleton with placeholder pages
- App.jsx with basic routing structure
