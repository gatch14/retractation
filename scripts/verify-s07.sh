#!/usr/bin/env bash
# S07 verification - delegates to PowerShell for Windows compatibility
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
powershell -NoProfile -ExecutionPolicy Bypass -File "$SCRIPT_DIR/verify-s07.ps1"
