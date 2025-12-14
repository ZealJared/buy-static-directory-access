#!/bin/bash
# Script to build the course-access plugin as a zip file

PLUGIN_DIR="wp/wp-content/plugins/course-access"
OUTPUT_ZIP="course-access.zip"

# Remove old zip if exists
rm -f "$OUTPUT_ZIP"

# Create the zip, excluding any unwanted files (like .git, .DS_Store, etc.)
cd "$PLUGIN_DIR" || exit 1
zip -r "../../../../$OUTPUT_ZIP" . -x '*.git*' '*.DS_Store*'

cd - > /dev/null

echo "Plugin zipped as $OUTPUT_ZIP"
