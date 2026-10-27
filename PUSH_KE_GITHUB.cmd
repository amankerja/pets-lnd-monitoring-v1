@echo off
title Upload ke GitHub (pets-lnd-monitoring-v1)
echo ========================================================
echo  MENGUNGGAH SELURUH KODE KE GITHUB REPOSITORY:
echo  https://github.com/amankerja/pets-lnd-monitoring-v1.git
echo ========================================================
echo.
echo Menjalankan: git push -u origin main...
echo (Jika jendela login GitHub muncul di browser/layar, silakan selesaikan login)
echo.
git push -u origin main
echo.
if %ERRORLEVEL% equ 0 (
    echo ========================================================
    echo  BERHASIL! Kode projek telah terunggah ke GitHub.
    echo ========================================================
) else (
    echo ========================================================
    echo  Gagal mengunggah. Pastikan koneksi internet aktif dan
    echo  akun GitHub Anda memiliki akses write ke repository.
    echo ========================================================
)
echo.
pause
