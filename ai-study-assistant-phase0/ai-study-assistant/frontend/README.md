# Frontend — React + Vite + PWA

โครงสร้างหลัก:

```
src/
├── api/            # axios client กลาง (client.js) + 1 ไฟล์ต่อ feature (auth.js, documents.js, ...)
├── app/            # App.jsx (routing ทั้งหมด), layouts/, ProtectedRoute.jsx
├── features/       # 1 โฟลเดอร์ต่อ 1 โมดูล (auth, dashboard, documents, summaries,
│                   #   flashcards, quiz, chatbot, planner, analytics, admin)
│   └── <feature>/pages/
├── store/          # zustand: authStore.js (session), uiStore.js (sidebar/toast)
└── index.css       # global styles + Tailwind import
```

## คำสั่งที่ใช้บ่อย

```bash
npm run dev       # dev server ที่ http://localhost:5173 (proxy /api -> backend)
npm run build     # build production ไปที่ dist/
npm run lint      # ESLint
```

## สถานะหน้าต่าง ๆ

- `LoginPage` — wire เสร็จสมบูรณ์แล้ว (เรียก `authApi.login`, เซฟ session ใน `authStore`)
  ใช้เป็น pattern อ้างอิงสำหรับหน้าอื่น
- หน้าที่เหลือทั้งหมด (Dashboard, Documents, Flashcards, Quiz, Chatbot, Planner, Analytics, Admin)
  เป็น placeholder รอเติม UI จริงใน Phase ถัดไป — routing และ API service module เตรียมไว้ครบแล้ว

## State Management

ใช้ **Zustand** (เบากว่า Redux, ไม่ต้องมี Provider ครอบ):
- `authStore` — persist ลง browser storage อัตโนมัติผ่าน middleware `persist`
- `uiStore` — state ของ UI ทั่วไป เช่น sidebar เปิด/ปิด, toast notification

## Routing & Auth Guard

`ProtectedRoute.jsx` ใช้ครอบ route ที่ต้อง login ก่อนเข้า และรองรับจำกัดสิทธิ์ตาม role
(เช่น `<Route element={<ProtectedRoute roles={['admin']} />}>` สำหรับหน้า Admin เท่านั้น)
