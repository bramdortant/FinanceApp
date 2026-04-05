#!/bin/bash

# Fix permissions for Laravel storage and database
if [ -d "/var/www/storage" ]; then
    chmod -R 777 /var/www/storage
fi

if [ -d "/var/www/bootstrap/cache" ]; then
    chmod -R 777 /var/www/bootstrap/cache
fi

if [ -d "/var/www/database" ]; then
    chmod 777 /var/www/database
    chmod 666 /var/www/database/*.sqlite 2>/dev/null
fi

# Mark git directory as safe (prevents ownership warnings)
git config --global --add safe.directory /var/www 2>/dev/null

# Run the original command
exec "$@"
