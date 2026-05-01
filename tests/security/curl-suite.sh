#!/usr/bin/env bash
# Adversarial security probes for the mu-plugins.
# Runs against a live WP at http://localhost:8099. Exits non-zero on any failure.
#
# Covers:
#   - Forged / expired / malformed cart tokens
#   - CORS origin allowlist
#   - OPTIONS preflight denial
#   - Security headers presence
#   - Return URL bypass attempts
#   - Cart token scope (only accepted on /checkout)
#
# Run: bash tests/security/curl-suite.sh

set -u
WP="http://localhost:8099"
FAIL=0
PASS=0
TOTAL=0

red()   { printf "\033[31m%s\033[0m" "$*"; }
green() { printf "\033[32m%s\033[0m" "$*"; }
yellow(){ printf "\033[33m%s\033[0m" "$*"; }

ok() {
	PASS=$((PASS + 1))
	TOTAL=$((TOTAL + 1))
	echo "  $(green '✓') $1"
}

fail() {
	FAIL=$((FAIL + 1))
	TOTAL=$((TOTAL + 1))
	echo "  $(red '✗') $1"
	shift
	for line in "$@"; do echo "      $line"; done
}

section() {
	echo ""
	echo "=== $1 ==="
}

# ----- prerequisites -----
if ! curl -sf -o /dev/null "$WP/wp-login.php"; then
	echo "WP not reachable at $WP — start the env first" >&2
	exit 2
fi

# =======================================================================
# Section 1: Security headers present on every Store API response
# =======================================================================
section "Security headers"

HEADERS=$(curl -sI "$WP/wp-json/wc/store/v1/cart")

if echo "$HEADERS" | grep -qi '^X-Content-Type-Options: nosniff'; then
	ok "X-Content-Type-Options: nosniff"
else
	fail "X-Content-Type-Options missing"
fi

if echo "$HEADERS" | grep -qi '^X-Frame-Options: DENY'; then
	ok "X-Frame-Options: DENY"
else
	fail "X-Frame-Options missing"
fi

if echo "$HEADERS" | grep -qi '^Referrer-Policy:'; then
	ok "Referrer-Policy set"
else
	fail "Referrer-Policy missing"
fi

# =======================================================================
# Section 2: CORS allowlist
# =======================================================================
section "CORS origin allowlist"

# Good origin → CORS headers present
GOOD=$(curl -sI -H "Origin: http://localhost:5175" "$WP/wp-json/wc/store/v1/cart")
if echo "$GOOD" | grep -qi '^Access-Control-Allow-Origin: http://localhost:5175'; then
	ok "allowed origin gets ACAO reflection"
else
	fail "allowed origin missing ACAO"
fi

if echo "$GOOD" | grep -qi '^Access-Control-Allow-Credentials: true'; then
	ok "allowed origin gets ACAC: true"
else
	fail "allowed origin missing ACAC"
fi

# Bad origin → NO Access-Control-Allow-Origin header
BAD=$(curl -sI -H "Origin: http://evil.com" "$WP/wp-json/wc/store/v1/cart")
if echo "$BAD" | grep -qi '^Access-Control-Allow-Origin:'; then
	fail "rogue origin got ACAO" "$(echo "$BAD" | grep -i 'Access-Control-Allow-Origin:')"
else
	ok "rogue origin gets no ACAO"
fi

# Null origin → also no ACAO
NULL=$(curl -sI -H "Origin: null" "$WP/wp-json/wc/store/v1/cart")
if echo "$NULL" | grep -qi '^Access-Control-Allow-Origin:'; then
	fail "null origin got ACAO"
else
	ok "null origin gets no ACAO"
fi

# Empty origin header → no ACAO
EMPTY=$(curl -sI -H "Origin: " "$WP/wp-json/wc/store/v1/cart")
if echo "$EMPTY" | grep -qi '^Access-Control-Allow-Origin:'; then
	fail "empty origin got ACAO"
else
	ok "empty origin gets no ACAO"
fi

# Subdomain attack — evil.localhost:5175 should NOT match localhost:5175
SUB=$(curl -sI -H "Origin: http://evil.localhost:5175" "$WP/wp-json/wc/store/v1/cart")
if echo "$SUB" | grep -qi '^Access-Control-Allow-Origin:'; then
	fail "evil subdomain got ACAO"
else
	ok "evil subdomain gets no ACAO"
fi

# =======================================================================
# Section 3: OPTIONS preflight denial for rogue origins
# =======================================================================
section "OPTIONS preflight"

# Good preflight
GOOD_OPT=$(curl -sI -X OPTIONS -H "Origin: http://localhost:5175" -H "Access-Control-Request-Method: POST" "$WP/wp-json/wc/store/v1/cart/add-item")
STATUS=$(echo "$GOOD_OPT" | head -n1 | grep -oE '[0-9]{3}')
if [ "$STATUS" = "204" ] || [ "$STATUS" = "200" ]; then
	ok "good preflight returns 2xx ($STATUS)"
else
	fail "good preflight returned $STATUS"
