@echo off
echo ========================================
echo   CartoFLU - Serveur de tuiles local
echo ========================================
echo.
echo Demarrage du serveur sur http://localhost:8080
echo.
echo Laissez cette fenetre ouverte pendant toute la session.
echo Pour arreter le serveur : fermez cette fenetre ou Ctrl+C
echo.
cd /d "%~dp0"
python -m http.server 8080
pause
