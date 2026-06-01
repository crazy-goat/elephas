#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="docker/docker-compose.yml"

cleanup() {
    echo "Stopping Docker services..."
    docker compose -f "$COMPOSE_FILE" down
}

trap cleanup EXIT

echo "Starting Docker services..."
docker compose -f "$COMPOSE_FILE" up -d

echo "Waiting for TigerBeetle..."
for i in $(seq 1 30); do
    if docker compose -f "$COMPOSE_FILE" exec -T elephas php -r '
        $addr = getenv("TIGERBEETLE_ADDRESS") ?: "tigerbeetle:3000";
        $parts = explode(":", $addr);
        $host = $parts[0];
        $port = (int)($parts[1] ?? 3000);
        $s = @fsockopen($host, $port, $e, $s, 2);
        exit $s ? 0 : 1;
    ' 2>/dev/null; then
        echo "TigerBeetle is ready."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "Timed out waiting for TigerBeetle."
        exit 1
    fi
    sleep 1
done

vendor/bin/phpunit --testsuite=functional "$@"
