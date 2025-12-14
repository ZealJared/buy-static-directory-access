#!/bin/bash
set -e
echo "[DEBUG] Custom entrypoint script running..."
echo "[DEBUG] Running as user: $(whoami), UID: $(id -u), GID: $(id -g)"

# Wait for WordPress to be ready
until curl -s http://localhost/wp-login.php > /dev/null; do
  echo "[DEBUG] Waiting for WordPress to be ready..."
  sleep 3
done


# Install WordPress if not already installed
if ! wp core is-installed; then
  echo "[DEBUG] Running wp core install..."
  wp core install --url="http://localhost:8080" --title="Sample Site" --admin_user="admin" --admin_password="password" --admin_email="admin@example.com" || echo "[ERROR] wp core install failed"
else
  echo "[DEBUG] WordPress already installed."
fi


# Install WooCommerce if not present
echo "[DEBUG] Checking/installing WooCommerce..."
wp plugin is-installed woocommerce || wp plugin install woocommerce --activate

# Run extra setup (test product, user, order)
if [ -f /var/www/html/wp-setup-extra.sh ]; then
  echo "[DEBUG] Running extra setup script..."
  bash /var/www/html/wp-setup-extra.sh
else
  echo "[DEBUG] No extra setup script found."
fi
