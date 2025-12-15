#!/bin/bash
set -e
set -x

echo "--- Starting wp-setup-extra.sh ---"

# --- WooCommerce Onboarding REST API Completion ---
ADMIN_USER=admin
ADMIN_PASS=password
SITE_URL=http://localhost



# --- Application Password for REST API Auth ---
APP_PW_FILE="admin-app-password.txt"
if [ ! -f "$APP_PW_FILE" ]; then
  echo "Generating application password for admin..."
  APP_PW=$(wp user application-password create $ADMIN_USER "wp-setup-extra" --porcelain 2>&1)
  STATUS=$?
  echo "$APP_PW" | grep -qE '^[a-zA-Z0-9]+' || { echo "Failed to generate application password. Output:"; echo "$APP_PW"; exit 1; }
  if [ $STATUS -ne 0 ]; then
    echo "wp user application-password create failed with status $STATUS"; exit $STATUS;
  fi
  echo "$APP_PW" > "$APP_PW_FILE"
else
  APP_PW=$(cat "$APP_PW_FILE")
fi

echo "Application password: $APP_PW"


# Get login cookie and nonce
echo "Getting login cookie and nonce..."
LOGIN_RESPONSE=$(curl -s -c cookies.txt -d "log=$ADMIN_USER&pwd=$ADMIN_PASS&wp-submit=Log+In&testcookie=1" "$SITE_URL/wp-login.php")
NONCE=$(curl -s -b cookies.txt "$SITE_URL/wp-admin/admin.php?page=wc-admin" | grep -o '"nonce":"[a-zA-Z0-9]*"' | head -n1 | cut -d '"' -f4)

if [ -z "$NONCE" ]; then
  echo "Primary nonce not found, trying alternate admin page..."
  NONCE=$(curl -s -b cookies.txt "$SITE_URL/wp-admin/" | grep -o '"nonce":"[a-zA-Z0-9]*"' | head -n1 | cut -d '"' -f4)
fi

if [ -z "$NONCE" ]; then
  echo "Nonce still not found. Dumping HTML for debugging:"
  curl -s -b cookies.txt "$SITE_URL/wp-admin/admin.php?page=wc-admin" | head -n 40
fi

# POST to onboarding completion endpoint if nonce was found
if [ -n "$NONCE" ]; then
  echo "Nonce found: $NONCE. Completing WooCommerce onboarding via REST API..."
  # Skip guided setup
  curl -s -b cookies.txt -H "Content-Type: application/json" -H "x-wp-nonce: $NONCE" -X POST \
    -d '{"step":"skip-guided-setup"}' \
    "$SITE_URL/wp-json/wc-admin/onboarding/profile/progress/core-profiler/complete?_locale=user"
  # Set onboarding profile as skipped
  curl -s -b cookies.txt -H "Content-Type: application/json" -H "x-wp-nonce: $NONCE" -X POST \
    -d '{"skipped":true}' \
    "$SITE_URL/wp-json/wc-admin/onboarding/profile?_locale=user"
  # Update store currency and measurement units
  curl -s -b cookies.txt -H "Content-Type: application/json" -H "x-wp-nonce: $NONCE" -X POST \
    -d '{"country_code":"US"}' \
    "$SITE_URL/wp-json/wc-admin/onboarding/profile/update-store-currency-and-measurement-units?_locale=user"
  # Set WooCommerce default country via analytics settings endpoint
  curl -s -b cookies.txt -H "Content-Type: application/json" -H "x-wp-nonce: $NONCE" -H "x-http-method-override: PUT" -X POST \
    -d '{"value":"US:OR"}' \
    "$SITE_URL/wp-json/wc-analytics/settings/general/woocommerce_default_country?_locale=user"
  # Initialize 'coming soon' state via launch-your-store endpoint
  # Disable 'coming soon' state using wp option update (more reliable than REST API)
  wp option update woocommerce_coming_soon no
  wp option update woocommerce_store_pages_only no
  wp option update woocommerce_private_link no
  echo "WooCommerce Coming Soon mode disabled. Store is live."
else
  echo "No nonce found. Skipping WooCommerce onboarding REST API steps."
fi
# --- End WooCommerce Onboarding REST API Completion ---


# Ensure WP-CLI RESTful package is installed for WooCommerce CLI support
echo "Checking for WP-CLI RESTful package..."
if ! wp package list | grep -q 'wp-cli/restful'; then
  echo "Installing WP-CLI RESTful package..."
  wp package install wp-cli/restful
else
  echo "WP-CLI RESTful package already installed."
fi



# Wait for WordPress to be ready
echo "Waiting for WordPress to be ready (extra setup)..."
until curl -s http://localhost/wp-login.php > /dev/null; do
  echo "...still waiting for WordPress..."
  sleep 3
done
echo "WordPress is ready."



# Robustly activate course-access plugin with retry and logging
PLUGIN="course-access"
MAX_RETRIES=10
RETRY_DELAY=3
RETRY_COUNT=0
echo "Ensuring $PLUGIN plugin is active..."
while ! wp plugin is-active $PLUGIN; do
  echo "Attempting to activate $PLUGIN plugin (try $((RETRY_COUNT+1))/$MAX_RETRIES)..."
  wp plugin activate $PLUGIN
  RETRY_COUNT=$((RETRY_COUNT+1))
  if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
    echo "Failed to activate $PLUGIN plugin after $MAX_RETRIES attempts."
    exit 1
  fi
  sleep $RETRY_DELAY
