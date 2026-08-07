#!/usr/bin/env bash
set -euo pipefail

ADDON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ASSETS_DIR="${ADDON_DIR}/assets"

get_latest_datatables_v1() {
  node -e "const cp=require('child_process');const versions=JSON.parse(cp.execSync('npm view datatables.net@1 versions --json',{encoding:'utf8'}));console.log(versions[versions.length-1]);"
}

get_latest_echarts_v5() {
  node -e "const cp=require('child_process');const versions=JSON.parse(cp.execSync('npm view echarts@5 versions --json',{encoding:'utf8'}));console.log(versions[versions.length-1]);"
}

DT_VERSION="${DT_VERSION:-$(get_latest_datatables_v1)}"
ECHARTS_VERSION="${ECHARTS_VERSION:-$(get_latest_echarts_v5)}"

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
