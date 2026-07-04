import { Suspense, lazy } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'

import AuthProvider from './AuthProvider'
import ErrorBoundary from './ErrorBoundary'
import AuthLayout from './layouts/AuthLayout'
import MainLayout from './layouts/MainLayout'
import ProtectedRoute from './ProtectedRoute'
import { Spinner } from '../components/ui'

// ── Auth ─────────────────────────────────────────────────────────────────
import LoginPage from '../features/auth/pages/LoginPage'
import RegisterPage from '../features/auth/pages/RegisterPage'
import ForgotPasswordPage from '../features/auth/pages/ForgotPasswordPage'

// ── Core pages (eager — always needed) ───────────────────────────────────
import DashboardPage from '../features/dashboard/pages/DashboardPage'
import { NotFoundPage, UnauthorizedPage } from '../features/errors/pages/ErrorPages'

// ── Lazy-loaded feature pages ─────────────────────────────────────────────
const DocumentListPage     = lazy(() => import('../features/documents/pages/DocumentListPage'))
const DocumentUploadPage   = lazy(() => import('../features/documents/pages/DocumentUploadPage'))
const DocumentDetailPage   = lazy(() => import('../features/documents/pages/DocumentDetailPage'))
const SummaryPage          = lazy(() => import('../features/summaries/pages/SummaryPage'))

const FlashcardSetListPage = lazy(() => import('../features/flashcards/pages/FlashcardSetListPage'))
const FlashcardReviewPage  = lazy(() => import('../features/flashcards/pages/FlashcardReviewPage'))

const QuizListPage         = lazy(() => import('../features/quiz/pages/QuizListPage'))
const QuizGeneratePage     = lazy(() => import('../features/quiz/pages/QuizGeneratePage'))
const QuizAttemptPage      = lazy(() => import('../features/quiz/pages/QuizAttemptPage'))
const QuizResultPage       = lazy(() => import('../features/quiz/pages/QuizResultPage'))

const ChatbotPage          = lazy(() => import('../features/chatbot/pages/ChatbotPage'))
const StudyPlannerPage     = lazy(() => import('../features/planner/pages/StudyPlannerPage'))
const AnalyticsPage        = lazy(() => import('../features/analytics/pages/AnalyticsPage'))

// Admin (admin-only, lazy)
const AdminDashboardPage   = lazy(() => import('../features/admin/pages/AdminDashboardPage'))
const AdminUsersPage       = lazy(() => import('../features/admin/pages/AdminUsersPage'))
const AdminDocumentsPage   = lazy(() => import('../features/admin/pages/AdminDocumentsPage'))
const QueueMonitorPage     = lazy(() => import('../features/admin/pages/QueueMonitorPage'))
const AdminLogsPage        = lazy(() => import('../features/admin/pages/AdminLogsPage'))

// ── Fallback while lazy chunk loads ──────────────────────────────────────
function PageLoader() {
  return (
    <div className="flex items-center justify-center py-20">
      <Spinner size="lg" />
    </div>
  )
}

export default function App() {
  return (
    <ErrorBoundary>
      <AuthProvider>
        <Suspense fallback={<PageLoader />}>
          <Routes>
            {/* ── Public: unauthenticated pages ──────────────────────── */}
            <Route element={<AuthLayout />}>
              <Route path="/login"            element={<LoginPage />} />
              <Route path="/register"         element={<RegisterPage />} />
              <Route path="/forgot-password"  element={<ForgotPasswordPage />} />
            </Route>

            {/* ── Error pages (no layout required) ──────────────────── */}
            <Route path="/unauthorized" element={<UnauthorizedPage />} />

            {/* ── Protected: requires authentication ─────────────────── */}
            <Route element={<ProtectedRoute />}>
              <Route element={<MainLayout />}>

                {/* Dashboard */}
                <Route path="/dashboard" element={<DashboardPage />} />

                {/* Documents */}
                <Route path="/documents"              element={<DocumentListPage />} />
                <Route path="/documents/upload"       element={<DocumentUploadPage />} />
                <Route path="/documents/:id"          element={<DocumentDetailPage />} />
                <Route path="/documents/:id/summary"  element={<SummaryPage />} />

                {/* Flash Cards */}
                <Route path="/flashcards"                element={<FlashcardSetListPage />} />
                <Route path="/flashcards/:setId/review" element={<FlashcardReviewPage />} />

                {/* Quiz */}
                <Route path="/quizzes"                            element={<QuizListPage />} />
                <Route path="/quizzes/generate"                   element={<QuizGeneratePage />} />
                <Route path="/quizzes/attempts/:attemptId"        element={<QuizAttemptPage />} />
                <Route path="/quizzes/attempts/:attemptId/result" element={<QuizResultPage />} />

                {/* AI Chatbot */}
                <Route path="/chatbot" element={<ChatbotPage />} />

                {/* Study Planner */}
                <Route path="/planner" element={<StudyPlannerPage />} />

                {/* Analytics */}
                <Route path="/analytics" element={<AnalyticsPage />} />

                {/* ── Admin-only routes ──────────────────────────────── */}
                <Route element={<ProtectedRoute roles={['admin']} />}>
                  <Route path="/admin"           element={<AdminDashboardPage />} />
                  <Route path="/admin/users"     element={<AdminUsersPage />} />
                  <Route path="/admin/documents" element={<AdminDocumentsPage />} />
                  <Route path="/admin/queue"     element={<QueueMonitorPage />} />
                  <Route path="/admin/logs"      element={<AdminLogsPage />} />
                </Route>

              </Route>
            </Route>

            {/* ── Root redirect ──────────────────────────────────────── */}
            <Route path="/"  element={<Navigate to="/dashboard" replace />} />

            {/* ── 404 ────────────────────────────────────────────────── */}
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
      </AuthProvider>
    </ErrorBoundary>
  )
}
