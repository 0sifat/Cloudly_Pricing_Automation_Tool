#!/bin/bash
# Docker entrypoint script for backend to update database config

# Update database.php with environment variables if they exist
# Use environment variables or fallback to defaults
DB_HOST=${DB_HOST:-database}
DB_NAME=${DB_NAME:-aws_calc}
DB_USER=${DB_USER:-app_user}
DB_PASS=${DB_PASS:-app_password}
DB_PORT=${DB_PORT:-3306}

# Create/update database.php with Docker environment variables
cat > /var/www/html/config/database.php <<EOF
<?php
\$host = '$DB_HOST';
\$db = '$DB_NAME';
\$user = '$DB_USER';
\$pass = '$DB_PASS';
\$port = '$DB_PORT';

\$conn = new mysqli(\$host, \$user, \$pass, \$db, \$port);

if (\$conn->connect_error) {
    die("Connection failed: " . \$conn->connect_error);
}
?>
EOF

# Execute the original start script
exec /usr/local/bin/start.sh

