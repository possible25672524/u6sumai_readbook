FROM php:8.3-fpm

# ----- System dependencies -----
# - tesseract-ocr + tesseract-ocr-tha: สำหรับโมดูล Document Processing (OCR ภาษาไทย)
# - ffmpeg: Whisper/แปลงไฟล์เสียง-วิดีโอก่อนส่งไป Whisper API บางกรณีต้องแปลง container ก่อน
# - libzip, unzip, git: จำเป็นสำหรับ Composer และ PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    ffmpeg \
    tesseract-ocr \
    tesseract-ocr-tha \
    && rm -rf /var/lib/apt/lists/*

# ----- PHP extensions ที่ Laravel/ระบบนี้ต้องใช้ -----
RUN docker-php-ext-install \
    pdo_mysql \
    zip \
    gd \
    bcmath \
    opcache \
    pcntl

# ----- Redis extension (สำหรับ Queue/Cache) -----
RUN pecl install redis && docker-php-ext-enable redis

# ----- Composer -----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ----- เอนทรีพอยต์: composer install อัตโนมัติถ้า vendor ยังไม่มี -----
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
