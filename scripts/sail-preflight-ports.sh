#!/usr/bin/env bash

# This script checks for port conflicts and dynamically assigns new ports if needed.
# It is intended to be sourced by the main 'sail' script.

port_in_use() {
  local port="$1"

  # Prioritize ss as it's more reliable for seeing all listening ports without sudo
  if command -v ss >/dev/null 2>&1; then
    ss -ltn 2>/dev/null | grep -qE "[.:]${port}(\s|$)"
    return $?
  fi

  if command -v lsof >/dev/null 2>&1; then
    lsof -iTCP:"${port}" -sTCP:LISTEN -n -P >/dev/null 2>&1
    return $?
  fi

  if command -v netstat >/dev/null 2>&1; then
    netstat -ltn 2>/dev/null | grep -qE "[.:]${port}(\s|$)"
    return $?
  fi

  return 1
}

find_available_port() {
  local port="$1"
  local original_port="$1"
  
  while port_in_use "$port"; do
    port=$((port + 1))
  done
  
  echo "$port"
}

# Source .env if available
if [ -n "${APP_ENV:-}" ] && [ -f ".env.${APP_ENV}" ]; then
  # shellcheck source=/dev/null
  source ".env.${APP_ENV}"
elif [ -f ".env" ]; then
  # shellcheck source=/dev/null
  source ".env"
fi

# Initial values from environment or defaults
APP_PORT=${APP_PORT:-80}
VITE_PORT=${VITE_PORT:-5173}
FORWARD_DB_PORT=${FORWARD_DB_PORT:-5432}
FORWARD_REDIS_PORT=${FORWARD_REDIS_PORT:-6379}
FORWARD_MEILISEARCH_PORT=${FORWARD_MEILISEARCH_PORT:-7700}

# Dynamically find available ports
NEW_APP_PORT=$(find_available_port "$APP_PORT")
NEW_VITE_PORT=$(find_available_port "$VITE_PORT")
NEW_DB_PORT=$(find_available_port "$FORWARD_DB_PORT")
NEW_REDIS_PORT=$(find_available_port "$FORWARD_REDIS_PORT")
NEW_MEILI_PORT=$(find_available_port "$FORWARD_MEILISEARCH_PORT")

# Export the new values if they changed
if [ "$NEW_APP_PORT" != "$APP_PORT" ]; then
  echo "APP_PORT conflict: $APP_PORT is in use. Using $NEW_APP_PORT instead." >&2
  export APP_PORT="$NEW_APP_PORT"
fi

if [ "$NEW_VITE_PORT" != "$VITE_PORT" ]; then
  echo "VITE_PORT conflict: $VITE_PORT is in use. Using $NEW_VITE_PORT instead." >&2
  export VITE_PORT="$NEW_VITE_PORT"
fi

if [ "$NEW_DB_PORT" != "$FORWARD_DB_PORT" ]; then
  echo "DB_PORT conflict: $FORWARD_DB_PORT is in use. Using $NEW_DB_PORT instead." >&2
  export FORWARD_DB_PORT="$NEW_DB_PORT"
fi

if [ "$NEW_REDIS_PORT" != "$FORWARD_REDIS_PORT" ]; then
  echo "REDIS_PORT conflict: $FORWARD_REDIS_PORT is in use. Using $NEW_REDIS_PORT instead." >&2
  export FORWARD_REDIS_PORT="$NEW_REDIS_PORT"
fi

if [ "$NEW_MEILI_PORT" != "$FORWARD_MEILISEARCH_PORT" ]; then
  echo "MEILISEARCH_PORT conflict: $FORWARD_MEILISEARCH_PORT is in use. Using $NEW_MEILI_PORT instead." >&2
  export FORWARD_MEILISEARCH_PORT="$NEW_MEILI_PORT"
fi
