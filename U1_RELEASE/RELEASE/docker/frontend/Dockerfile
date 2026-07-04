# =============================================================================
# frontend/Dockerfile
# React + Vite + PWA
#
# Three stages:
#   development -> vite dev server with hot reload (used by docker-compose
#                  .override.yml for local dev)
#   builder     -> `npm run build` -> /app/dist
#   production  -> nginx serving the static SPA build (DEFAULT/base target,
#                  used by the production-safe docker-compose.yml)
# =============================================================================

# ---------------------------------------------------------------------------
# development
# ---------------------------------------------------------------------------
FROM node:20-alpine AS development
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host"]

# ---------------------------------------------------------------------------
# builder
# ---------------------------------------------------------------------------
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# VITE_* build-time vars are baked in at build time (Vite convention)
ARG VITE_API_BASE_URL=/api
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm run build

# ---------------------------------------------------------------------------
# production (default target)
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS production
RUN apk add --no-cache wget
COPY --from=builder /app/dist /usr/share/nginx/html
# NOTE: kept inside the frontend/ build context on purpose - Docker forbids
# COPY-ing files from outside the build context (e.g. `../docker/...`).
# This file is identical in intent to docker/nginx/frontend.conf documented
# at the repo root for readability; keep both in sync if you edit one.
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --spider -q http://localhost/ || exit 1
CMD ["nginx", "-g", "daemon off;"]
