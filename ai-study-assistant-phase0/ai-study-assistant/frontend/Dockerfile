# ---- Base: ติดตั้ง dependencies ----
FROM node:20-alpine AS base
WORKDIR /app
COPY package*.json ./
RUN npm install

# ---- Development: ใช้ vite dev server พร้อม hot reload ----
FROM base AS development
COPY . .
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

# ---- Build: สร้าง production build ----
FROM base AS build
COPY . .
RUN npm run build

# ---- Production: serve static files ด้วย nginx ----
FROM nginx:1.27-alpine AS production
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
