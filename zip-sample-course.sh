
#!/bin/bash

# Check if zip is installed, install if missing (Ubuntu)
if ! command -v zip &> /dev/null; then
	echo "zip could not be found, installing..."
	sudo apt-get update && sudo apt-get install -y zip
fi

cd "$(dirname "$0")/sample-course"
zip -r ../sample-course.zip .
