@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "HOST=127.0.0.1"
set "DEFAULT_PORT=8000"
set "PORT=%DEFAULT_PORT%"
set "MAX_PORT=9000"
set "DOCROOT=%~dp0"
set "PORT_FILE=%~dp0.server-port"

if exist "%PORT_FILE%" (
    set /p SAVED_PORT=<"%PORT_FILE%"
    for /f "delims=0123456789" %%A in ("!SAVED_PORT!") do set "SAVED_PORT="
    if defined SAVED_PORT (
        if !SAVED_PORT! GEQ 1 if !SAVED_PORT! LEQ 65535 (
            set "PORT=!SAVED_PORT!"
        )
    )
)

where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP was not found in PATH.
    echo Add PHP to PATH or set the full path to php.exe in this bat file.
    pause
    exit /b 1
)

:find_port
if %PORT% GTR %MAX_PORT% (
    echo [ERROR] No free port found in range 8000-9000.
    pause
    exit /b 1
)

netstat -ano | findstr /R /C:":%PORT% .*LISTENING" >nul
if not errorlevel 1 (
    if %PORT% EQU %DEFAULT_PORT% (
        echo [INFO] Port %DEFAULT_PORT% is busy. Looking for a free port...
    )
    set /a PORT+=1
    goto find_port
)

> "%PORT_FILE%" echo %PORT%

set "URL=http://%HOST%:%PORT%/"
echo [INFO] Starting server at: %URL%
echo [INFO] Docroot: %DOCROOT%
echo [INFO] Saved port to: %PORT_FILE%

start "" "%URL%"
php -S %HOST%:%PORT% -t "%DOCROOT%"

endlocal
