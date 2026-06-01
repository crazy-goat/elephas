#!/usr/bin/env bash
set -euo pipefail

echo "=== Building and starting Docker services ==="
docker compose up -d --build

echo "=== Checking PHP extensions ==="
missing=0
for ext in ffi gmp bcmath pcntl posix; do
  if docker compose exec elephas php -m 2>/dev/null | grep -q "^${ext}$"; then
    echo "  ✓ ext-${ext} loaded"
  else
    echo "  ✗ ext-${ext} MISSING"
    missing=$((missing + 1))
  fi
done

echo "=== Checking Composer availability ==="
if docker compose exec elephas composer --version >/dev/null 2>&1; then
  echo "  ✓ Composer available"
else
  echo "  ✗ Composer MISSING"
  missing=$((missing + 1))
fi

echo "=== Checking TigerBeetle connectivity ==="
if docker compose exec elephas bash -c 'echo > /dev/tcp/tigerbeetle/3000' 2>/dev/null; then
  echo "  ✓ TigerBeetle reachable on tigerbeetle:3000"
else
  echo "  ✗ TigerBeetle NOT reachable"
  missing=$((missing + 1))
fi

echo "=== Checking project mount ==="
if docker compose exec elephas test -f /app/composer.json; then
  echo "  ✓ Project root mounted to /app"
else
  echo "  ✗ Project root NOT mounted"
  missing=$((missing + 1))
fi

echo ""
if [ "$missing" -eq 0 ]; then
  echo "✅ All checks passed!"
else
  echo "❌ ${missing} check(s) failed"
  exit 1
fi
