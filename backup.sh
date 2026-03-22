#!/usr/bin/env bash
# backup.sh - Simple Linux backup script for TaskFlow
# Usage: ./backup.sh /path/to/output

set -euo pipefail

# === Configuration (edit as needed) ===
DB_HOST="localhost"
DB_NAME="taskflow_db"
DB_USER="root"
DB_PASS=""

# Where to store the backup (default: current directory)
OUTPUT_DIR="${1:-$(pwd)}"

# Filenames
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
SQL_FILE="taskflow_backup_${TIMESTAMP}.sql"
ZIP_FILE="taskflow_backup_full_${TIMESTAMP}.zip"

# Paths from project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UPLOADS_DIR="$PROJECT_ROOT/uploads"
CONFIG_FILES=("$PROJECT_ROOT/config.php" "$PROJECT_ROOT/database.sql" "$PROJECT_ROOT/firebase-applet-config.json")

mkdir -p "$OUTPUT_DIR"

echo "[TaskFlow Backup] Starting backup to $OUTPUT_DIR"

echo "- Exporting database to $SQL_FILE"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$OUTPUT_DIR/$SQL_FILE"

# Build ZIP
ZIP_PATH="$OUTPUT_DIR/$ZIP_FILE"

echo "- Creating archive $ZIP_PATH"

# Use zip if available; otherwise fallback to tar.gz
if command -v zip >/dev/null 2>&1; then
  # Include SQL dump
  zip -r "$ZIP_PATH" "$OUTPUT_DIR/$SQL_FILE" >/dev/null

  # Include uploads folder if exists
  if [ -d "$UPLOADS_DIR" ]; then
    zip -r "$ZIP_PATH" "$UPLOADS_DIR" >/dev/null
  fi

  # Include config files
  for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
      zip -r "$ZIP_PATH" "$file" >/dev/null
    fi
  done

  echo "- Backup created: $ZIP_PATH"
else
  echo "- zip not found, using tar.gz fallback"
  TAR_PATH="$OUTPUT_DIR/taskflow_backup_full_${TIMESTAMP}.tar.gz"
  tar -czf "$TAR_PATH" -C "$OUTPUT_DIR" "$(basename "$SQL_FILE")"
  if [ -d "$UPLOADS_DIR" ]; then
    tar -rzf "$TAR_PATH" -C "$PROJECT_ROOT" "uploads"
  fi
  for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
      tar -rzf "$TAR_PATH" -C "$PROJECT_ROOT" "$(basename "$file")"
    fi
  done
  echo "- Backup created: $TAR_PATH"
fi

echo "Done."
