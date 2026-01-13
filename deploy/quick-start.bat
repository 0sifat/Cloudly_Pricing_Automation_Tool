@echo off
REM Quick start script for Docker deployment (Windows)

echo =========================================
echo AWS Cloudly - Docker Deployment
echo =========================================
echo.

REM Check if Docker is running
docker info >nul 2>&1
if errorlevel 1 (
    echo Error: Docker is not running. Please start Docker and try again.
    exit /b 1
)

echo Building and starting containers...
docker-compose up -d --build

if errorlevel 1 (
    echo.
    echo Error: Failed to start containers. Check the logs above for details.
    exit /b 1
)

echo.
echo =========================================
echo Containers started successfully!
echo =========================================
echo.
echo Access the application at:
echo   - Backend:  http://localhost:8080
echo   - Frontend: http://localhost:8081
echo   - Database: localhost:3307
echo.
echo View logs with: docker-compose logs -f
echo Stop containers with: docker-compose down
echo.

