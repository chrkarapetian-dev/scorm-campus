@echo off
setlocal
set "NODE_BIN="
where node.exe >nul 2>nul
if not errorlevel 1 set "NODE_BIN=node.exe"
if not defined NODE_BIN if exist "%USERPROFILE%\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" set "NODE_BIN=%USERPROFILE%\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe"
if not defined NODE_BIN (
  echo Node.js was not found. Open index.html for the offline course demo, or install Node.js to enable SCORM uploads and server AI.
  pause
  exit /b 1
)
pushd "%~dp0"
"%NODE_BIN%" server.js
popd
pause
