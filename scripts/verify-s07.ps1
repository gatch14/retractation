$ErrorActionPreference = 'Continue'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir
Set-Location $projectRoot

$PASS = 0
$FAIL = 0
$BASE = "retractation2026"

function Check {
    param([string]$Desc, [bool]$Ok)
    if ($Ok) {
        Write-Host "  PASS: $Desc"
        $script:PASS++
    } else {
        Write-Host "  FAIL: $Desc"
        $script:FAIL++
    }
}

Write-Host "=== S07 Slice Verification ==="
Write-Host ""

# --- License headers ---
Write-Host "[License headers]"
$licenseFiles = @(
    "$BASE/retractation2026.php",
    "$BASE/classes/RetractationEligibilityService.php",
    "$BASE/controllers/front/request.php",
    "$BASE/controllers/front/retractationlist.php"
)
foreach ($f in $licenseFiles) {
    $name = Split-Path -Leaf $f
    $hasLicense = $false
    if (Test-Path $f) {
        $content = Get-Content $f -Raw -ErrorAction SilentlyContinue
        if ($content -match '@license') { $hasLicense = $true }
    }
    Check "@license header in $name" $hasLicense
}

# --- .htaccess ---
Write-Host ""
Write-Host "[.htaccess]"
$htaccessPath = "$BASE/.htaccess"
Check ".htaccess exists" (Test-Path $htaccessPath)

$hasDeny = $false
if (Test-Path $htaccessPath) {
    $content = Get-Content $htaccessPath -Raw -ErrorAction SilentlyContinue
    if ($content -match '(?i)deny') { $hasDeny = $true }
}
Check ".htaccess contains deny rule" $hasDeny

# --- logo.png ---
Write-Host ""
Write-Host "[Logo]"
$logoPath = "$BASE/logo.png"
if (Test-Path $logoPath) {
    $size = (Get-Item $logoPath).Length
    Check "logo.png is not a 1x1 placeholder (size: ${size}B)" ($size -gt 100)
} else {
    Check "logo.png exists" $false
}

# --- ZIP ---
Write-Host ""
Write-Host "[ZIP structure]"
$zipPath = "retractation2026.zip"
if (Test-Path $zipPath) {
    Check "retractation2026.zip exists" $true

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $fullZipPath = (Resolve-Path $zipPath).Path
    $zip = [System.IO.Compression.ZipFile]::OpenRead($fullZipPath)
    $entries = @()
    foreach ($e in $zip.Entries) {
        $entries += $e.FullName -replace '\\','/'
    }
    $zip.Dispose()

    $badRoot = ($entries | Where-Object { $_ -ne '' -and $_ -notmatch '^retractation2026/' }).Count
    Check "All ZIP entries have retractation2026/ prefix" ($badRoot -eq 0)

    $devFiles = ($entries | Where-Object { $_ -match '\.git/|\.gsd/|scripts/|docs/' }).Count
    Check "No dev files (.git/.gsd/scripts/docs) in ZIP" ($devFiles -eq 0)
} else {
    Check "retractation2026.zip exists" $false
    Check "All ZIP entries have retractation2026/ prefix" $false
    Check "No dev files in ZIP" $false
}

# --- Documentation ---
Write-Host ""
Write-Host "[Documentation]"
foreach ($doc in @("docs/documentation.md", "docs/guide-cgv.md")) {
    $exists = (Test-Path $doc) -and ((Get-Item $doc).Length -gt 0)
    Check "$doc exists and non-empty" $exists
}

# --- Anti-patterns ---
Write-Host ""
Write-Host "[Anti-patterns]"
$dangerous = $false
$phpFiles = Get-ChildItem -Path $BASE -Filter '*.php' -Recurse -File
foreach ($f in $phpFiles) {
    $content = Get-Content $f.FullName -Raw -ErrorAction SilentlyContinue
    if ($content -match 'serialize\(|eval\(') {
        $dangerous = $true
        Write-Host "    WARNING: serialize()/eval() found in $($f.FullName)"
    }
}
Check "No serialize() or eval() in PHP files" (-not $dangerous)

$directAccess = $false
foreach ($f in $phpFiles) {
    $content = Get-Content $f.FullName -Raw -ErrorAction SilentlyContinue
    if ($content -match '\$_(SERVER|GET|POST|REQUEST|COOKIE)\b') {
        $directAccess = $true
        Write-Host "    WARNING: Direct superglobal access in $($f.FullName)"
    }
}
Check 'No direct $_SERVER/$_GET/$_POST/$_REQUEST/$_COOKIE' (-not $directAccess)

# --- Summary ---
Write-Host ""
$total = $PASS + $FAIL
Write-Host "=== Results: $PASS/$total passed, $FAIL failed ==="

if ($FAIL -gt 0) { exit 1 }
exit 0
