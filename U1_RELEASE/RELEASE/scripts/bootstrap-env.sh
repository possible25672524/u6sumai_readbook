#!/bin/sh
# scripts/bootstrap-env.sh
# One-time convenience: copies every .env.example to .env if (and only if)
# the real .env doesn't already exist. Safe to run repeatedly. Required
# in ALL modes (dev and prod) — see docker-compose.override.yml's note on
# why there is no in-compose fallback for secrets.

set -e
copy_if_missing() {
  example="$1"; target="$2"
  if [ -f "$example" ] && [ ! -f "$target" ]; then
    cp "$example" "$target"
    echo ">> created $target from $example - EDIT IT before deploying anywhere but a throwaway local sandbox"
  elif [ ! -f "$example" ]; then
    echo ">> skipped: $example not found"
  else
    echo ">> $target already exists, left untouched"
  fi
}

copy_if_missing ".env.example" ".env"
copy_if_missing "backend/.env.example" "backend/.env"
copy_if_missing "frontend/.env.example" "frontend/.env"

echo ">> done. Review the files above, then run: docker compose up -d --build"
