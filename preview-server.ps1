# ===============================================================================
# PT PANI BERSAMA JAYA - LEARNING & DEVELOPMENT MANAGEMENT SYSTEM
# Standalone High-Performance Preview Server (HttpListener Engine)
# ===============================================================================

$port = 8080
$htmlPath = Join-Path $PSScriptRoot "public\preview.html"
$trainingCatalogPath = Join-Path $PSScriptRoot "database\seeders\mdka_trainings.json"

if (-not (Test-Path $htmlPath)) {
    Write-Host "[ERROR] File tidak ditemukan: $htmlPath" -ForegroundColor Red
    pause
    exit 1
}

if (-not (Test-Path $trainingCatalogPath)) {
    Write-Host "[ERROR] Dataset pelatihan tidak ditemukan: $trainingCatalogPath" -ForegroundColor Red
    exit 1
}

function Get-PreviewHtmlBytes {
    $html = [System.IO.File]::ReadAllText($htmlPath)
    $catalogItems = ConvertFrom-Json -InputObject ([System.IO.File]::ReadAllText($trainingCatalogPath))
    $trainingCatalog = ConvertTo-Json -InputObject @($catalogItems) -Depth 4 -Compress
    $trainingCatalog = $trainingCatalog.Replace('<', '\u003c').Replace('>', '\u003e')
    $html = $html.Replace("/*PREVIEW_TRAINING_CATALOG*/ []", $trainingCatalog)
    return [System.Text.Encoding]::UTF8.GetBytes($html)
}

# 1. Bersihkan proses lama yang mungkin masih menyandera port 8080
try {
    $zombies = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique
    foreach ($zPid in $zombies) {
        if ($zPid -and $zPid -ne $PID -and $zPid -ne 0) {
            Stop-Process -Id $zPid -Force -ErrorAction SilentlyContinue
        }
    }
    Start-Sleep -Milliseconds 250
} catch {}

# 2. Inisialisasi HttpListener Nativ Windows (HTTP.SYS)
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://localhost:$port/")
$listener.Prefixes.Add("http://127.0.0.1:$port/")

try {
    $listener.Start()
}
catch {
    Write-Host "===============================================================================" -ForegroundColor Yellow
    Write-Host "  Pemberitahuan: Port $port sedang digunakan atau server sudah aktif." -ForegroundColor Green
    Write-Host "  Membuka peramban web: http://localhost:$port" -ForegroundColor Cyan
    Write-Host "===============================================================================" -ForegroundColor Yellow
    try { Start-Process "http://localhost:$port" } catch {}
    Start-Sleep -Seconds 2
    exit 0
}

# 3. Tampilkan Banner Status Aktif
Clear-Host
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host "      PT PANI BERSAMA JAYA - LEARNING & DEVELOPMENT MANAGEMENT SYSTEM         " -ForegroundColor Green
Write-Host "                Enterprise Competency & Training Platform                     " -ForegroundColor Green
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host "  [STATUS] Server Lokal Aktif pada URL: http://localhost:$port" -ForegroundColor Yellow
Write-Host "  [INFO]   Membuka peramban web (browser) Anda secara otomatis..." -ForegroundColor Gray
Write-Host "  [PENTING] JANGAN TUTUP jendela ini selama Anda menggunakan aplikasi web." -ForegroundColor White
Write-Host "  [STOP]   Tekan Ctrl+C pada jendela ini jika ingin mematikan server." -ForegroundColor Gray
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host ""

# 4. Buka Browser Otomatis
try {
    Start-Process "http://localhost:$port"
} catch {}

# 5. In-Memory Cache dengan Auto-Reload jika preview.html Diedit
$htmlBytes = Get-PreviewHtmlBytes
$lastModified = (Get-Item $htmlPath).LastWriteTime
$catalogLastModified = (Get-Item $trainingCatalogPath).LastWriteTime

# 6. Infinite Resilient Request Loop
try {
    while ($listener.IsListening) {
        $context = $null
        try {
            $context = $listener.GetContext()
            $request = $context.Request
            $response = $context.Response

            # Abaikan favicon request dengan status 204
            if ($request.RawUrl -match "^/favicon\.ico") {
                $response.StatusCode = 204
                $response.Close()
                continue
            }

            # Deteksi perubahan file agar hot-reload bekerja otomatis
            $currentMod = (Get-Item $htmlPath).LastWriteTime
            $currentCatalogMod = (Get-Item $trainingCatalogPath).LastWriteTime
            if ($currentMod -ne $lastModified -or $currentCatalogMod -ne $catalogLastModified) {
                $htmlBytes = Get-PreviewHtmlBytes
                $lastModified = $currentMod
                $catalogLastModified = $currentCatalogMod
            }

            # Sajikan HTML dengan header UTF-8 & Anti-Cache
            $response.ContentType = "text/html; charset=utf-8"
            $response.Headers.Add("Cache-Control", "no-cache, no-store, must-revalidate")
            $response.Headers.Add("Pragma", "no-cache")
            $response.StatusCode = 200

            # Kirim konten secara sinkron & aman
            $response.Close($htmlBytes, $true)
        }
        catch {
            # Abaikan jika koneksi peramban di-refresh atau dibatalkan sepihak oleh klien
            if ($context -and $context.Response) {
                try { $context.Response.Abort() } catch {}
            }
        }
    }
}
finally {
    if ($null -ne $listener) {
        try { $listener.Stop() } catch {}
        try { $listener.Close() } catch {}
    }
}
