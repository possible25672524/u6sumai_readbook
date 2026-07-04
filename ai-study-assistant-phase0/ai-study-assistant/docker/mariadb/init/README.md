# MariaDB Init Scripts

ไฟล์ `.sql` หรือ `.sh` ใดๆ ที่วางไว้ในโฟลเดอร์นี้ จะถูกรันอัตโนมัติ
**ครั้งเดียวตอนสร้าง container ครั้งแรก** (ตอนที่ data volume ยังว่างอยู่)
โดย entrypoint ของ official MariaDB image

ใช้สำหรับ:
- Seed ข้อมูลเริ่มต้น (เช่น default roles: admin/teacher/student)
- สร้าง view หรือ stored procedure ที่ต้องมีตั้งแต่ต้น

ไม่ต้องใช้สำหรับการสร้างตาราง — ตารางทั้งหมดสร้างผ่าน Laravel Migration
(`php artisan migrate`) ใน Phase 1 แทน
