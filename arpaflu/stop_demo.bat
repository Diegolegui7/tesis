@echo off
chcp 65001 >nul
taskkill /fi "WINDOWTITLE eq ARPAFLU servidor*" /f >nul 2>&1
echo  Sistema de la demo detenido.
timeout /t 2 /nobreak >nul
