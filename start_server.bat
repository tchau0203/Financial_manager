@echo off
chcp 65001 >nul
echo ========================================================
echo   KHỞI ĐỘNG HỆ THỐNG QUẢN LÝ CHI TIÊU
echo ========================================================

if exist "C:\php\php.exe" (
    set PHP_CMD=C:\php\php.exe
    goto start
)

for /d %%i in (C:\php\php*) do (
    if exist "%%i\php.exe" (
        set PHP_CMD=%%i\php.exe
        goto start
    )
)

if exist "C:\xampp\php\php.exe" (
    set PHP_CMD=C:\xampp\php\php.exe
    goto start
)

where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set PHP_CMD=php
    goto start
)

echo.
echo [LỖI] Máy của bạn chưa cài PHP!
echo Bạn hãy tải PHP tại: https://windows.php.net/download/
echo và giải nén toàn bộ vào thư mục: C:\php
echo.
pause
exit /b

:start
echo [OK] Tìm thấy PHP: %PHP_CMD%
echo.
echo Đang khởi chạy máy chủ tại: http://127.0.0.1:8000
echo Mở trình duyệt và truy cập: http://127.0.0.1:8000/pages/login.html
echo (Để tắt máy chủ, bấm tổ hợp phím Ctrl + C trong cửa sổ này)
echo.
"%PHP_CMD%" -S 127.0.0.1:8000
