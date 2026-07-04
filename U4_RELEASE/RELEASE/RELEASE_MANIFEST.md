# Release Manifest — AI Study Assistant Frontend

---

## Release Metadata

| Field | Value |
|-------|-------|
| **Team** | U4 — Frontend Lead |
| **Version** | 2.0.0 |
| **Release Date** | 2026-06-28 |
| **Base** | Phase 0 (ai-study-assistant-phase0.zip) |
| **Branch Target** | `develop` |
| **Ready For Merge** | ✅ YES |

---

## Package Contents

```
RELEASE/
├── README.md
├── CHANGELOG.md
├── IMPLEMENTATION_REPORT.md
├── VALIDATION_REPORT.md
├── ACCEPTANCE_REPORT.md
├── FILE_INVENTORY.md
├── RELEASE_MANIFEST.md          ← this file
├── DEPENDENCY_REPORT.md
├── project_memory.md
└── frontend/
    ├── index.html
    ├── package.json
    ├── vite.config.js
    ├── public/
    │   ├── favicon.svg
    │   ├── icons.svg
    │   ├── manifest.json
    │   ├── offline.html
    │   ├── pwa-192x192.png
    │   ├── pwa-512x512.png
    │   └── icons/
    │       ├── icon.svg
    │       ├── icon-72x72.png
    │       ├── icon-96x96.png
    │       ├── icon-128x128.png
    │       ├── icon-144x144.png
    │       ├── icon-152x152.png
    │       ├── icon-192x192.png
    │       ├── icon-384x384.png
    │       └── icon-512x512.png
    └── src/
        ├── index.css
        ├── main.jsx
        ├── sw.js
        ├── assets/.gitkeep
        ├── api/
        │   ├── admin.js           ← NEW
        │   ├── analytics.js
        │   ├── auth.js
        │   ├── chatbot.js
        │   ├── client.js
        │   ├── documents.js
        │   ├── flashcards.js
        │   ├── planner.js
        │   ├── quiz.js
        │   └── summaries.js
        ├── app/
        │   ├── App.jsx            ← NEW (full replacement)
        │   ├── AuthProvider.jsx   ← NEW
        │   ├── ErrorBoundary.jsx  ← NEW
        │   ├── ProtectedRoute.jsx ← NEW (full replacement)
        │   └── layouts/
        │       ├── AuthLayout.jsx ← ENHANCED
        │       └── MainLayout.jsx ← NEW (full replacement)
        ├── components/
        │   ├── PWAInstallPrompt.jsx ← NEW
        │   └── ui/
        │       └── index.jsx        ← NEW
        ├── hooks/
        │   └── index.js             ← NEW
        ├── store/
        │   ├── authStore.js         ← ENHANCED
        │   ├── featureStores.js     ← NEW
        │   └── uiStore.js           ← ENHANCED
        ├── features/
        │   ├── admin/pages/
        │   │   ├── AdminDashboardPage.jsx  ← NEW
        │   │   ├── AdminDocumentsPage.jsx  ← NEW
        │   │   ├── AdminLogsPage.jsx       ← NEW (stub→full)
        │   │   ├── AdminUsersPage.jsx      ← NEW
        │   │   └── QueueMonitorPage.jsx    ← NEW
        │   ├── analytics/pages/
        │   │   └── AnalyticsPage.jsx       ← NEW
        │   ├── auth/pages/
        │   │   ├── ForgotPasswordPage.jsx  ← NEW
        │   │   ├── LoginPage.jsx           (Phase 0)
        │   │   └── RegisterPage.jsx        ← NEW
        │   ├── chatbot/pages/
        │   │   └── ChatbotPage.jsx         ← NEW
        │   ├── dashboard/pages/
        │   │   └── DashboardPage.jsx       ← NEW
        │   ├── documents/pages/
        │   │   ├── DocumentDetailPage.jsx  ← NEW
        │   │   ├── DocumentListPage.jsx    ← NEW
        │   │   └── DocumentUploadPage.jsx  ← NEW
        │   ├── errors/pages/
        │   │   └── ErrorPages.jsx          ← NEW
        │   ├── flashcards/pages/
        │   │   ├── FlashcardReviewPage.jsx ← NEW
        │   │   └── FlashcardSetListPage.jsx ← NEW
        │   ├── planner/pages/
        │   │   └── StudyPlannerPage.jsx    ← NEW
        │   ├── quiz/pages/
        │   │   ├── QuizAttemptPage.jsx     ← ENHANCED
        │   │   ├── QuizGeneratePage.jsx    ← NEW
        │   │   ├── QuizListPage.jsx        ← ENHANCED
        │   │   └── QuizResultPage.jsx      ← NEW
        │   └── summaries/pages/
        │       └── SummaryPage.jsx         ← NEW
        └── __tests__/
            ├── api/
            │   └── ApiIntegration.test.js  ← NEW
            ├── pages/
            │   ├── ChatbotPage.test.jsx    ← NEW
            │   └── DashboardPage.test.jsx  ← NEW
            ├── routes/
            │   ├── ProtectedRoute.test.jsx ← NEW
            │   └── RouteIntegration.test.jsx ← NEW
            └── stores/
                ├── authStore.test.js       ← NEW
                └── featureStores.test.js   ← NEW
```

