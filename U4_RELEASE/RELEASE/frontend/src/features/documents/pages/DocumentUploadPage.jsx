import { useState, useRef, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { documentsApi } from '../../../api/documents'
import { useDocumentStore } from '../../../store/featureStores'
import { Button, Card, PageHeader } from '../../../components/ui'
import { useToast } from '../../../hooks'

const ACCEPTED_TYPES = [
  'application/pdf',
  'image/jpeg', 'image/png', 'image/webp',
  'audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/ogg',
  'video/mp4',
]
const MAX_SIZE_MB = 100

export default function DocumentUploadPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const fileInputRef = useRef(null)

  const { uploadProgress, uploadStatus, setUploadProgress, setUploadStatus, resetUpload, addDocument } =
    useDocumentStore()

  const [dragOver, setDragOver] = useState(false)
  const [selectedFile, setSelectedFile] = useState(null)
  const [fileError, setFileError] = useState(null)

  // URL import
  const [urlMode, setUrlMode] = useState(false)
  const [urlInput, setUrlInput] = useState('')
  const [urlLoading, setUrlLoading] = useState(false)

  const validateFile = (file) => {
    if (!ACCEPTED_TYPES.includes(file.type)) {
      return 'ไม่รองรับประเภทไฟล์นี้ รองรับ PDF, รูปภาพ, ไฟล์เสียง, วิดีโอ'
    }
    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
      return `ไฟล์ต้องมีขนาดไม่เกิน ${MAX_SIZE_MB} MB`
    }
    return null
  }

  const selectFile = useCallback((file) => {
    const err = validateFile(file)
    if (err) { setFileError(err); setSelectedFile(null); return }
    setFileError(null)
    setSelectedFile(file)
  }, [])

  const handleDrop = (e) => {
    e.preventDefault()
    setDragOver(false)
    const file = e.dataTransfer.files[0]
    if (file) selectFile(file)
  }

  const handleUpload = async () => {
    if (!selectedFile) return
    setUploadStatus('uploading')
    setUploadProgress(0)

    const formData = new FormData()
    formData.append('file', selectedFile)

    try {
      const { data } = await documentsApi.upload(formData, (evt) => {
        const pct = Math.round((evt.loaded / evt.total) * 100)
        setUploadProgress(pct)
      })
      addDocument(data)
      setUploadStatus('done')
      toast({ type: 'success', message: 'อัปโหลดสำเร็จ! กำลังประมวลผล...' })
      setTimeout(() => {
        resetUpload()
        navigate(`/documents/${data.id}`)
      }, 1200)
    } catch (err) {
      setUploadStatus('error')
      toast({ type: 'error', message: err.response?.data?.message || 'อัปโหลดไม่สำเร็จ' })
    }
  }

  const handleUrlImport = async () => {
    if (!urlInput.trim()) return
    setUrlLoading(true)
    try {
      const { data } = await documentsApi.importFromUrl({ url: urlInput })
      addDocument(data)
      toast({ type: 'success', message: 'นำเข้าสำเร็จ!' })
      navigate(`/documents/${data.id}`)
    } catch (err) {
      toast({ type: 'error', message: err.response?.data?.message || 'นำเข้าไม่สำเร็จ' })
    } finally {
      setUrlLoading(false)
    }
  }

  const formatSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  }

  const isUploading = uploadStatus === 'uploading'

  return (
    <div className="max-w-2xl mx-auto">
      <PageHeader
        title="อัปโหลดเอกสาร"
        subtitle="รองรับ PDF, รูปภาพ (OCR), ไฟล์เสียง (Whisper), และวิดีโอ"
        action={
          <Button variant="ghost" onClick={() => navigate('/documents')}>
            ← กลับ
          </Button>
        }
      />

      {/* Mode tabs */}
      <div className="flex gap-2 mb-5">
        <button
          onClick={() => setUrlMode(false)}
          className={`px-4 py-2 text-sm rounded-lg font-medium transition-colors ${
            !urlMode ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          📁 อัปโหลดไฟล์
        </button>
        <button
          onClick={() => setUrlMode(true)}
          className={`px-4 py-2 text-sm rounded-lg font-medium transition-colors ${
            urlMode ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          🔗 นำเข้าจาก URL / Google Drive
        </button>
      </div>

      {!urlMode ? (
        <Card>
          {/* Drop zone */}
          <div
            onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
            onDragLeave={() => setDragOver(false)}
            onDrop={handleDrop}
            onClick={() => !isUploading && fileInputRef.current?.click()}
            className={`border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors ${
              dragOver
                ? 'border-indigo-400 bg-indigo-50'
                : 'border-slate-300 hover:border-indigo-300 hover:bg-slate-50'
            } ${isUploading ? 'pointer-events-none opacity-60' : ''}`}
          >
            <div className="text-4xl mb-3">
              {dragOver ? '📂' : '📄'}
            </div>
            <p className="text-sm font-medium text-slate-700">
              {dragOver ? 'ปล่อยไฟล์ที่นี่' : 'ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก'}
            </p>
            <p className="text-xs text-slate-400 mt-1">
              PDF · JPG · PNG · MP3 · WAV · MP4 (สูงสุด {MAX_SIZE_MB} MB)
            </p>
            <input
              ref={fileInputRef}
              type="file"
              accept={ACCEPTED_TYPES.join(',')}
              className="hidden"
              onChange={(e) => e.target.files[0] && selectFile(e.target.files[0])}
            />
          </div>

          {/* Error */}
          {fileError && (
            <div className="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
              {fileError}
            </div>
          )}

          {/* Selected file preview */}
          {selectedFile && (
            <div className="mt-4 rounded-lg bg-slate-50 border border-slate-200 p-3 flex items-center gap-3">
              <div className="text-2xl">
                {selectedFile.type.startsWith('audio') ? '🎵'
                  : selectedFile.type.startsWith('video') ? '🎬'
                  : selectedFile.type.startsWith('image') ? '🖼️'
                  : '📄'}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-slate-900 truncate">{selectedFile.name}</p>
                <p className="text-xs text-slate-500">{formatSize(selectedFile.size)}</p>
              </div>
              {!isUploading && (
                <button
                  onClick={(e) => { e.stopPropagation(); setSelectedFile(null) }}
                  className="text-slate-400 hover:text-red-500 text-lg leading-none"
                >
                  ×
                </button>
              )}
            </div>
          )}

          {/* Upload progress */}
          {isUploading && (
            <div className="mt-4">
              <div className="flex justify-between text-xs text-slate-500 mb-1">
                <span>กำลังอัปโหลด...</span>
                <span>{uploadProgress}%</span>
              </div>
              <div className="h-2 bg-slate-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-indigo-600 rounded-full transition-all duration-300"
                  style={{ width: `${uploadProgress}%` }}
                />
              </div>
            </div>
          )}

          {uploadStatus === 'done' && (
            <div className="mt-4 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
              <span>✓</span> อัปโหลดสำเร็จ! กำลังไปหน้าเอกสาร...
            </div>
          )}

          {/* Action */}
          <div className="mt-4 flex justify-end">
            <Button
              onClick={handleUpload}
              disabled={!selectedFile || isUploading || uploadStatus === 'done'}
              loading={isUploading}
            >
              {isUploading ? `กำลังอัปโหลด ${uploadProgress}%` : 'อัปโหลดและประมวลผล'}
            </Button>
          </div>
        </Card>
      ) : (
        <Card>
          <label className="block text-sm font-medium text-slate-700 mb-2">
            URL ของเอกสาร
          </label>
          <input
            type="url"
            value={urlInput}
            onChange={(e) => setUrlInput(e.target.value)}
            placeholder="https://drive.google.com/... หรือ https://www.youtube.com/..."
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <p className="mt-2 text-xs text-slate-400">
            รองรับ Google Drive (Public link), YouTube, และ URL ที่เข้าถึงได้สาธารณะ
          </p>
          <div className="mt-4 flex justify-end">
            <Button onClick={handleUrlImport} loading={urlLoading} disabled={!urlInput.trim()}>
              นำเข้าเอกสาร
            </Button>
          </div>
        </Card>
      )}

      {/* Processing info */}
      <div className="mt-4 rounded-xl bg-blue-50 border border-blue-200 p-4">
        <p className="text-sm font-medium text-blue-800 mb-2">ขั้นตอนการประมวลผลหลังอัปโหลด</p>
        <ol className="text-xs text-blue-700 space-y-1 list-decimal list-inside">
          <li>ดึงข้อความจากไฟล์ (OCR สำหรับรูปภาพ / Whisper สำหรับเสียง)</li>
          <li>แบ่งข้อความเป็น chunks และสร้าง embeddings</li>
          <li>บันทึก vectors ลง ChromaDB สำหรับ RAG Chatbot</li>
          <li>พร้อมใช้งาน: สรุปบทเรียน, สร้างข้อสอบ, ถาม AI</li>
        </ol>
      </div>
    </div>
  )
}
