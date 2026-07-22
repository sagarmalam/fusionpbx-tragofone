#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FUSIONPBX_ROOT="${FUSIONPBX_ROOT:-/var/www/fusionpbx}"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
FUSIONPBX_USER="${FUSIONPBX_USER:-www-data}"
FUSIONPBX_GROUP="${FUSIONPBX_GROUP:-www-data}"
TARGET="${FUSIONPBX_ROOT}/app/tragofone"

[[ ${EUID} -eq 0 ]] || { echo "Run this installer as root (or with sudo)." >&2; exit 1; }
for command in install cp chown chmod getent openssl systemctl sed; do
	command -v "${command}" >/dev/null 2>&1 || { echo "Required command not found: ${command}" >&2; exit 1; }
done
[[ -n "${PHP_BIN}" && -x "${PHP_BIN}" ]] || { echo "PHP CLI was not found. Set PHP_BIN to its absolute path." >&2; exit 1; }
[[ "${PHP_BIN}" = /* && "${FUSIONPBX_ROOT}" = /* ]] || { echo "PHP_BIN and FUSIONPBX_ROOT must be absolute paths." >&2; exit 1; }
[[ "${PHP_BIN}" =~ ^/[A-Za-z0-9._/-]+$ && "${FUSIONPBX_ROOT}" =~ ^/[A-Za-z0-9._/-]+$ ]] || { echo "PHP_BIN and FUSIONPBX_ROOT contain unsupported path characters." >&2; exit 1; }
[[ "${FUSIONPBX_USER}" =~ ^[A-Za-z0-9._-]+$ && "${FUSIONPBX_GROUP}" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Runtime user or group contains unsupported characters." >&2; exit 1; }
id "${FUSIONPBX_USER}" >/dev/null 2>&1 || { echo "Runtime user not found: ${FUSIONPBX_USER}" >&2; exit 1; }
getent group "${FUSIONPBX_GROUP}" >/dev/null 2>&1 || { echo "Runtime group not found: ${FUSIONPBX_GROUP}" >&2; exit 1; }
test -f "${FUSIONPBX_ROOT}/resources/require.php" || { echo "FusionPBX runtime bootstrap not found at ${FUSIONPBX_ROOT}" >&2; exit 1; }
test -f "${FUSIONPBX_ROOT}/core/upgrade/upgrade.php" || { echo "FusionPBX upgrade tool not found under ${FUSIONPBX_ROOT}" >&2; exit 1; }
"${PHP_BIN}" -r '
	if (PHP_VERSION_ID < 80100) {
		fwrite(STDERR, "Tragofone requires PHP 8.1 or newer; found ".PHP_VERSION.".\n");
		exit(1);
	}
	$missing = array_values(array_filter(["curl", "json", "mbstring", "PDO", "pdo_pgsql", "sodium"], static fn ($extension) => !extension_loaded($extension)));
	if ($missing !== []) {
		fwrite(STDERR, "Missing required PHP extension(s): ".implode(", ", $missing).".\n");
		exit(1);
	}
'

install -d -o "${FUSIONPBX_USER}" -g "${FUSIONPBX_GROUP}" "${TARGET}"
# Copy only the native FusionPBX application, not the repository wrapper.
cp -a "${SOURCE_DIR}/." "${TARGET}/"
if [[ -f "$(dirname "${SOURCE_DIR}")/VERSION" ]]; then
	install -m 0644 "$(dirname "${SOURCE_DIR}")/VERSION" "${TARGET}/VERSION"
fi
chown -R "${FUSIONPBX_USER}:${FUSIONPBX_GROUP}" "${TARGET}"
install -m 0644 "${TARGET}/resources/service/tragofone-worker.service" /etc/systemd/system/
install -m 0644 "${TARGET}/resources/service/tragofone-worker.timer" /etc/systemd/system/
install -m 0644 "${TARGET}/resources/service/tragofone-reconcile.service" /etc/systemd/system/
install -m 0644 "${TARGET}/resources/service/tragofone-reconcile.timer" /etc/systemd/system/
for service in /etc/systemd/system/tragofone-worker.service /etc/systemd/system/tragofone-reconcile.service; do
	sed -i \
		-e "s|^User=www-data$|User=${FUSIONPBX_USER}|" \
		-e "s|^Group=www-data$|Group=${FUSIONPBX_GROUP}|" \
		-e "s|/usr/bin/php|${PHP_BIN}|g" \
		-e "s|/var/www/fusionpbx|${FUSIONPBX_ROOT}|g" \
		"${service}"
done
if [[ ! -d /etc/fusionpbx ]]; then
	install -d -m 0755 -o root -g root /etc/fusionpbx
fi
if [[ ! -f /etc/fusionpbx/tragofone.env ]]; then
	KEY="$(openssl rand -hex 32)"
	install -m 0640 -o root -g "${FUSIONPBX_GROUP}" /dev/null /etc/fusionpbx/tragofone.env
	echo "TRAGOFONE_ENCRYPTION_KEY=${KEY}" > /etc/fusionpbx/tragofone.env
fi
chown "root:${FUSIONPBX_GROUP}" /etc/fusionpbx/tragofone.env
chmod 0640 /etc/fusionpbx/tragofone.env
"${PHP_BIN}" "${FUSIONPBX_ROOT}/core/upgrade/upgrade.php"
"${PHP_BIN}" "${FUSIONPBX_ROOT}/core/upgrade/upgrade.php" -g
systemctl daemon-reload
systemctl enable --now tragofone-worker.timer tragofone-reconcile.timer
echo "Installed with PHP ${PHP_BIN}, FusionPBX root ${FUSIONPBX_ROOT}, and runtime ${FUSIONPBX_USER}:${FUSIONPBX_GROUP}."
echo "Open Advanced > Tragofone Integration."