---

## File Counts

| Category | Phase 0 | New | Enhanced | Total |
|----------|---------|-----|----------|-------|
| App Shell | 1 | 3 | 1 | 5 |
| Layouts | 0 | 1 | 1 | 2 |
| Components | 0 | 2 | 0 | 2 |
| Hooks | 0 | 1 | 0 | 1 |
| Stores | 2 | 1 | 2 | 3 |
| API Modules | 9 | 1 | 0 | 10 |
| Pages (all) | 1 | 26 | 4 | 31 |
| Tests | 0 | 7 | 0 | 7 |
| PWA | 2 | 14 | 0 | 16 |
| Config | 3 | 0 | 2 | 5 |
| Docs | 0 | 9 | 0 | 9 |
| **Total** | **18** | **65** | **10** | **91** |

---

## Routes Registered (25)

| Route | Component | Guard |
|-------|-----------|-------|
| `/login` | LoginPage | Public |
| `/register` | RegisterPage | Public |
| `/forgot-password` | ForgotPasswordPage | Public |
| `/unauthorized` | UnauthorizedPage | None |
| `/` | → /dashboard | Auth |
| `/dashboard` | DashboardPage | Auth |
| `/documents` | DocumentListPage | Auth |
| `/documents/upload` | DocumentUploadPage | Auth |
| `/documents/:id` | DocumentDetailPage | Auth |
| `/documents/:id/summary` | SummaryPage | Auth |
| `/flashcards` | FlashcardSetListPage | Auth |
| `/flashcards/:setId/review` | FlashcardReviewPage | Auth |
| `/quizzes` | QuizListPage | Auth |
| `/quizzes/generate` | QuizGeneratePage | Auth |
| `/quizzes/attempts/:attemptId` | QuizAttemptPage | Auth |
| `/quizzes/attempts/:attemptId/result` | QuizResultPage | Auth |
| `/chatbot` | ChatbotPage | Auth |
| `/planner` | StudyPlannerPage | Auth |
| `/analytics` | AnalyticsPage | Auth |
| `/admin` | AdminDashboardPage | Admin |
| `/admin/users` | AdminUsersPage | Admin |
| `/admin/documents` | AdminDocumentsPage | Admin |
| `/admin/queue` | QueueMonitorPage | Admin |
| `/admin/logs` | AdminLogsPage | Admin |
| `*` | NotFoundPage | None |

---

## Stores

| Store | File | State Keys |
|-------|------|-----------|
| authStore | store/authStore.js | user, token, isAuthenticated, initializing |
| uiStore | store/uiStore.js | sidebarOpen, toast, globalLoading |
| documentStore | store/featureStores.js | documents, current, uploadProgress, uploadStatus, processingStatus |
| chatStore | store/featureStores.js | sessions, currentSession, messages, streaming, streamText |
| quizStore | store/featureStores.js | quizzes, currentAttempt, answers, timeLeft |
| flashcardStore | store/featureStores.js | sets, currentSet, cards, cardIndex, showAnswer |

---

## Runtime Requirements

| Requirement | Version |
|-------------|---------|
| Node.js | ≥ 20.19.0 |
| npm | ≥ 8 |
| React | 19.x |
| Vite | 8.x |
| Zustand | 5.x |
| react-router-dom | 7.x |
| Tailwind CSS | 4.x |
| Docker | (for backend services) |

---

## Environment Variables Required

```env
VITE_API_PROXY_TARGET=http://backend:8000   # Docker Compose internal
VITE_API_BASE_URL=/api                       # Optional override for production
```

---

## Known Limitations

1. **Service Worker** requires `vite-plugin-pwa` to inject the Workbox manifest at build time. Dev mode uses `devOptions: { enabled: true }`.
2. **SSE Streaming** falls back to typewriter animation if backend sends JSON instead of `text/event-stream`. Full streaming requires backend implementation.
3. **Charts** are hand-coded SVG — no chart library. Complex visualisations should use Recharts in future phases.
4. **Thai font** is system default. Production build should add `@fontsource/noto-sans-thai` for consistent typography.
5. **Tests** use Vitest with `jsdom` environment. Install `@testing-library/react @testing-library/jest-dom jsdom` as devDependencies to run.

---

## Merge Instructions

```bash
# 1. Copy frontend/ over Phase 0 base (already merged in this package)
# 2. Start services
docker compose up -d --build

# 3. Verify frontend starts
docker compose logs -f frontend

# 4. Access
# Frontend: http://localhost:5173
# After backend Phase 1 (auth), login at http://localhost:5173/login
```
