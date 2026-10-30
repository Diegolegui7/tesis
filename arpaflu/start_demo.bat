@echo off
setlocal EnableExtensions
chcp 65001 >nul
title ARPAFLU - Demo guiada
cd /d "%~dp0"
set "APP=%~dp0"
if "%APP:~-1%"=="\" set "APP=%APP:~0,-1%"
set "PUERTO=8088"

echo.
echo  ============================================================
echo    ARPAFLU - Demo guiada paso a paso
echo  ============================================================
echo.

rem --- 1. Buscar PHP de WAMP (se usa la version mas nueva de la serie 7)
set "PHP="
for /d %%D in ("C:\wamp64\bin\php\php7*") do if exist "%%D\php.exe" set "PHP=%%D\php.exe"
if not defined PHP for /d %%D in ("C:\wamp\bin\php\php7*") do if exist "%%D\php.exe" set "PHP=%%D\php.exe"
if not defined PHP (
  echo  No encontre PHP. Instale WAMP ^(www.wampserver.com^) y vuelva a ejecutar este archivo.
  echo.
  pause
  exit /b 1
)
echo  PHP encontrado: %PHP%
echo.

rem --- 2. Cerrar un servidor de una demo anterior, si quedo abierto
taskkill /fi "WINDOWTITLE eq ARPAFLU servidor*" /f >nul 2>&1

rem --- 2b. Si existe la carpeta de WAMP (www), dejar ahi una copia al dia del sistema (no toca la carpeta almacen)
if exist "C:\wamp64\www" if /i not "%APP%"=="C:\wamp64\www\arpaflu" (
  robocopy "%APP%" "C:\wamp64\www\arpaflu" /E /XD almacen .git /NFL /NDL /NJH /NJS /NP >nul
  echo  Copia actualizada en C:\wamp64\www\arpaflu ^(tambien se ve en http://localhost/arpaflu/ con WAMP en verde^)
)

rem --- 3. Esperar a MySQL (abre WAMP si hace falta) y cargar los datos de la demo
"%PHP%" "%APP%\demo\preparar.php"
if errorlevel 1 (
  echo.
  echo  No se pudo preparar la demo. Revise que WAMP este en verde.
  echo.
  pause
  exit /b 1
)

rem --- 4. Iniciar el sistema en una ventana minimizada (no la cierre mientras use la demo)
echo.
echo  Iniciando el sistema...
start "ARPAFLU servidor (no cerrar)" /min "%PHP%" -d upload_max_filesize=12M -d post_max_size=14M -d display_errors=0 -S 127.0.0.1:%PUERTO% -t "%APP%" "%APP%\demo\router.php"
timeout /t 2 /nobreak >nul

rem --- 5. Abrir el navegador y mostrar la guia en esta ventana (en tiempo real)
start "" "http://127.0.0.1:%PUERTO%/"
"%PHP%" "%APP%\demo\guia.php" %PUERTO%

echo.
echo  Cerrando el sistema...
taskkill /fi "WINDOWTITLE eq ARPAFLU servidor*" /f >nul 2>&1
echo.
pause
