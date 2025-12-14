#!/bin/bash
set -e

# Zip the sample course
bash zip-sample-course.sh

echo "[+] Sample course zipped as sample-course.zip"


# Build and start Docker containers
docker compose down -v
docker compose up -d --build

echo "[+] Docker containers started. Waiting for WordPress to be ready..."

# Wait for WordPress to be ready
until curl -s http://localhost:8080/wp-login.php > /dev/null; do
  sleep 2
done


# Run post-start-setup.sh in the WordPress container
echo "[+] Running WordPress post-start setup..."
docker compose exec wordpress bash /wp/post-start-setup.sh

echo "[+] WordPress is ready. Opening browser..."
echo "Open http://localhost:8080 in your browser."
