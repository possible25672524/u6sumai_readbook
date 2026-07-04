# Backend — Laravel 12 API

โฟลเดอร์นี้จะถูก **bootstrap อัตโนมัติ** เมื่อรัน `docker compose up` ครั้งแรก
(ดู `docker-entrypoint.sh`) เนื่องจาก sandbox ที่ใช้สร้างโครงการนี้ไม่มีสิทธิ์เข้าถึง
Packagist จึงไม่สามารถรัน `composer create-project` ไว้ล่วงหน้าให้ได้

## สิ่งที่ entrypoint จะทำให้อัตโนมัติตอน container เริ่มทำงานครั้งแรก

1. `composer create-project laravel/laravel` เข้ามาในโฟลเดอร์นี้
2. ติดตั้ง `laravel/sanctum` และ `predis/predis` เพิ่ม
3. คัดลอก `.env.example` (ของเราเอง ที่เตรียม config MariaDB/Redis/MinIO/ChromaDB/Claude/OpenAI ไว้แล้ว) เป็น `.env`
4. รัน `php artisan key:generate`
5. ตั้งสิทธิ์ `storage/` และ `bootstrap/cache/`

หลังจากนั้นไฟล์ Laravel ทั้งหมดจะปรากฏในโฟลเดอร์นี้บนเครื่อง host (เพราะ bind-mount)
สามารถแก้ไขโค้ดได้ตามปกติ — Phase ถัดไปจะเริ่มเขียน Models / Controllers / Migrations ที่นี่

## คำสั่งที่ใช้บ่อย (รันผ่าน docker compose exec)

```bash
docker compose exec backend php artisan migrate
docker compose exec backend php artisan make:model Document -m
docker compose exec backend composer require <package>
docker compose exec backend php artisan queue:work   # ปกติรันอยู่ใน container queue-worker แล้ว
```
