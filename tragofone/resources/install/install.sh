#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FUSIONPBX_ROOT="${FUSIONPBX_ROOT:-/var/www/fusionpbx}"
TARGET="${FUSIONPBX_ROOT}/app/tragofone"

test -f "${FUSIONPBX_ROOT}/resources/config.php" || { echo "FusionPBX not found at ${FUSIONPBX_ROOT}" >&2; exit 1; }
install -d -o www-data -g www-data "${TARGET}"
# Copy only the native FusionPBX application, not the repository wrapper.
cp -a "${SOURCE_DIR}/." "${TARGET}/"
chown -R www-data:www-data "${TARGET}"
install -m 0644 "${TARGET}/resources/service/tragofone-worker.service" /etc/systemd/system/
install -m 0644 "${TARGET}/resources/service/tragofone-worker.timer" /etc/systemd/system/
install -m 0644 "${TARGET}/resources/service/tragofone-reconcile.service" /etc/systemd/system/
install -m 0644 "${TARGET}/resources/service/tragofone-reconcile.timer" /etc/systemd/system/
if [[ ! -f /etc/fusionpbx/tragofone.env ]]; then
	KEY="$(openssl rand -hex 32)"
	install -m 0600 -o root -g www-data /dev/null /etc/fusionpbx/tragofone.env
	echo "TRAGOFONE_ENCRYPTION_KEY=${KEY}" > /etc/fusionpbx/tragofone.env
fi
php "${FUSIONPBX_ROOT}/core/upgrade/upgrade.php"
systemctl daemon-reload
systemctl enable --now tragofone-worker.timer tragofone-reconcile.timer
echo "Installed. Open Advanced > Tragofone Integration."
