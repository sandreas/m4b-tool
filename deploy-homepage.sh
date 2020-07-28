#!/bin/sh
SYNC_DIR="homepage"
FTP_HOST="$(grep 'host' .credentials | cut -d '=' -f 2)"
FTP_USERNAME="$(grep 'username' .credentials | cut -d '=' -f 2)"
FTP_OBSCURED_PASSWORD="$(grep 'password' .credentials | cut -d '=' -f 2)"

php -f "tools/build-homepage.php" && \
rclone sync "$SYNC_DIR" :ftp: \
    --ftp-concurrency=10 \
    --ftp-host="$FTP_HOST" \
    --ftp-user="$FTP_USERNAME" \
    --ftp-pass="$FTP_OBSCURED_PASSWORD"
