#!/bin/bash
set -e



# Wait for wp-config.php to exist, then inject debug-config.php if not already present
echo "[WRAPPER-DEBUG] custom-entrypoint.sh started."
if [ -f /var/www/html/wp-content/debug-config.php ]; then
	echo "[WRAPPER-DEBUG] debug-config.php found. Waiting for wp-config.php to exist..."
	for i in {1..30}; do
		if [ -f /var/www/html/wp-config.php ]; then
			echo "[WRAPPER-DEBUG] wp-config.php found. Checking for debug-config.php inclusion..."
			if ! grep -q "debug-config.php" /var/www/html/wp-config.php; then
				echo "[WRAPPER-DEBUG] Adding debug-config.php to wp-config.php..."
				echo -e "\n// Include debug config\nif (file_exists(__DIR__ . '/wp-content/debug-config.php')) { require_once __DIR__ . '/wp-content/debug-config.php'; }" >> /var/www/html/wp-config.php 2>&1
				if [ $? -eq 0 ]; then
					echo "[WRAPPER-DEBUG] Successfully appended debug-config.php inclusion."
				else
					echo "[WRAPPER-DEBUG] Failed to append debug-config.php inclusion! Check permissions."
				fi
			else
				echo "[WRAPPER-DEBUG] debug-config.php already included in wp-config.php."
			fi
			break
		else
			echo "[WRAPPER-DEBUG] Waiting for wp-config.php... ($i/30)"
			sleep 1
		fi
	done
	if [ ! -f /var/www/html/wp-config.php ]; then
		echo "[WRAPPER-DEBUG] wp-config.php not found after waiting, skipping debug-config.php injection."
	fi
else
	echo "[WRAPPER-DEBUG] debug-config.php not found, skipping injection."
fi

# Start post-setup script in background (waits for Apache)
echo "[WRAPPER] Starting post-start-setup.sh in background..."
/wp/post-start-setup.sh &

# Hand off to the official WordPress entrypoint and start Apache
exec /usr/local/bin/docker-entrypoint.sh apache2-foreground
