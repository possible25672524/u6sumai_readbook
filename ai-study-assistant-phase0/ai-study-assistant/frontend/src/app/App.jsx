import { Routes, Route, Navigate } from 'react-router-dom'

import AuthLayout from './layouts/AuthLayout'
import MainLayout from './layouts/MainLayout'
import ProtectedRoute from './ProtectedRoute'

import LoginPage from '../features/auth/pages/LoginPage'
import RegisterPage from '../features/auth/pages/RegisterPage'
import ForgotPasswordPage from '../features/auth/pages/ForgotPasswordPage'

import DashboardPage from '../features/dashboard/pages/DashboardPage'

import DocumentListPage from '../features/documents/pages/DocumentListPage'
import DocumentUploadPage from '../features/documents/pages/DocumentUploadPage'
import DocumentDetailPage from '../features/documents/pages/DocumentDetailPage'

import SummaryPage from '../features/summaries/pages/SummaryPage'

import FlashcardSetListPage from '../features/flashcards/pages/FlashcardSetListPage'
import FlashcardReviewPage from '../features/flashcards/pages/FlashcardReviewPage'

import QuizListPage from '../features/quiz/pages/QuizListPage'
import QuizGeneratePage from '../features/quiz/pages/QuizGeneratePage'
import QuizAttemptPage from '../features/quiz/pages/QuizAttemptPage'
import QuizResultPage from '../features/quiz/pages/QuizResultPage'

import ChatbotPage from '../features/chatbot/pages/ChatbotPage'
import StudyPlannerPage from '../features/planner/pages/StudyPlannerPage'
import AnalyticsPage from '../features/analytics/pages/AnalyticsPage'

import AdminUsersPage from '../features/admin/pages/AdminUsersPage'
import AdminDocumentsPage from '../features/admin/pages/AdminDocumentsPage'
import AdminLogsPage from '../features/admin/pages/AdminLogsPage'

export default function App() {
  return (
    <Routes>
      {/* Public: auth pages */}
      <Route element={<AuthLayout />}>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      </Route>

      {/* Protected: ต้อง login (ทุก role) */}
      <Route element={<ProtectedRoute />}>
        <Route element={<MainLayout />}>
          <Route path="/dashboard" element={<DashboardPage />} />

          <Route path="/documents" element={<DocumentListPage />} />
          <Route path="/documents/upload" element={<DocumentUploadPage />} />
          <Route path="/documents/:id" element={<DocumentDetailPage />} />
          <Route path="/documents/:id/summary" element={<SummaryPage />} />

          <Route path="/flashcards" element={<FlashcardSetListPage />} />
          <Route path="/flashcards/:setId/review" element={<FlashcardReviewPage />} />

          <Route path="/quizzes" element={<QuizListPage />} />
          <Route path="/quizzes/generate" element={<QuizGeneratePage />} />
          <Route path="/quizzes/attempts/:attemptId" element={<QuizAttemptPage />} />
          <Route path="/quizzes/attempts/:attemptId/result" element={<QuizResultPage />} />

          <Route path="/chatbot" element={<ChatbotPage />} />
          <Route path="/planner" element={<StudyPlannerPage />} />
          <Route path="/analytics" element={<AnalyticsPage />} />

          {/* Protected: เฉพาะ admin */}
          <Route element={<ProtectedRoute roles={['admin']} />}>
            <Route path="/admin" element={<AdminUsersPage />} />
            <Route path="/admin/documents" element={<AdminDocumentsPage />} />
            <Route path="/admin/logs" element={<AdminLogsPage />} />
          </Route>
        </Route>
      </Route>

      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}
