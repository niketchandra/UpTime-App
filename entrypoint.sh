#!/bin/sh

CONFIG_FILE="/var/www/localhost/htdocs/config.php"

# Replace empty values in config.php with env vars
sed -i "s|define('DATABASE_SERVER',   '');|define('DATABASE_SERVER',   '${DATABASE_SERVER}');|" "$CONFIG_FILE"
sed -i "s|define('DATABASE_USERNAME', '');|define('DATABASE_USERNAME', '${DATABASE_USERNAME}');|" "$CONFIG_FILE"
sed -i "s|define('DATABASE_PASSWORD', '');|define('DATABASE_PASSWORD', '${DATABASE_PASSWORD}');|" "$CONFIG_FILE"
sed -i "s|define('DATABASE_NAME',     '');|define('DATABASE_NAME',     '${DATABASE_NAME}');|" "$CONFIG_FILE"
sed -i "s|define('SITE_URL',          '');|define('SITE_URL',          '${SITE_URL}');|" "$CONFIG_FILE"

# Run Apache in foreground
exec httpd -DFOREGROUND
