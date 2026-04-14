#!/usr/bin/env bash
# Build ZIP - delegates to PowerShell for Windows compatibility
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
powershell -NoProfile -ExecutionPolicy Bypass -File "$SCRIPT_DIR/build-zip.ps1"
