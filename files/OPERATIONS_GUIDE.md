# OPERATIONS GUIDE
**Project:** AI Study Assistant Platform  
**Version:** Phase 2 Release  
**Team:** U5 Integration Lead  
**Date:** 2026-07-01

---

## 1. STARTUP PROCEDURE

### Full System Start
```bash
cd /path/to/ai-study-assistant

# Development
docker compose up -d

# Production
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Verify all services healthy (allow 60-90s)
watch docker compose ps
```

### Expected Healthy State
```
NAME                        STATUS
study-ai-mariadb            Up (healthy)
study-ai-redis              Up (healthy)
study-ai-chromadb           Up (healthy)
study-ai-minio              Up (healthy)
study-ai-minio-init         Exited (0)       ← normal: runs once
study-ai-backend            Up (healthy)
study-ai-queue-worker-ocr   Up (healthy)
study-ai-queue-worker-ai    Up (healthy)
study-ai-queue-worker-default Up (healthy)
study-ai-nginx              Up (healthy)
study-ai-frontend           Up (healthy)
```

### Post-Start Verification
```bash
# Backend API
curl http://localhost:8000/up

# Frontend
curl http://localhost:8080/healthz

# ChromaDB (v2 API)
curl http://localhost:8001/api/v2/heartbeat

# Redis
docker compose exec redis redis-cli ping

# Queue workers active
docker compose exec queue-worker-default php artisan queue:monitor
```

---

## 2. SHUTDOWN PROCEDURE

### Graceful Shutdown
```bash
# Stops containers, preserves volumes
docker compose down

# Also remove anonymous volumes (careful in production)
docker compose down -v
```

### Drain Queue Before Shutdown (Production)
```bash
# Stop accepting new jobs
docker compose exec backend php artisan queue:pause

# Wait for active jobs to complete (check logs)
docker compose logs -f queue-worker-ocr queue-worker-ai queue-worker-default

# Then shutdown
docker compose down
```

---

## 3. QUEUE WORKER OPERATIONS

### Queue Names (Post PATCH-05)
| Queue | Worker | Job Types |
|-------|--------|-----------|
| `default` | queue-worker-default | ProcessDocumentJob, notifications |
| `ocr` | queue-worker-ocr | OcrDocumentJob |
| `transcribe` | queue-worker-ocr | TranscribeAudioJob |
| `embed` | queue-worker-ai | GenerateEmbeddingsJob |
| `embedding` | queue-worker-ai | (future AI generation jobs) |
| `ai-generation` | queue-worker-ai | (future generation jobs) |

### Monitor Queue Depth
```bash
docker compose exec redis redis-cli llen queues:default
docker compose exec redis redis-cli llen queues:ocr
docker compose exec redis redis-cli llen queues:transcribe
docker compose exec redis redis-cli llen queues:embed
```

### View Failed Jobs
```bash
# Via API (admin only)
curl -H "Authorization: Bearer <admin_token>" \
  http://localhost:8000/api/admin/jobs?status=failed

# Via database
docker compose exec backend php artisan queue:failed

# Retry a specific failed job
docker compose exec backend php artisan queue:retry <uuid>

# Retry all failed jobs
docker compose exec backend php artisan queue:retry all

# Flush failed jobs (destructive)
docker compose exec backend php artisan queue:flush
```

### Scale Workers
```bash
docker compose up -d --scale queue-worker-ocr=3 --scale queue-worker-ai=2
```

### Restart Workers After Code Deploy
```bash
docker compose restart queue-worker-ocr queue-worker-ai queue-worker-default
```

---

## 4. SCHEDULER OPERATIONS

The Laravel scheduler is not yet configured as a separate cron container (Phase 8 concern).  
To enable in production, add to host crontab:
```bash
# Run on the backend container every minute
* * * * * docker compose exec -T backend php artisan schedule:run >> /dev/null 2>&1
```

Or add a scheduler service to docker-compose:
```yaml
scheduler:
  build:
    context: ./backend
    dockerfile: Dockerfile
  command: ["php", "artisan", "schedule:work"]
  depends_on:
    backend:
      condition: service_healthy
```

---

## 5. HEALTH MONITORING

### Quick Health Check Script
```bash
#!/bin/bash
echo "=== AI Study Assistant Health ==="
echo -n "Backend:   "; curl -sf http://localhost:8000/up && echo "OK" || echo "FAIL"
echo -n "Frontend:  "; curl -sf http://localhost:8080/healthz && echo "OK" || echo "FAIL"
echo -n "ChromaDB:  "; curl -sf http://localhost:8001/api/v2/heartbeat && echo "OK" || echo "FAIL"
echo -n "Redis:     "; docker compose exec -T redis redis-cli ping
echo -n "MariaDB:   "; docker compose exec -T mariadb healthcheck.sh --connect && echo "OK" || echo "FAIL"
echo -n "MinIO:     "; curl -sf http://localhost:9000/minio/health/live && echo "OK" || echo "FAIL"
```