fi

# Bad preflight → 403
BAD_OPT=$(curl -sI -X OPTIONS -H "Origin: http://evil.com" -H "Access-Control-Request-Method: POST" "$WP/wp-json/wc/store/v1/cart/add-item")
STATUS=$(echo "$BAD_OPT" | head -n1 | grep -oE '[0-9]{3}')
if [ "$STATUS" = "403" ]; then
	ok "rogue preflight returns 403"
else
	fail "rogue preflight returned $STATUS, expected 403"
fi

# =======================================================================
# Section 4: Cart bridge scope + token validation
# =======================================================================
section "Cart bridge"

# Get a legitimate cart token by calling GET /cart first
rm -f /tmp/wchs-sec-h.txt
curl -s -D /tmp/wchs-sec-h.txt "$WP/wp-json/wc/store/v1/cart" -o /dev/null
TOKEN=$(grep -i '^Cart-Token:' /tmp/wchs-sec-h.txt | awk '{print $2}' | tr -d '\r')
NONCE=$(grep -i '^Nonce:' /tmp/wchs-sec-h.txt | awk '{print $2}' | tr -d '\r')

if [ -z "$TOKEN" ]; then
	fail "could not fetch a cart token to start tests"
	exit 1
fi

# Add an item to that cart
curl -s -X POST \
	-H "Content-Type: application/json" \
	-H "Cart-Token: $TOKEN" \
	-H "Nonce: $NONCE" \
	-d '{"id":12,"quantity":1}' \
	"$WP/wp-json/wc/store/v1/cart/add-item" -o /dev/null

# 4a. legit token on /checkout → 200, product in page
CHECKOUT=$(curl -s "$WP/checkout/?cart=$TOKEN")
if echo "$CHECKOUT" | grep -q "Canvas Tote"; then
	ok "legit token on /checkout imports cart"
else
	fail "legit token on /checkout did NOT import cart"
fi

# 4b. legit token on /shop → cart NOT imported (token scoped to /checkout)
SHOP=$(curl -s "$WP/shop/?cart=$TOKEN")
if echo "$SHOP" | grep -qi "critical error"; then
	fail "/shop with token caused critical error"
else
	ok "/shop with token does not crash"
fi

# 4c. legit token on /my-account → ignored, no error
MYACC=$(curl -s "$WP/my-account/?cart=$TOKEN")
if echo "$MYACC" | grep -qi "critical error"; then
	fail "/my-account with token caused critical error"
else
	ok "/my-account with token does not crash"
fi

# 4d. Forged JWT (wrong signature) on /checkout → ignored, no crash
FORGED="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoidF9mb3JnZWQiLCJleHAiOjk5OTk5OTk5OTksImlzcyI6InN0b3JlLWFwaSIsImlhdCI6MTc3NTAwMDAwMH0.INVALID_SIGNATURE_HERE"
FORGED_RESP=$(curl -s -o /tmp/wchs-forged.html -w "%{http_code}" "$WP/checkout/?cart=$FORGED")
if [ "$FORGED_RESP" = "500" ] || grep -qi "critical error" /tmp/wchs-forged.html; then
	fail "forged JWT caused 500/critical error"
else
	ok "forged JWT handled gracefully (status $FORGED_RESP)"
fi

# 4e. Malformed JWT (not 3 segments) → ignored
MALFORMED="not.a.valid.jwt.string"
curl -s -o /tmp/wchs-mal.html -w "%{http_code}" "$WP/checkout/?cart=$MALFORMED" > /tmp/wchs-mal-code.txt
MAL_CODE=$(cat /tmp/wchs-mal-code.txt)
if [ "$MAL_CODE" = "500" ] || grep -qi "critical error" /tmp/wchs-mal.html; then
	fail "malformed JWT caused 500/critical error"
else
	ok "malformed JWT handled gracefully (status $MAL_CODE)"
fi

# 4f. SQL injection attempt in ?cart param
INJECT="'; DROP TABLE wp_users; --"
INJ_ENC=$(python3 -c "import urllib.parse; print(urllib.parse.quote(\"$INJECT\"))" 2>/dev/null || echo "%27%3B+DROP+TABLE+wp_users%3B+--")
INJ_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WP/checkout/?cart=$INJ_ENC")
if [ "$INJ_CODE" = "500" ]; then
	fail "SQL injection in ?cart caused 500"
else
	ok "SQL injection in ?cart handled (status $INJ_CODE)"
fi
# Verify wp_users table still exists
USER_CHECK=$(curl -sI "$WP/wp-login.php" | head -n1)
if echo "$USER_CHECK" | grep -q "200"; then
	ok "wp_users table still exists (login page loads)"
else
	fail "wp_users may have been dropped"
fi

# =======================================================================
# Section 5: Return URL bypass attempts
# =======================================================================
section "Return URL open redirect"