done
if wp plugin is-active $PLUGIN; then
  echo "$PLUGIN plugin is active."
else
  echo "$PLUGIN plugin is NOT active after retries."
  exit 1
fi


# Create test product if not exists
echo "Checking for test product..."
wp post list --post_type=product --name=test-course-product | grep test-course-product || \
wp post create --post_type=product --post_title='Test Course Product' --post_name='test-course-product' --post_status=publish



# Get product ID
PRODUCT_ID=$(wp post list --post_type=product --name=test-course-product --field=ID --format=ids)
echo "Test product ID: $PRODUCT_ID"

# Ensure product is virtual (not downloadable) and WooCommerce recognizes it

echo "Setting product $PRODUCT_ID as virtual (not downloadable) and type simple..."
wp post meta update $PRODUCT_ID _virtual yes
wp post meta update $PRODUCT_ID _product_type simple
wp post meta update $PRODUCT_ID _sku test-course-product
wp post meta update $PRODUCT_ID _regular_price 10.00
wp post meta update $PRODUCT_ID _price 10.00
wp post term set $PRODUCT_ID product_type simple

# Clear WooCommerce product cache and transients
echo "Clearing WooCommerce cache and transients..."
wp transient delete --all
wp cache flush


# Create non-admin user if not exists, then get user ID
echo "Checking for testuser..."
if ! wp user get testuser > /dev/null 2>&1; then
  echo "Creating testuser..."
  wp user create testuser testuser@example.com --user_pass=TestPass123 --role=customer
fi
USER_ID=$(wp user get testuser --field=ID)
echo "testuser ID: $USER_ID"


# Flush permalinks to ensure REST API endpoints are available
echo "Flushing permalinks..."
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard


# Create no-access user if not exists
echo "Checking for noaccessuser..."
if ! wp user get noaccessuser > /dev/null 2>&1; then
  echo "Creating noaccessuser..."
  wp user create noaccessuser noaccess@example.com --user_pass=NoAccess123 --role=customer
fi


# Automate Course Creation
COURSE_SLUG="test-course-product-directory-access"
PRODUCT_SLUG="test-course-product"
echo "Checking for course '$COURSE_SLUG'..."

# Get Product ID for linking
PRODUCT_ID=$(wp post list --post_type=product --name="$PRODUCT_SLUG" --field=ID --format=ids)

    #     echo "Course '$COURSE_SLUG' already exists."
    # fi
# fi


# Ensure WooCommerce CLI is available before running wc commands

if wp plugin is-active woocommerce && wp wc --help > /dev/null 2>&1; then

  echo "WooCommerce CLI available. Deleting all previous test orders (shop_order and shop_order_placehold, any status)..."
  # Delete all shop_order posts (any status)
  ALL_SHOP_ORDER_IDS=$(wp wc shop_order list --user=admin --field=id) # One per line
  if [ -n "$ALL_SHOP_ORDER_IDS" ]; then
    for ID in $ALL_SHOP_ORDER_IDS; do
      echo "Deleting shop_order ID: $ID"
      if wp wc shop_order delete $ID --user=admin; then
        continue
      else
        echo "Warning: Failed deleting shop_order $ID as testuser."
      fi
    done
  else
    echo "No shop_order posts to delete."
  fi
  # Clear WooCommerce and WordPress caches again
  echo "Clearing WooCommerce cache and transients (post deletion)..."
  wp transient delete --all
  wp cache flush

  echo "Creating new WooCommerce test order for testuser with product..."
  # Create order via WooCommerce CLI
  ORDER_OUTPUT=$(wp wc shop_order create --user=admin --status=completed --customer_id=$USER_ID)
  echo "$ORDER_OUTPUT"
  ORDER_ID=$(echo "$ORDER_OUTPUT" | grep -Eo 'shop_order [0-9]+' | awk '{print $2}')
  echo "Created order ID: $ORDER_ID"

  # Add product line item using WooCommerce CLI
  echo "Adding product $PRODUCT_ID to order $ORDER_ID..."
  wp wc shop_order update $ORDER_ID --user=admin --line_items='[{"product_id":'$PRODUCT_ID',"quantity":1}]'

  # Optionally set billing and shipping meta (minimal for visibility)
  wp post meta update $ORDER_ID _billing_first_name Test
  wp post meta update $ORDER_ID _billing_last_name User
  wp post meta update $ORDER_ID _billing_email testuser@example.com
  wp post meta update $ORDER_ID _billing_address_1 '123 Test St'
  wp post meta update $ORDER_ID _billing_city Testville
  wp post meta update $ORDER_ID _billing_postcode 12345
  wp post meta update $ORDER_ID _billing_country US
  wp post meta update $ORDER_ID _shipping_first_name Test
  wp post meta update $ORDER_ID _shipping_last_name User
  wp post meta update $ORDER_ID _shipping_address_1 '123 Test St'
  wp post meta update $ORDER_ID _shipping_city Testville
  wp post meta update $ORDER_ID _shipping_postcode 12345
  wp post meta update $ORDER_ID _shipping_country US
  echo "Test WooCommerce order created and populated with product."
else
  echo "WooCommerce CLI not available or WooCommerce not active. Skipping test order creation."
fi

echo "--- wp-setup-extra.sh complete ---"
