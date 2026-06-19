#!/usr/bin/env sh
set -eu

REPO_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
MANIFEST_PATH="${1:-$REPO_ROOT/internal/public/assets/vendor/checksums.sha256}"
VENDOR_ROOT="$REPO_ROOT/internal/public/assets/vendor"

if [ ! -f "$MANIFEST_PATH" ]; then
  echo "Checksum manifest not found: $MANIFEST_PATH" >&2
  exit 1
fi

if [ ! -d "$VENDOR_ROOT" ]; then
  echo "Vendor asset directory not found: $VENDOR_ROOT" >&2
  exit 1
fi

if command -v sha256sum >/dev/null 2>&1; then
  hash_file() {
    sha256sum "$1" | awk '{print $1}'
  }
elif command -v shasum >/dev/null 2>&1; then
  hash_file() {
    shasum -a 256 "$1" | awk '{print $1}'
  }
else
  echo "No SHA-256 utility found. Install sha256sum or shasum." >&2
  exit 1
fi

failures=0
while IFS= read -r line || [ -n "$line" ]; do
  trimmed="$(printf '%s' "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  [ -z "$trimmed" ] && continue
  case "$trimmed" in
    \#*) continue ;;
  esac

  expected="$(printf '%s' "$trimmed" | awk '{print $1}')"
  relative="$(printf '%s' "$trimmed" | cut -d' ' -f2- | sed 's/^[[:space:]]*//')"

  if [ "${#expected}" -ne 64 ] || [ -z "$relative" ]; then
    echo "Invalid checksum line format: $line" >&2
    failures=$((failures + 1))
    continue
  fi

  target="$VENDOR_ROOT/$relative"
  if [ ! -f "$target" ]; then
    echo "Missing asset file: $relative" >&2
    failures=$((failures + 1))
    continue
  fi

  actual="$(hash_file "$target")"
  if [ "$actual" != "$expected" ]; then
    echo "Hash mismatch for $relative (expected: $expected, actual: $actual)" >&2
    failures=$((failures + 1))
  fi
done < "$MANIFEST_PATH"

if [ "$failures" -gt 0 ]; then
  echo "Vendor asset checksum verification failed with $failures issue(s)." >&2
  exit 1
fi

echo "Vendor asset checksum verification passed."
