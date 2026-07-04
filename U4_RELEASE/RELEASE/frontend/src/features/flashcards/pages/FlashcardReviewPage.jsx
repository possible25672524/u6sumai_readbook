import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { flashcardsApi } from '../../../api/flashcards'
import { useFlashcardStore } from '../../../store/featureStores'
import { Button, PageHeader, Spinner } from '../../../components/ui'
import { useToast } from '../../../hooks'

function FlipCard({ front, back, flipped, onClick }) {
  return (
    <div
      className="relative w-full cursor-pointer"
      style={{ perspective: 1200, height: 280 }}
      onClick={onClick}
    >
      <div
        className="relative w-full h-full transition-transform duration-500"
        style={{ transformStyle: 'preserve-3d', transform: flipped ? 'rotateY(180deg)' : 'rotateY(0deg)' }}
      >
        {/* Front */}
        <div
          className="absolute inset-0 flex items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 p-6"
          style={{ backfaceVisibility: 'hidden' }}
        >
          <p className="text-white text-lg font-medium text-center leading-relaxed">{front}</p>
          <span className="absolute bottom-4 text-indigo-200 text-xs">แตะเพื่อดูเฉลย</span>
        </div>
        {/* Back */}
        <div
          className="absolute inset-0 flex items-center justify-center rounded-2xl bg-white border-2 border-indigo-200 p-6"
          style={{ backfaceVisibility: 'hidden', transform: 'rotateY(180deg)' }}
        >
          <p className="text-slate-900 text-base text-center leading-relaxed">{back}</p>
        </div>
      </div>
    </div>
  )
}

const RATINGS = [
  { value: 'again', label: 'ไม่รู้', color: 'bg-red-100 text-red-700 border-red-200', emoji: '😓' },
  { value: 'good', label: 'พอรู้', color: 'bg-amber-100 text-amber-700 border-amber-200', emoji: '🤔' },
  { value: 'easy', label: 'รู้ดี', color: 'bg-green-100 text-green-700 border-green-200', emoji: '😊' },
]

export default function FlashcardReviewPage() {
  const { setId } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const { currentSet, cards, cardIndex, showAnswer, startReview, nextflipCard } = useFlashcardStore()

  const [loading, setLoading] = useState(true)
  const [ratingLoading, setRatingLoading] = useState(false)
  const [sessionStats, setSessionStats] = useState({ again: 0, good: 0, easy: 0 })
  const [done, setDone] = useState(false)

  useEffect(() => {
    flashcardsApi.getSet(setId)
      .then(({ data }) => {
        startReview(data, data.cards ?? [])
      })
      .catch(() => toast({ type: 'error', message: 'โหลด Flash Cards ไม่สำเร็จ' }))
      .finally(() => setLoading(false))
  }, [setId, startReview, toast])

  const handleRate = async (rating) => {
    const card = cards[cardIndex]
    if (!card) return
    setRatingLoading(true)
    try {
      await flashcardsApi.review(card.id, { result: rating })
      setSessionStats((s) => ({ ...s, [rating]: s[rating] + 1 }))
      if (cardIndex >= cards.length - 1) {
        setDone(true)
      } else {
        nextCard()
      }
    } catch {
      toast({ type: 'error', message: 'บันทึกผลไม่สำเร็จ' })
    } finally {
      setRatingLoading(false)
    }
  }

  if (loading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>

  if (done) {
    const total = sessionStats.again + sessionStats.good + sessionStats.easy
    return (
      <div className="max-w-md mx-auto text-center py-12">
        <div className="text-6xl mb-4">🎉</div>
        <h2 className="text-xl font-semibold text-slate-900 mb-2">ทบทวนครบแล้ว!</h2>
        <p className="text-slate-500 mb-6">{total} ใบ ใน session นี้</p>
        <div className="grid grid-cols-3 gap-3 mb-6">
          {RATINGS.map((r) => (
            <div key={r.value} className={`rounded-xl border p-3 ${r.color}`}>
              <p className="text-2xl">{r.emoji}</p>
              <p className="text-xl font-bold mt-1">{sessionStats[r.value]}</p>
              <p className="text-xs mt-0.5">{r.label}</p>
            </div>
          ))}
        </div>
        <Button onClick={() => navigate('/flashcards')} className="w-full">กลับรายการ</Button>
      </div>
    )
  }

  const card = cards[cardIndex]
  if (!card) return null

  return (
    <div className="max-w-lg mx-auto">
      <PageHeader
        title={currentSet?.title || 'Flash Cards'}
        subtitle={`${cardIndex + 1} / ${cards.length} ใบ`}
        action={<Button variant="ghost" onClick={() => navigate('/flashcards')}>← กลับ</Button>}
      />

      {/* Progress */}
      <div className="h-1.5 bg-slate-200 rounded-full mb-6 overflow-hidden">
        <div
          className="h-full bg-indigo-600 rounded-full transition-all"
          style={{ width: `${((cardIndex + 1) / cards.length) * 100}%` }}
        />
      </div>

      <FlipCard
        front={card.front}
        back={card.back}
        flipped={showAnswer}
        onClick={flipCard}
      />

      {/* Rating buttons — only shown after flip */}
      <div className={`mt-5 transition-all ${showAnswer ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}>
        <p className="text-xs text-center text-slate-400 mb-3">ประเมินความเข้าใจของคุณ</p>
        <div className="grid grid-cols-3 gap-3">
          {RATINGS.map((r) => (
            <button
              key={r.value}
              disabled={ratingLoading}
              onClick={() => handleRate(r.value)}
              className={`flex flex-col items-center gap-1 rounded-xl border p-3 text-sm font-medium transition-all hover:scale-105 disabled:opacity-50 ${r.color}`}
            >
              <span className="text-xl">{r.emoji}</span>
              {r.label}
            </button>
          ))}
        </div>
      </div>

      {!showAnswer && (
        <Button className="w-full mt-5" variant="outline" onClick={flipCard}>
          ดูเฉลย
        </Button>
      )}
    </div>
  )
}
