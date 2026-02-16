#!/usr/bin/env bash
set -euo pipefail

echo "🔎 LeenUp local connectivity diagnosis"
echo "====================================="

if [[ -n "${COMPOSE_FILE:-}" ]]; then
  echo "COMPOSE_FILE=${COMPOSE_FILE}"
  if [[ "${COMPOSE_FILE}" == *"compose.prod.yaml"* ]]; then
    echo "❌ compose.prod.yaml is active in COMPOSE_FILE (not suitable for localhost access)."
  else
    echo "✅ COMPOSE_FILE is set and does not include compose.prod.yaml."
  fi
else
  echo "✅ COMPOSE_FILE is not set (default compose files will be used)."
fi

echo
if ! command -v docker >/dev/null 2>&1; then
  echo "⚠️ docker command is not available in PATH."
  exit 0
fi

echo "🐳 docker compose config (php ports / pwa exposure)"
if docker compose config >/tmp/leenup-compose-config.yaml 2>/tmp/leenup-compose-config.err; then
  php_ports=$(awk '
    $1=="php:" {in_php=1; next}
    in_php && /^[^[:space:]]/ {in_php=0}
    in_php && $1=="ports:" {in_ports=1; next}
    in_php && in_ports && /^[[:space:]]*-[[:space:]]*target:/ {print $0}
    in_php && in_ports && /^[[:space:]]*published:/ {print $0}
    in_php && in_ports && /^[[:space:]]*[a-zA-Z_]+:/ && $1!="published:" && $1!="-" {in_ports=0}
  ' /tmp/leenup-compose-config.yaml)

  if [[ -n "${php_ports}" ]]; then
    echo "✅ php service publishes ports:"
    echo "${php_ports}"
  else
    echo "❌ php service does not publish host ports in effective compose config."
  fi
else
  echo "❌ docker compose config failed:"
  cat /tmp/leenup-compose-config.err
fi

echo
if docker compose ps >/tmp/leenup-compose-ps.out 2>/tmp/leenup-compose-ps.err; then
  echo "✅ docker compose ps"
  cat /tmp/leenup-compose-ps.out
else
  echo "❌ docker compose ps failed:"
  cat /tmp/leenup-compose-ps.err
fi

echo
for url in https://localhost/docs https://localhost/admin; do
  if curl -k -sS -o /dev/null -m 5 "$url"; then
    echo "✅ Reachable: $url"
  else
    echo "❌ Unreachable: $url"
  fi
done
