#!/usr/bin/env bash
set -euo pipefail

ADDON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ASSETS_DIR="${ADDON_DIR}/assets"

get_latest_stable_version() {
  local package_name="$1"
  local major="$2"

  node - "${package_name}" "${major}" <<'NODE'
const childProcess = require('node:child_process');

const packageName = process.argv[2];
const major = process.argv[3];
const output = childProcess.execFileSync(
  'npm',
  ['view', packageName, 'versions', '--json'],
  {encoding: 'utf8'},
);
const versions = [JSON.parse(output)]
  .flat(Infinity)
  .filter((version) => new RegExp(`^${major}\\.\\d+\\.\\d+$`).test(version))
  .sort((left, right) => left.localeCompare(right, 'en', {numeric: true}));

if (versions.length === 0) {
  throw new Error(`No stable ${packageName} ${major}.x version found`);
}

process.stdout.write(versions.at(-1));
NODE
}

DT_VERSION="${DT_VERSION:-$(get_latest_stable_version datatables.net 1)}"
ECHARTS_VERSION="${ECHARTS_VERSION:-$(get_latest_stable_version echarts 5)}"

if [[ ! "${DT_VERSION}" =~ ^1\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid DataTables 1.x version: ${DT_VERSION}" >&2
  exit 1
fi

if [[ ! "${ECHARTS_VERSION}" =~ ^5\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid ECharts 5.x version: ${ECHARTS_VERSION}" >&2
  exit 1
fi

DT_JS_URL="https://cdn.datatables.net/v/bs/dt-${DT_VERSION}/datatables.min.js"
DT_CSS_URL="https://cdn.datatables.net/v/bs/dt-${DT_VERSION}/datatables.min.css"
ECHARTS_JS_URL="https://cdn.jsdelivr.net/npm/echarts@${ECHARTS_VERSION}/dist/echarts.min.js"

echo "Updating DataTables to ${DT_VERSION} ..."
curl -fLsS "${DT_JS_URL}" -o "${ASSETS_DIR}/datatables.min.js"
curl -fLsS "${DT_CSS_URL}" -o "${ASSETS_DIR}/datatables.min.css"

echo "Updating ECharts to ${ECHARTS_VERSION} ..."
curl -fLsS "${ECHARTS_JS_URL}" -o "${ASSETS_DIR}/echarts.min.js"

echo "Done. Updated files:"
printf ' - %s\n' \
  "${ASSETS_DIR}/datatables.min.js" \
  "${ASSETS_DIR}/datatables.min.css" \
  "${ASSETS_DIR}/echarts.min.js"
