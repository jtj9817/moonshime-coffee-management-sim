#!/usr/bin/env bash

set -euo pipefail

if [ -n "${APP_ENV:-}" ] && [ -f ".env.${APP_ENV}" ]; then
  # shellcheck source=/dev/null
  source ".env.${APP_ENV}"
elif [ -f ".env" ]; then
  # shellcheck source=/dev/null
  source ".env"
fi

APP_PORT=${APP_PORT:-80}
VITE_PORT=${VITE_PORT:-5173}
FORWARD_DB_PORT=${FORWARD_DB_PORT:-5432}
FORWARD_REDIS_PORT=${FORWARD_REDIS_PORT:-6379}
FORWARD_MEILISEARCH_PORT=${FORWARD_MEILISEARCH_PORT:-7700}

port_in_use() {
  local port="$1"

  if command -v lsof >/dev/null 2>&1; then
    lsof -iTCP:"${port}" -sTCP:LISTEN -n -P >/dev/null 2>&1
    return $?
  fi

  if command -v ss >/dev/null 2>&1; then
    ss -ltn 2>/dev/null | grep -q "[.:]${port} "
    return $?
  fi

  if command -v netstat >/dev/null 2>&1; then
    netstat -ltn 2>/dev/null | grep -q "[.:]${port} "
    return $?
  fi

  return 1
}

declare -a conflicts=()

check_port() {
  local label="$1"
  local port="$2"

  if [[ -z "$port" || ! "$port" =~ ^[0-9]+$ ]]; then
    return 0
  fi

  if port_in_use "$port"; then
    conflicts+=("${label}:${port}")
  fi
}

check_port "APP_PORT" "$APP_PORT"
check_port "VITE_PORT" "$VITE_PORT"
check_port "FORWARD_DB_PORT" "$FORWARD_DB_PORT"
check_port "FORWARD_REDIS_PORT" "$FORWARD_REDIS_PORT"
check_port "FORWARD_MEILISEARCH_PORT" "$FORWARD_MEILISEARCH_PORT"

if [ "${#conflicts[@]}" -gt 0 ]; then
  echo "Port preflight failed. These host ports are already in use:" >&2
  for item in "${conflicts[@]}"; do
    echo "  - ${item}" >&2
  done
  echo "Resolve the conflict or set SAIL_SKIP_PORT_CHECK=1 to bypass." >&2
  exit 1
fi
