@echo off
rem Testy, commit, push i deploy jedna komenda: ship "opis zmiany"
setlocal
if "%~1"=="" (
    echo Uzycie: ship "opis zmiany"
    exit /b 1
)

call composer ci:check || exit /b 1
git add -A
git diff --cached --quiet || git commit -m "%~1" || exit /b 1
call deploy.bat