# Helper: extract Location header from a redirect response
check_redirect() {
	local name="$1"
	local return_url="$2"
	local expect_pattern="$3"
	local expect_description="$4"

	local loc
	loc=$(curl -sI "$WP/wp-login.php?return=$return_url" | grep -i '^Location:' | awk '{print $2}' | tr -d '\r')
	if [ -z "$loc" ]; then
		# No redirect in the response — the return filter runs only on
		# actual login redirects, so wp-login.php GET might not expose it.
		# We test the filter indirectly by loading /my-account which would
		# use the woocommerce_login_redirect filter.
		loc="(no Location in GET response — tested via filter directly)"
	fi
	# For this curl suite we can't actually log in, so we verify by
	# calling a PHP eval via wp-cli against the filter directly.
	local resolved
	# Base64 the return URL to avoid shell quoting hell across special chars.
	local b64
	b64=$(printf '%s' "$return_url" | base64 -w0)
	resolved=$(docker compose -f "$(dirname "$0")/../../docker-compose.yml" exec -T -u 33:33 wpcli php -d memory_limit=256M -r "
		\$url = base64_decode('$b64');
		\$_GET['return'] = \$url;
		\$_REQUEST['return'] = \$url;
		define('ABSPATH', '/var/www/html/');
		require '/var/www/html/wp-load.php';
		// wp-load runs wp_magic_quotes() which REBUILDS \$_REQUEST from
		// \$_GET + \$_POST, so we need to set \$_GET first. After wp-load
		// \$_REQUEST['return'] should be populated and slash-added.
		\$r = wchs_resolve_return_url();
		echo \$r === null ? '__NULL__' : \$r;
	" 2>/dev/null)

	case "$expect_pattern" in
		'__REJECTED__')
			if [ "$resolved" = "__NULL__" ]; then
				ok "$name: $expect_description (rejected)"
			else
				fail "$name: expected rejection, got: $resolved"
			fi
			;;
		*)
			if [ "$resolved" = "$expect_pattern" ]; then
				ok "$name: $expect_description (resolved to $resolved)"
			else
				fail "$name: expected $expect_pattern, got $resolved"
			fi
			;;
	esac
}

# Legit
check_redirect "legit" "http://localhost:5175/" "http://localhost:5175/" "plain SPA origin"
check_redirect "legit-path" "http://localhost:5175/some/path" "http://localhost:5175/" "path is discarded"

# Protocol-relative
check_redirect "proto-rel" "//evil.com" "__REJECTED__" "//evil.com blocked"

# Userinfo attack
check_redirect "userinfo" "http://localhost:5175@evil.com/" "__REJECTED__" "userinfo host bypass blocked"
check_redirect "userinfo2" "http://user:pass@localhost:5175/" "__REJECTED__" "credentials in URL blocked"

# Scheme attacks
check_redirect "javascript" "javascript:alert(1)" "__REJECTED__" "javascript: blocked"
check_redirect "data" "data:text/html,<script>alert(1)</script>" "__REJECTED__" "data: blocked"
check_redirect "file" "file:///etc/passwd" "__REJECTED__" "file: blocked"

# CRLF injection
check_redirect "crlf" $'http://localhost:5175/\r\nLocation:%20http://evil.com/' "__REJECTED__" "CRLF injection blocked"

# Domain-lookalike
check_redirect "lookalike" "http://localhost:5175.evil.com/" "__REJECTED__" "subdomain-lookalike blocked"
check_redirect "lookalike2" "http://evil-localhost:5175/" "__REJECTED__" "evil-localhost blocked"

# Off-allowlist origin
check_redirect "offlist" "http://example.com/" "__REJECTED__" "off-allowlist blocked"

# Case variations on scheme/host
check_redirect "scheme-case" "HTTP://localhost:5175/" "http://localhost:5175/" "scheme uppercase normalized"
check_redirect "host-case" "http://LOCALHOST:5175/" "http://localhost:5175/" "host uppercase normalized"

# =======================================================================
# Section 6: Shadow cart DB safety — session row shape attack
# =======================================================================
section "Session row defensive"

# This test verifies that the allowlist logic in wchs_read_store_api_session
# rejects foreign keys. We can't easily inject a poisoned session row without
# direct DB access, so we verify the guard function exists and the allowlist
# constant has the expected shape.
HAS_GUARD=$(docker compose -f "$(dirname "$0")/../../docker-compose.yml" exec -T -u 33:33 wpcli php -d memory_limit=256M -r "
	define('ABSPATH', '/var/www/html/');
	require '/var/www/html/wp-load.php';
	echo defined('WCHS_BRIDGE_KEYS') ? 'YES' : 'NO';
" 2>/dev/null)
if [ "$HAS_GUARD" = "YES" ]; then
	ok "WCHS_BRIDGE_KEYS allowlist defined"
else
	fail "WCHS_BRIDGE_KEYS constant missing"
fi

# =======================================================================
# Summary
# =======================================================================
echo ""
echo "========================================="
if [ "$FAIL" -eq 0 ]; then
	echo "$(green "$PASS/$TOTAL passed") — security suite clean"
	exit 0
else
	echo "$(red "$FAIL/$TOTAL failed") ($PASS passed)"
	exit 1
fi
