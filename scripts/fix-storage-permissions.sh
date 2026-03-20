#!/bin/sh
# Run once so Apache (www-data) can write uploads. Adjust path if needed.
DIR="$(cd "$(dirname "$0")/.." && pwd)"
echo "Fixing permissions on $DIR/storage ..."
sudo chown -R www-data:www-data "$DIR/storage" 2>/dev/null || chown -R www-data:www-data "$DIR/storage"
sudo chmod -R ug+rwX "$DIR/storage" 2>/dev/null || chmod -R 775 "$DIR/storage"
echo "Done. If uploads still fail, try: chmod -R 777 $DIR/storage"
