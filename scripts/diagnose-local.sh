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

echo "🐳 Published host ports for php service"
published_any=0
for container_port in 80 443; do
  if mapping=$(docker compose port php "$container_port" 2>/tmp/leenup-diagnose-port.err); then
    echo "✅ php:$container_port -> ${mapping}"
    published_any=1
  else
    echo "ℹ️  No published mapping found for php:$container_port"
  fi
done

if [[ $published_any -eq 0 ]]; then
  echo "❌ php service does not publish host ports in effective compose config."
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
check_url() {
  local url="$1"

  if out=$(curl -k -sS -o /dev/null -m 8 -w "%{http_code}" "$url" 2>/tmp/leenup-curl.err); then
    if [[ "$out" == "000" ]]; then
      echo "❌ Unreachable: $url"
      return 1
    fi

    echo "✅ Reachable: $url (HTTP $out)"
    return 0
  fi

  err=$(cat /tmp/leenup-curl.err)
  if [[ "$err" == *"unexpected eof while reading"* ]]; then
    echo "❌ TLS handshake error on $url (unexpected EOF)."
    echo "   ↳ Hint: test the same endpoint in HTTP to confirm the app is up without TLS."
  else
    echo "❌ Unreachable: $url"
    echo "   ↳ curl error: $err"
  fi

  return 1
}

for path in /docs /admin; do
  https_ok=0
  http_ok=0

  check_url "https://localhost${path}" && https_ok=1 || true
  check_url "http://localhost${path}" && http_ok=1 || true

  if [[ $https_ok -eq 0 && $http_ok -eq 1 ]]; then
    echo "⚠️  $path is reachable in HTTP but not HTTPS. Check local TLS/certificates/reverse-proxy config."
  fi

  echo
 done
