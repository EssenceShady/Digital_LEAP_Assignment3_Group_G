#!/usr/bin/env bash
# First-start setup for the Codespaces stack. Runs once per container create
# or rebuild, as root, from the repository root.
#
# 1. Let root (the remote user) run git in the workspace owned by the
#    codespace user.
# 2. Give Apache ownership of storage/, which lives in a named volume.
# 3. Wait until MariaDB has finished importing database/digital_leap.sql, so
#    the first page load does not fail with "table not found".

set -u

cd "$(dirname "$0")/.." || exit 1

git config --global --add safe.directory "$PWD" 2>/dev/null || true

chown -R www-data:www-data digital-leap/storage 2>/dev/null || true

if ! php -r '
$deadline = time() + 180;
do {
    try {
        $pdo = new PDO(
            "mysql:host=db;port=3306;dbname=digital_leap;charset=utf8mb4",
            "root",
            "leap-demo",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
        exit(0);
    } catch (Throwable $e) {
        sleep(2);
    }
} while (time() < $deadline);
exit(1);
'; then
    echo "MariaDB did not finish importing the seed within 180 seconds. Reload the app in a minute; if it still fails, rebuild the container." >&2
fi

exit 0
