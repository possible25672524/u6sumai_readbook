#!/bin/sh
set -e

APP_DIR=/var/www/html
cd "$APP_DIR"

# ----- ครั้งแรกที่รัน: ยังไม่มี Laravel app อยู่ใน backend/ -----
# (โฟลเดอร์นี้ถูก bind-mount จาก host ดังนั้นไฟล์ที่สร้างจะปรากฏบน host ด้วย
#  ทำให้นักพัฒนาแก้ไขโค้ดบน host แล้ว container เห็นผลทันที)
if [ ! -f "$APP_DIR/artisan" ]; then
  echo ">> [entrypoint] ไม่พบ Laravel app — กำลังสร้างโปรเจกต์ใหม่ (composer create-project, ครั้งแรกเท่านั้น)"
  composer create-project laravel/laravel /tmp/laravel-bootstrap --prefer-dist --no-interaction

  # -n = no-clobber: ไม่ทับไฟล์ที่เราเตรียมไว้ล่วงหน้าแล้ว (เช่น .env.example ของเราเอง)
  cp -rn /tmp/laravel-bootstrap/. "$APP_DIR"
  rm -rf /tmp/laravel-bootstrap

  echo ">> [entrypoint] ติดตั้งแพ็กเกจเพิ่มเติมที่ระบบต้องใช้: Sanctum, Predis"
  composer require laravel/sanctum predis/predis --no-interaction
fi

# ----- ติดตั้ง dependency ถ้ายังไม่มี vendor/ (เช่น เพิ่ง git clone มาใหม่) -----
if [ ! -d "$APP_DIR/vendor" ]; then
  echo ">> [entrypoint] กำลังรัน composer install"
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# ----- เตรียม .env จาก .env.example ถ้ายังไม่มี -----
if [ ! -f "$APP_DIR/.env" ] && [ -f "$APP_DIR/.env.example" ]; then
  cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi

# ----- สร้าง APP_KEY ถ้ายังไม่มี -----
if [ -f "$APP_DIR/.env" ] && ! grep -q "^APP_KEY=base64" "$APP_DIR/.env"; then
  echo ">> [entrypoint] กำลังสร้าง APP_KEY"
  php artisan key:generate --force
fi

# ----- สิทธิ์ของโฟลเดอร์ที่ Laravel ต้องเขียนได้ -----
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
