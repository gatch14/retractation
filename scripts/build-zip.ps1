$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir
$zipName = "retractation2026.zip"
$zipPath = Join-Path $projectRoot $zipName
$srcPath = Join-Path $projectRoot "retractation2026"

if (Test-Path $zipPath) { Remove-Item -Force $zipPath }

$tempDir = Join-Path $env:TEMP 'retractation2026_build'
if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }
New-Item -ItemType Directory -Path $tempDir | Out-Null

Copy-Item -Path $srcPath -Destination $tempDir -Recurse

Get-ChildItem -Path $tempDir -Recurse -Directory -Filter '.git' | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $tempDir -Recurse -Directory -Filter '.gsd' | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $tempDir -Recurse -Directory -Filter 'scripts' | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $tempDir -Recurse -Directory -Filter 'docs' | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $tempDir -Recurse -File -Filter '*.sh' | Remove-Item -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $tempDir -Recurse -File -Filter '.gitignore' | Remove-Item -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $tempDir -Recurse -File -Filter '.mcp.json' | Remove-Item -Force -ErrorAction SilentlyContinue

Compress-Archive -Path (Join-Path $tempDir 'retractation2026') -DestinationPath $zipPath -Force
Remove-Item -Recurse -Force $tempDir

Write-Host ""
Write-Host "=== ZIP created: $zipName ==="
Write-Host ""

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
foreach ($entry in $zip.Entries) {
    Write-Host $entry.FullName
}
$zip.Dispose()

Write-Host ""
Write-Host "Done."
