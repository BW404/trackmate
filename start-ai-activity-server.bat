@echo off
echo ============================================================
echo TrackMate AI Activity Detection Server
echo ============================================================
echo.
echo Starting Python Flask server...
echo Make sure Ollama is running before starting this server
echo.
echo Server will run on: http://localhost:5000
echo Press Ctrl+C to stop
echo.
echo ============================================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.7 or higher
    pause
    exit /b 1
)

REM Check if required packages are installed
python -c "import flask" >nul 2>&1
if errorlevel 1 (
    echo Installing required Python packages...
    pip install flask flask-cors pillow requests
    echo.
)

REM Start the AI server
python ai_server.py

pause
