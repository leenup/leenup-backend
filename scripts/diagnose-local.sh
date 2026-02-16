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
dev_caddyfile="api/frankenphp/Caddyfile.dev"
if [[ ! -f "$dev_caddyfile" ]]; then
  dev_caddyfile="api/frankenphp/Caddyfile"
fi

if [[ -f "$dev_caddyfile" ]]; then
  if grep -qE '^:80\s*\{' "$dev_caddyfile" && ! grep -qE '^:443\s*\{' "$dev_caddyfile" && ! grep -qE '^localhost(,|\s*\{)' "$dev_caddyfile"; then
    echo "ℹ️  Dev Caddyfile ($dev_caddyfile) listens on :80 only. HTTPS may fail locally unless additional TLS config is enabled."
    echo
  fi
fi

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

  if out=$(curl -k -sS -o /dev/null -m 8 -w "%{http_code}" -H "Accept: text/html" "$url" 2>/tmp/leenup-curl.err); then
    if [[ "$out" == "000" ]]; then
      echo "❌ Unreachable: $url"
      return 2
    fi

    if [[ "$out" =~ ^[23] ]]; then
      echo "✅ Reachable: $url (HTTP $out)"
      return 0
    fi

    if [[ "$out" == "404" ]]; then
      echo "⚠️  Reachable but route not found: $url (HTTP 404)"
      return 1
    fi

    echo "⚠️  Reachable with non-2xx/3xx status: $url (HTTP $out)"
    return 1
  fi

  err=$(cat /tmp/leenup-curl.err)
  if [[ "$err" == *"unexpected eof while reading"* ]]; then
    echo "❌ TLS handshake error on $url (unexpected EOF)."
    return 2
  fi

  echo "❌ Unreachable: $url"
  echo "   ↳ curl error: $err"
  return 2
}

for path in /docs /admin; do
  https_ok=0
  http_ok=0
  http_route_ok=0

  set +e
  check_url "https://localhost${path}"
  rc=$?
  set -e
  if [[ $rc -eq 0 ]]; then
    https_ok=1
  fi

  set +e
  check_url "https://localhost${path}/"
  rc=$?
  set -e
  if [[ $rc -eq 0 ]]; then
    https_ok=1
  fi

  set +e
  check_url "http://localhost${path}"
  rc=$?
  set -e
  if [[ $rc -ne 2 ]]; then
    http_ok=1
  fi
  if [[ $rc -eq 0 ]]; then
    http_route_ok=1
  fi

  set +e
  check_url "http://localhost${path}/"
  rc=$?
  set -e
  if [[ $rc -ne 2 ]]; then
    http_ok=1
  fi
  if [[ $rc -eq 0 ]]; then
    http_route_ok=1
  fi

  if [[ $https_ok -eq 0 && $http_route_ok -eq 1 ]]; then
    echo "⚠️  $path works over HTTP but not HTTPS. Check local TLS/certificates/reverse-proxy config."
  elif [[ $https_ok -eq 0 && $http_ok -eq 1 ]]; then
    echo "⚠️  $path host is reachable over HTTP, but endpoint status is not OK."
    echo "   ↳ If this is /admin, verify PWA container health and browser-style Accept headers."
  fi

  echo
done
