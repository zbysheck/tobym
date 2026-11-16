@echo off
rem Jednorazowa konfiguracja: klucz SSH do MyDevil + przygotowanie serwera.
rem Uruchamiaj z katalogu projektu: deploy\setup.bat
setlocal
set HOST=zbigniewkisly@s59.mydevil.net
set KEY=%USERPROFILE%\.ssh\id_ed25519

if not exist "%USERPROFILE%\.ssh" mkdir "%USERPROFILE%\.ssh"
if not exist "%KEY%.pub" ssh-keygen -t ed25519 -f "%KEY%" -N ""

echo Instaluje Twoj klucz SSH na serwerze. Podaj haslo SSH do MyDevil:
type "%KEY%.pub" | ssh %HOST% "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys" || exit /b 1

scp deploy/setup-server.sh %HOST%:tobym-setup.sh || exit /b 1
ssh -t %HOST% "bash tobym-setup.sh" || exit /b 1
