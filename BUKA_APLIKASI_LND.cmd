@echo off
setlocal EnableExtensions
title PT PANI BERSAMA JAYA - Enterprise L^&D Management System
color 0A
cls

echo ===============================================================================
echo           PT PANI BERSAMA JAYA - LEARNING ^& DEVELOPMENT SYSTEM
echo                Enterprise Training ^& Competency Matrix
echo ===============================================================================
echo.

cd /d "%~dp0"

:: Cek apakah PHP terpasang di PATH Windows
where php >nul 2>&1
if errorlevel 1 goto RUN_PREVIEW

:: Jika PHP terdeteksi, jalankan Laravel + Vite secara penuh
echo [1/3] Menyiapkan environment Laravel ^& Vite...
if not exist ".env" (
    echo [INFO] Menyiapkan file .env...
    copy .env.example .env >nul
)
if not exist "vendor" (
    echo [INFO] Menjalankan composer install...
    call composer install
)
if not exist "node_modules" (
    echo [INFO] Menjalankan npm install...
    call npm install
)

echo [2/3] Menjalankan Database Migration ^& Seeder MDKA...
call php artisan migrate:fresh --seed --force

echo [3/3] Menjalankan Server Laravel Port 8000 dan Vite...
start "Laravel Server" cmd /k "php artisan serve"
start "Vite Dev Server" cmd /k "npm run dev"

timeout /t 3 >nul
start http://127.0.0.1:8000

echo ===============================================================================
echo   Server Laravel ^& Vite Berhasil Dijalankan!
echo   Buka browser di: http://127.0.0.1:8000
echo   Jangan tutup jendela ini selama menggunakan aplikasi.
echo ===============================================================================
echo.
pause
exit /b

:RUN_PREVIEW
echo [INFO] PHP belum terdeteksi di PATH Windows.
echo [+] Menjalankan Standalone Preview Server di Port 8080...
echo [+] Membuka browser di: http://localhost:8080
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0preview-server.ps1"
echo.
echo Server telah berhenti.
pause
exit /b
