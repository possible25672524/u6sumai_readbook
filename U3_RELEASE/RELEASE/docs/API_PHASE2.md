# API Reference — Phase 2: Document Upload & Processing Pipeline

Base URL: `http://localhost:8000/api` (via Nginx per `docker-compose.yml`)

All responses are JSON. Authenticated endpoints require:
`Authorization: Bearer <token>`

---

## Categories

### List Categories
`GET /api/categories`  **Auth required**

**Query Parameters**
| Parameter | Type | Description |
|---|---|---|
| `tree` | boolean | If `true`, returns root categories with nested children |
| `page` | integer | Page number (paginated mode only) |

**Response 200 (paginated)**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Mathematics",
      "slug": "mathematics",
      "description": "Math topics",
      "parent_id": null,
      "parent": null,
      "document_count": 5,
      "created_at": "2026-06-27T10:00:00+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "total": 10, "per_page": 50 }
}
```

**Response 200 (tree mode)**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Science",
      "slug": "science",
      "children": [
        { "id": 2, "name": "Physics", "slug": "physics", "children": [] }
      ]
    }
  ]
}
```

---

### Create Category
`POST /api/categories`  **Admin or Teacher only**

**Request body**
```json
{
  "name": "Mathematics",
  "slug": "mathematics",
  "description": "Math and statistics topics",
  "parent_id": null
}
```

| Field | Rules |
|---|---|
| `name` | required, string, max:100 |
| `slug` | nullable, alpha_dash, max:100, unique |
| `description` | nullable, string, max:500 |
| `parent_id` | nullable, integer, exists:categories |

**Response 201**
```json
{
  "message": "สร้างหมวดหมู่สำเร็จ",
  "category": { "id": 3, "name": "Mathematics", "slug": "mathematics", ... }
}
```

**Response 403** — student role or unauthenticated

---

### Get Category
`GET /api/categories/{category}`  **Auth required**

**Response 200** — category object with parent and children loaded

---

### Update Category
`PUT /api/categories/{category}`  **Owner or Admin**

**Request body** — same fields as create (all optional)

**Response 200**
```json
{ "message": "อัปเดตหมวดหมู่สำเร็จ", "category": { ... } }
```

---

### Delete Category
`DELETE /api/categories/{category}`  **Owner or Admin**

Cannot delete if category has children or documents attached.

**Response 200**
```json
{ "message": "ลบหมวดหมู่สำเร็จ" }
```

**Response 422** — has children or documents

---

## Documents

### List Documents
`GET /api/documents`  **Auth required**

Students/Teachers see: own documents + public + shared.
Admins see: all active documents.

**Query Parameters**
| Parameter | Type | Description |
|---|---|---|
| `status` | string | Filter by `pending\|processing\|completed\|failed` |
| `source_type` | string | Filter by `pdf\|docx\|txt\|image\|audio\|video\|youtube\|google_drive` |
| `category_id` | integer | Filter by category |
| `search` | string | Search title and description |
| `per_page` | integer | Results per page (max 100, default 15) |

**Response 200**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Calculus Notes",
      "description": "Chapter 1-5",
      "source_type": "pdf",
      "file_name": "calc.pdf",
      "file_size": 204800,
      "file_size_human": "200 KB",
      "mime_type": "application/pdf",
      "source_url": null,
      "status": "completed",
      "language": "th",
      "page_count": 45,
      "duration_seconds": null,
      "visibility": "private",
      "is_active": true,
      "categories": [],
      "chunk_count": 87,
      "created_at": "2026-06-27T10:00:00+00:00",
      "updated_at": "2026-06-27T10:05:00+00:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

### Upload Document
`POST /api/documents`  **Auth required**

**Request** — `multipart/form-data`

| Field | Rules |
|---|---|
| `title` | required, string, max:255 |
| `description` | nullable, string, max:2000 |
| `source_type` | required: `pdf\|docx\|txt\|image\|audio\|video\|youtube\|google_drive` |
| `file` | required for file types, max 200 MB, validated MIME |
| `source_url` | required for youtube/google_drive, valid URL |
| `category_ids[]` | nullable, array of existing category IDs |
| `visibility` | nullable: `private\|shared\|public` (default: private) |
| `language` | nullable, string max:10 (default: th) |

**Response 201**
```json
{
  "message": "อัปโหลดเอกสารสำเร็จ กำลังประมวลผล...",
  "document": {
    "id": 5,
    "title": "Lecture Notes",
    "status": "pending",
    "source_type": "pdf",
    ...
  }
}
```

**Response 422** — validation errors with Thai messages

---

### Get Document
`GET /api/documents/{document}`  **Owner / public visibility**

**Response 200** — document object with categories, processing_jobs, user

---

