#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-${GITHUB_REF_NAME:-}}"
if [[ -z "${VERSION}" ]]; then
  echo "Version is required (use tag vX.Y.Z or pass as argument)." >&2
  exit 1
fi

ROOT_DIR="$(pwd)"
DIST_DIR="$(mktemp -d)"
PLUGIN_DIR="${DIST_DIR}/ai_gateway"

cleanup() {
  rm -rf "${DIST_DIR}"
}
trap cleanup EXIT

mkdir -p "${PLUGIN_DIR}"

copy_items() {
  local include_src="$1"

  rsync -a --delete \
    --exclude ".git" \
    --exclude "node_modules" \
    --exclude "ai-gateway-dist" \
    --exclude "*.zip" \
    "${ROOT_DIR}/admin" \
    "${ROOT_DIR}/build" \
    "${ROOT_DIR}/core" \
    "${ROOT_DIR}/editor" \
    "${ROOT_DIR}/rest" \
    "${ROOT_DIR}/ai_gateway.php" \
    "${ROOT_DIR}/README.md" \
    "${ROOT_DIR}/LICENSE" \
    "${ROOT_DIR}/CHANGELOG.md" \
    "${PLUGIN_DIR}/"

  if [[ "${include_src}" == "true" ]]; then
    rsync -a --delete \
      --exclude ".git" \
      --exclude "node_modules" \
      "${ROOT_DIR}/src" \
      "${ROOT_DIR}/package.json" \
      "${ROOT_DIR}/package-lock.json" \
      "${PLUGIN_DIR}/"
  fi
}

copy_items "false"
(
  cd "${DIST_DIR}"
  zip -r "${ROOT_DIR}/${VERSION}_no_src.zip" "ai_gateway" >/dev/null
)

rm -rf "${PLUGIN_DIR}"
mkdir -p "${PLUGIN_DIR}"
copy_items "true"
(
  cd "${DIST_DIR}"
  zip -r "${ROOT_DIR}/${VERSION}_src.zip" "ai_gateway" >/dev/null
)

echo "Created ${VERSION}_no_src.zip and ${VERSION}_src.zip"
