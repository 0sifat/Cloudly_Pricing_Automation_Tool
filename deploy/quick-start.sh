#!/bin/bash
# Quick start script for Docker deployment

echo "========================================="
echo "AWS Cloudly - Docker Deployment"
echo "========================================="
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running. Please start Docker and try again."
    exit 1
fi

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "Error: docker-compose is not installed. Please install docker-compose and try again."
    exit 1
fi

echo "Building and starting containers..."
docker-compose up -d --build

if [ $? -eq 0 ]; then
    echo ""
    echo "========================================="
    echo "Containers started successfully!"
    echo "========================================="
    echo ""
    echo "Access the application at:"
    echo "  - Backend:  http://localhost:8080"
    echo "  - Frontend: http://localhost:8081"
    echo "  - Database: localhost:3307"
    echo ""
    echo "View logs with: docker-compose logs -f"
    echo "Stop containers with: docker-compose down"
    echo ""
else
    echo ""
    echo "Error: Failed to start containers. Check the logs above for details."
    exit 1
fi

