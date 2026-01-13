# Docker Deployment Guide

This directory contains all the necessary files to dockerize and deploy the AWS Cloudly application.

## Structure

- `Dockerfile.frontend` - Nginx container for serving static assets
- `Dockerfile.backend` - PHP-FPM with Nginx container for the application
- `Dockerfile.database` - MySQL 8.0 container
- `docker-compose.yml` - Orchestrates all three containers
- `nginx-backend.conf` - Nginx configuration for PHP backend
- `nginx-frontend.conf` - Nginx configuration for static assets
- `init-db.sql` - Database initialization script
- `start.sh` - Startup script for backend container
- `config.docker.php` - Docker-specific database configuration

## Prerequisites

- Docker installed on your system
- Docker Compose installed (usually comes with Docker Desktop)

## Quick Start

1. **Navigate to the deploy directory:**
   ```bash
   cd deploy
   ```

2. **Build and start all containers:**
   ```bash
   docker-compose up -d --build
   ```

3. **Access the application:**
   - Backend (Main Application): http://localhost:8080
   - Frontend (Static Assets): http://localhost:8081
   - Database: localhost:3307

4. **View logs:**
   ```bash
   docker-compose logs -f
   ```

5. **Stop all containers:**
   ```bash
   docker-compose down
   ```

## Container Details

### Backend Container (Port 8080)
- PHP 8.2-FPM
- Nginx web server
- Serves the main PHP application
- Connected to database container

### Frontend Container (Port 8081)
- Nginx Alpine
- Serves static assets (CSS, JS, images)
- Can be integrated with backend or used separately

### Database Container (Port 3307)
- MySQL 8.0
- Database: `aws_calc`
- User: `app_user`
- Password: `app_password`
- Root Password: `rootpassword`

## Environment Variables

You can customize the database connection by modifying the environment variables in `docker-compose.yml`:

```yaml
environment:
  DB_HOST: database
  DB_NAME: aws_calc
  DB_USER: app_user
  DB_PASS: app_password
  DB_PORT: 3306
```

## Database Configuration

The application uses `config/database.php` for database connections. For Docker deployment, you may want to:

1. Update `config/database.php` to use environment variables, OR
2. Use the provided `config.docker.php` and update includes in your application

## Volumes

- `db_data` - Persistent storage for MySQL data
- Application code is mounted as a volume for development (can be removed for production)

## Production Considerations

For production deployment:

1. **Remove volume mounts** for application code (copy files into image instead)
2. **Use environment-specific secrets** for database passwords
3. **Enable SSL/TLS** for nginx
4. **Set up proper logging** and monitoring
5. **Use a reverse proxy** (like Traefik or Nginx) in front of containers
6. **Backup database volumes** regularly

## Troubleshooting

### Database connection issues
- Check if database container is healthy: `docker-compose ps`
- Verify environment variables in docker-compose.yml
- Check database logs: `docker-compose logs database`

### PHP errors
- Check backend logs: `docker-compose logs backend`
- Verify file permissions: `docker-compose exec backend ls -la /var/www/html`

### Port conflicts
- If ports 8080, 8081, or 3307 are in use, modify them in docker-compose.yml

## Commands Reference

```bash
# Build containers
docker-compose build

# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f [service_name]

# Execute command in container
docker-compose exec [service_name] [command]

# Rebuild specific service
docker-compose up -d --build [service_name]

# Remove all containers and volumes
docker-compose down -v
```

