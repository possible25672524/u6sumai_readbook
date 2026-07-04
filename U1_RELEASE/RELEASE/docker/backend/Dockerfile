# =============================================================================
# backend/Dockerfile
# Laravel 12 API + Tesseract OCR (tha+eng) + ffmpeg + poppler-utils
#
# One image is shared by THREE kinds of containers (only the `command:` in
# docker-compose differs):
#   1. backend              -> php-fpm (serves API via nginx/fastcgi)
#   2. queue-worker-ocr      -> php artisan queue:work --queue=ocr,transcribe
#   3. queue-worker-ai       -> php artisan queue:work --queue=embedding,ai-generation
#   4. queue-worker-default  -> php artisan queue:work --queue=default
#
# This matches the Phase 0/1 decision already recorded in project_memory.md:
# "Tesseract OCR ไว้ใน image เดียวกับ backend" — we keep that decision and
# simply split *queues* across multiple containers of the SAME image instead
# of building separate OCR/AI microservice images.
# =============================================================================

FROM php:8.3-fpm-bookworm AS base

LABEL maintainer="Team U1 - DevOps"
LABEL description="AI Study Assistant - Laravel 12 API + OCR/Audio toolchain"

ARG TESSERACT_LANGS="eng+tha"
ENV TESSERACT_LANGS=${TESSERACT_LANGS} \
    DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

# ---------------------------------------------------------------------------
# System packages
#   - tesseract-ocr + tesseract-ocr-tha + tesseract-ocr-eng : OCR (Module 4)
#   - poppler-utils                                          : pdftoppm/pdftotext for PDF->image/text
#   - ghostscript                                            : PDF rasterization fallback
#   - ffmpeg                                                 : audio/video extraction before Whisper (Module 4)
#   - libfcgi-bin                                            : provides `cgi-fcgi` used by the php-fpm healthcheck
#   - procps                                                 : provides `pgrep` used by the queue-worker healthcheck
#   - libzip-dev/libpng-dev/libjpeg62-turbo-dev/libfreetype6-dev/libicu-dev/libonig-dev
#                                                             : build deps for PHP extensions below
# ---------------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        tesseract-ocr \
        tesseract-ocr-eng \
        tesseract-ocr-tha \
        poppler-utils \
        ghostscript \
        ffmpeg \
        libfcgi-bin \
        procps \
        git \
        unzip \
        curl \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# PHP extensions required by Laravel 12 + this project
#   pdo_mysql   : MariaDB
#   mbstring    : Thai/UTF-8 string handling (important given Thai content)
#   bcmath      : money/score calculations (quiz scoring, analytics)
#   exif/gd     : image handling for uploaded images + thumbnails
#   intl        : ICU, useful for Thai date/number formatting
#   zip         : composer + document handling
#   pcntl       : required by `queue:work` graceful signal handling
#   opcache     : production performance
# Redis access goes through `predis/predis` (pure PHP, decided in Phase 1),
# so no pecl redis extension is required here.
# ---------------------------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        gd \
        intl \
        zip \
        pcntl \
        opcache

COPY docker/php/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php-fpm/healthcheck.sh /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# ---------------------------------------------------------------------------
# Composer (multi-stage copy, no extra install step needed)
# ---------------------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Application code.
# NOTE: the *base* image intentionally does NOT bind-mount source code -
# this is what makes it deployable as an immutable artifact. Local dev hot
# -reload bind-mounts are added back by docker-compose.override.yml.
# ---------------------------------------------------------------------------
COPY . .

# ---------------------------------------------------------------------------
# Fixed in v4.1 (see PHASE_2_INFRASTRUCTURE.md §6 "Defect 3" for the full
# root-cause writeup).
#
# HONEST STATEMENT OF CURRENT BEHAVIOR:
# As of this build, backend/ has NOT YET committed a Laravel skeleton
# (no composer.json/composer.lock/artisan - see README.md "ยังไม่มี Laravel
# skeleton"). Until that skeleton is committed, `composer install` CANNOT
# run at build time - there is nothing to install against - and
# docker-entrypoint.sh's first-boot `composer create-project` bootstrap is
# what actually populates vendor/, exactly as it did in Phase 0/1. That
# means the image is NOT yet fully immutable: first container start still
# needs live Packagist access and is slower than a "true" immutable image.
#
# This block makes that explicit and self-upgrading: once composer.json
# IS committed, the next `docker compose build backend` automatically
# switches to true build-time immutability with zero further Dockerfile
# changes - no one has to remember to come back and edit this file.
# ---------------------------------------------------------------------------
RUN if [ -f composer.json ]; then \
        echo ">> composer.json found - installing vendor/ at BUILD time (truly immutable image)"; \
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; \
    else \
        echo ">> composer.json NOT found yet - this image is NOT immutable."; \
        echo ">> vendor/ will be installed by docker-entrypoint.sh on first container"; \
        echo ">> start instead (same bootstrap behavior as Phase 0/1). Commit the"; \
        echo ">> Laravel skeleton to backend/ and rebuild to fix this permanently."; \
    fi

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