### AI Provider Health Check
```bash
docker compose exec backend php artisan tinker --execute="
  \$health = app(App\Services\AI\AIManager::class)->healthCheck();
  foreach(\$health as \$provider => \$status) {
    echo \$provider . ': ' . (\$status ? 'OK' : 'FAIL') . PHP_EOL;
  }
"
```

### Log Monitoring
```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f backend
docker compose logs -f queue-worker-ai

# Laravel application log
docker compose exec backend tail -f storage/logs/laravel.log

# Failed job details
docker compose exec backend php artisan queue:failed
```

---

## 6. BACKUP & RESTORE

### Database Backup
```bash
# Create backup
docker compose exec mariadb sh -c \
  'mysqldump -u root -p"$MARIADB_ROOT_PASSWORD" study_assistant' \
  > backup-$(date +%Y%m%d-%H%M%S).sql

# Restore backup
cat backup-YYYYMMDD-HHMMSS.sql | docker compose exec -T mariadb \
  mysql -u root -p"$MARIADB_ROOT_PASSWORD" study_assistant
```

### MinIO Backup
```bash
# Mirror raw uploads bucket to local
docker compose run --rm minio-init sh -c \
  'mc mirror local/study-assistant-raw /backup/raw'

# Mirror processed bucket
docker compose run --rm minio-init sh -c \
  'mc mirror local/study-assistant-processed /backup/processed'
```

### ChromaDB Backup
```bash
# ChromaDB data volume backup
docker run --rm \
  -v ai-study-assistant_chroma_data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/chromadb-$(date +%Y%m%d).tar.gz /data
```

### Full Restore Procedure
```bash
# 1. Stop application
docker compose down

# 2. Restore MariaDB
docker compose up -d mariadb
sleep 10
cat backup.sql | docker compose exec -T mariadb mysql -u root -p"$ROOT_PW" study_assistant

# 3. Restore MinIO (start minio first)
docker compose up -d minio
# Then mirror from backup location

# 4. Restore ChromaDB volume
docker compose up -d chromadb
# Then restore from tar.gz

# 5. Start full stack
docker compose up -d
```

---

## 7. LOG LOCATIONS

| Component | Log Location |
|-----------|-------------|
| Laravel App | `backend/storage/logs/laravel.log` (bind-mounted in dev) |
| PHP-FPM | stdout/stderr → `docker compose logs backend` |
| Queue Workers | stdout/stderr → `docker compose logs queue-worker-*` |
| Nginx (API gateway) | stdout/stderr → `docker compose logs nginx` |
| Frontend Nginx | stdout/stderr → `docker compose logs frontend` |
| MariaDB | stdout → `docker compose logs mariadb` |
| Redis | stdout → `docker compose logs redis` |
| ChromaDB | stdout → `docker compose logs chromadb` |

### Production Log Rotation (docker-compose.prod.yml)
```yaml
logging:
  driver: json-file
  options:
    max-size: "10m"
    max-file: "5"
```

---

## 8. TROUBLESHOOTING

### Backend Container Fails to Start
```bash
docker compose logs backend
# Common causes:
# - Database not yet healthy (wait for depends_on)
# - Missing APP_KEY (run: php artisan key:generate)
# - Wrong DB credentials in backend/.env
```

### Queue Jobs Not Processing
```bash
# Check worker is running
docker compose ps queue-worker-ai
docker compose logs queue-worker-ai

# Check queue depth
docker compose exec redis redis-cli llen queues:embed

# Verify queue connection in backend/.env
# QUEUE_CONNECTION=redis
# REDIS_HOST=redis

# After PATCH-05: queue-worker-ai must include 'embed' queue
# Verify command in docker-compose.yml:
# --queue=embed,embedding,ai-generation
```

### ChromaDB Operations Failing (404)
```bash
# Verify PATCH-02 applied — API must use /api/v2/
# Test directly:
curl http://localhost:8001/api/v2/collections
# Should return JSON, not 404

# Check image version
docker compose exec chromadb python3 -c "import chromadb; print(chromadb.__version__)"
```

### AI Providers Not Responding
```bash
# Check API keys set in backend/.env
grep ANTHROPIC_API_KEY backend/.env
grep OPENAI_API_KEY backend/.env

# Test connectivity
docker compose exec backend php artisan tinker --execute="
  echo app(App\Services\AI\AIManager::class)->healthCheck()['chat:claude'] ? 'Claude OK' : 'Claude FAIL';
"
```

### MinIO Upload Failures
```bash
# Check bucket exists
docker compose exec minio-init mc ls local/

# Verify bucket name matches .env
grep MINIO_BUCKET backend/.env
# Should be: MINIO_BUCKET=study-assistant-raw (or study-assistant-files)

# Check MinIO credentials match between .env and backend/.env
```

### ProtectedRoute Redirecting Admins (If PATCH-07 not applied)
```bash
# Verify fix in frontend/src/app/ProtectedRoute.jsx
# Should contain: user?.role?.slug ?? user?.role
# Not: user?.role
```

