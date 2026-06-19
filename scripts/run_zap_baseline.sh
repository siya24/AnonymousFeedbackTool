#!/usr/bin/env sh
set -eu

EXTERNAL_URL="${EXTERNAL_URL:-http://127.0.0.1:18084}"
INTERNAL_URL="${INTERNAL_URL:-http://127.0.0.1:18083}"
REPORT_DIR="${REPORT_DIR:-zap-reports}"
IMAGE="${IMAGE:-ghcr.io/zaproxy/zaproxy:stable}"

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
REPORT_PATH="$REPO_ROOT/$REPORT_DIR"

mkdir -p "$REPORT_PATH"

echo "Using ZAP image: $IMAGE"
echo "Reports directory: $REPORT_PATH"
echo "Running passive baseline scans only (no active attack)."

echo "Scanning external target: $EXTERNAL_URL"
docker run --rm -t --network host \
  -v "$REPO_ROOT:/zap/wrk" \
  "$IMAGE" \
  zap-baseline.py \
  -t "$EXTERNAL_URL" \
  -r "/zap/wrk/$REPORT_DIR/external-baseline.html" \
  -J "/zap/wrk/$REPORT_DIR/external-baseline.json"

echo "Scanning internal target: $INTERNAL_URL"
docker run --rm -t --network host \
  -v "$REPO_ROOT:/zap/wrk" \
  "$IMAGE" \
  zap-baseline.py \
  -t "$INTERNAL_URL" \
  -r "/zap/wrk/$REPORT_DIR/internal-baseline.html" \
  -J "/zap/wrk/$REPORT_DIR/internal-baseline.json"

echo "Baseline scans completed successfully."
echo "Reports generated under: $REPORT_PATH"
