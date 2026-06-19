#!/usr/bin/env sh
set -eu

EXTERNAL_BASE_URL="${EXTERNAL_BASE_URL:-http://127.0.0.1:18084}"
INTERNAL_BASE_URL="${INTERNAL_BASE_URL:-http://127.0.0.1:18083}"
REPORT_DIR="${REPORT_DIR:-zap-reports}"
IMAGE="${IMAGE:-ghcr.io/zaproxy/zaproxy:stable}"
RULES_FILE="${RULES_FILE:-.zap/rules-high-only.conf}"
SPIDER_MINUTES="${SPIDER_MINUTES:-2}"

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
REPORT_PATH="$REPO_ROOT/$REPORT_DIR"
RULES_PATH="$REPO_ROOT/$RULES_FILE"

mkdir -p "$REPORT_PATH"
mkdir -p "$(dirname "$RULES_PATH")"

if [ ! -f "$RULES_PATH" ]; then
  cat > "$RULES_PATH" <<'EOF'
FAIL	.*	High
WARN	.*	Medium
INFO	.*	Low
IGNORE	.*	Informational
EOF
fi

ALLOWLIST="
$EXTERNAL_BASE_URL/api/health/storage
$EXTERNAL_BASE_URL/api/categories
$EXTERNAL_BASE_URL/api/statuses
$INTERNAL_BASE_URL/api/categories
$INTERNAL_BASE_URL/api/provinces
$INTERNAL_BASE_URL/api/statuses
$INTERNAL_BASE_URL/api/stages
"

EXCLUDE_REGEX_1='.*/api/hr/logout.*'
EXCLUDE_REGEX_2='.*/api/hr/login.*'
EXCLUDE_REGEX_3='.*/api/hr/cases/.*/co-investigators.*'

is_allowed_base() {
  case "$1" in
    "$EXTERNAL_BASE_URL"*|"$INTERNAL_BASE_URL"*) return 0 ;;
    *) return 1 ;;
  esac
}

is_excluded() {
  printf '%s' "$1" | grep -E "$EXCLUDE_REGEX_1|$EXCLUDE_REGEX_2|$EXCLUDE_REGEX_3" >/dev/null 2>&1
}

safe_name() {
  echo "$1" | tr '[:upper:]' '[:lower:]' | sed -E 's#^https?://##; s#[^a-z0-9]+#-#g; s#^-+##; s#-+$##'
}

echo "Using ZAP image: $IMAGE"
echo "Reports directory: $REPORT_PATH"
echo "Rules file: $RULES_PATH"

RULES_CONTAINER_PATH="/zap/wrk/$(printf '%s' "$RULES_FILE" | sed 's#\\#/#g')"
REPORT_CONTAINER_DIR="/zap/wrk/$(printf '%s' "$REPORT_DIR" | sed 's#\\#/#g')"

TARGET_COUNT=0
printf '%s\n' "$ALLOWLIST" | while IFS= read -r target; do
  target=$(printf '%s' "$target" | sed 's/^\s*//; s/\s*$//')
  [ -z "$target" ] && continue

  if ! is_allowed_base "$target"; then
    echo "ERROR: Allowlist entry outside approved base URLs: $target" >&2
    exit 1
  fi

  if is_excluded "$target"; then
    echo "Skipping excluded target: $target"
    continue
  fi

  TARGET_COUNT=$((TARGET_COUNT + 1))
  NAME=$(safe_name "$target")
  [ -z "$NAME" ] && NAME="scan-target"

  echo "Scanning target: $target"
  docker run --rm -t --network host \
    -v "$REPO_ROOT:/zap/wrk" \
    "$IMAGE" \
    zap-full-scan.py \
    -t "$target" \
    -m "$SPIDER_MINUTES" \
    -c "$RULES_CONTAINER_PATH" \
    -r "$REPORT_CONTAINER_DIR/$NAME-active.html" \
    -J "$REPORT_CONTAINER_DIR/$NAME-active.json"
done

echo "Controlled active scans completed successfully."
echo "Reports generated under: $REPORT_PATH"