### Update Document
`PUT /api/documents/{document}`  **Owner only**

**Request body** (all fields optional)
```json
{
  "title": "Updated Title",
  "description": "New description",
  "visibility": "shared",
  "category_ids": [1, 2],
  "extracted_text": "Corrected OCR text..."
}
```

Providing `extracted_text` on a completed document re-triggers the embedding pipeline.

**Response 200**
```json
{ "message": "อัปเดตเอกสารสำเร็จ", "document": { ... } }
```

---

### Delete Document
`DELETE /api/documents/{document}`  **Owner only**

Soft-deletes the document record, removes file from MinIO, removes vectors from ChromaDB.

**Response 200**
```json
{ "message": "ลบเอกสารสำเร็จ" }
```

---

### Reprocess Document
`POST /api/documents/{document}/reprocess`  **Owner only**

Re-triggers the full processing pipeline. Only allowed when status is `completed` or `failed`.

**Response 200**
```json
{ "message": "เริ่มประมวลผลเอกสารใหม่" }
```

**Response 409** — document is already processing

---

### Get Processing Status
`GET /api/documents/{document}/status`  **Owner only**

**Response 200**
```json
{
  "document_id": 5,
  "status": "processing",
  "jobs": [
    {
      "id": 1,
      "job_type": "ocr",
      "status": "completed",
      "progress": 100,
      "attempts": 1,
      "max_attempts": 3,
      "meta": { "page_count": 45, "chunk_count": 87, "avg_confidence": 0.94 },
      "started_at": "2026-06-27T10:00:05+00:00",
      "completed_at": "2026-06-27T10:01:23+00:00"
    },
    {
      "id": 2,
      "job_type": "embed",
      "status": "processing",
      "progress": 42,
      ...
    }
  ]
}
```

---

### List Document Chunks
`GET /api/documents/{document}/chunks`  **Owner / viewable**

**Query Parameters**: `per_page` (max 200, default 50)

**Response 200**
```json
{
  "data": [
    {
      "id": 1,
      "chunk_index": 0,
      "content": "Chapter 1: Introduction to Calculus...",
      "token_count": 312,
      "page_number": 1,
      "is_embedded": true,
      "ocr_confidence": 0.96,
      "chroma_id": "550e8400-e29b-41d4-a716-446655440000"
    }
  ]
}
```

---

### Get Transcript
`GET /api/documents/{document}/transcript`  **Owner / viewable**

Only available for audio/video documents processed via Whisper.

**Query Parameters**: `with_segments=1` to include timestamped segments

**Response 200**
```json
{
  "transcript": {
    "id": 1,
    "document_id": 5,
    "content": "Full transcribed text...",
    "language": "th",
    "duration_seconds": 3600,
    "avg_logprob": -0.23,
    "provider": "whisper",
    "model": "whisper-1",
    "created_at": "2026-06-27T10:05:00+00:00"
  }
}
```

**Response 404** — no transcript for this document type

---

### Download Document
`GET /api/documents/{document}/download`  **Owner / viewable**

Returns a presigned MinIO URL valid for 15 minutes.

**Response 200**
```json
{
  "url": "http://minio:9000/study-assistant-files/documents/1/uuid.pdf?X-Amz-...",
  "expires_in_minutes": 15
}
```

**Response 404** — URL-based document (no file stored)

---

## Processing Jobs

### List Jobs for Document
`GET /api/documents/{document}/jobs`  **Owner only**

**Response 200**
```json
{
  "document_id": 5,
  "status": "completed",
  "jobs": [ { "id": 1, "job_type": "ocr", "status": "completed", ... } ]
}
```

---

### Get Single Job
`GET /api/jobs/{job}`  **Owner only**

**Response 200**
```json
{ "job": { "id": 1, "job_type": "ocr", "status": "completed", "progress": 100, ... } }
```

---

### Admin — List All Jobs
`GET /api/admin/jobs`  **Admin only**

**Query Parameters**: `status` (default: `failed`), `per_page` (max 100)

**Response 200** — paginated ProcessingJob list with document summary

---

## Common Error Responses

**401 Unauthenticated**
```json
{ "message": "Unauthenticated." }
```

**403 Forbidden**
```json
{ "message": "คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้" }
```

**404 Not Found**
```json
{ "message": "ไม่พบข้อมูลที่ต้องการ" }
```

**422 Validation Error**
```json
{
  "message": "กรุณาระบุชื่อเอกสาร",
  "errors": {
    "title": ["กรุณาระบุชื่อเอกสาร"],
    "file": ["ขนาดไฟล์ต้องไม่เกิน 200 MB"]
  }
}
```

**409 Conflict**
```json
{ "message": "เอกสารกำลังประมวลผลอยู่แล้ว" }
```
