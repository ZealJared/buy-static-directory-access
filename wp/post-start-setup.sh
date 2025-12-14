#!/bin/bash
set -e

# Wait for database to be ready
echo "[DEBUG] Waiting for database to be ready..."
until mariadb --host=db --user=wp --password=wp --skip-ssl -e 'SELECT 1' wp > /dev/null 2>&1; do
  echo "[DEBUG] Waiting for DB..."
  sleep 3
done
#!/bin/bash
set -e



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

# WooCommerce automation: create pages, set store details, disable wizard
echo "[DEBUG] Running WooCommerce automation..."
wp wc tool run install_pages --user=admin

wp option update woocommerce_store_address "123 Main St"
wp option update woocommerce_store_city "Sample City"
wp option update woocommerce_store_postcode "12345"
wp option update woocommerce_default_country "US:CA"
wp option update woocommerce_currency "USD"
wp option update woocommerce_admin_install_timestamp $(date +%s)
# Mark WooCommerce onboarding and task list as complete
wp option update woocommerce_onboarding_profile '{"completed":true,"skipped":true,"setup_client":true,"store_location_set":true,"industry":"other","product_types":["physical"],"business_extensions":[],"revenue":"none","selling_venues":[],"setup_guide_completed":true,"tasks":["store_details","product_types","customize_store","add_products","set_up_payments","set_up_shipping","recommended","personalize_store","marketing","activate","store_details_complete"],"completed_tasks":["store_details","product_types","customize_store","add_products","set_up_payments","set_up_shipping","recommended","personalize_store","marketing","activate","store_details_complete"]}'

# Do not set woocommerce_task_list_hidden_lists directly; let WooCommerce handle it
wp option update woocommerce_task_list_tracked 1


# Run extra setup (test product, user, order)
if [ -f /var/www/html/wp-setup-extra.sh ]; then
  echo "[DEBUG] Running extra setup script..."
  bash /var/www/html/wp-setup-extra.sh
else
  echo "[DEBUG] No extra setup script found."
fi
