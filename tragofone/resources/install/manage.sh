#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_APP="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SCRIPT_PATH="${SCRIPT_DIR}/$(basename "${BASH_SOURCE[0]}")"
INSTALLER="${SCRIPT_DIR}/install.sh"

FUSIONPBX_ROOT="${FUSIONPBX_ROOT:-/var/www/fusionpbx}"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
FUSIONPBX_USER="${FUSIONPBX_USER:-www-data}"
FUSIONPBX_GROUP="${FUSIONPBX_GROUP:-www-data}"
BACKUP_ROOT="${TRAGOFONE_BACKUP_ROOT:-/var/backups/fusionpbx-tragofone}"
DATABASE_NAME="${TRAGOFONE_DATABASE_NAME:-fusionpbx}"
DATABASE_OS_USER="${TRAGOFONE_DATABASE_OS_USER:-postgres}"
LOG_LINES="${TRAGOFONE_LOG_LINES:-50}"

COMMAND="${1:-help}"
if [[ $# -gt 0 ]]; then
	shift
fi
COMMAND_ARGUMENT=""
NO_BACKUP=0
SKIP_DATABASE_BACKUP=0
ALLOW_PROCESSING=0

usage() {
	cat <<'EOF'
Usage: manage.sh <command> [options]

Commands:
  install       Install from this source tree; back up an existing installation.
  upgrade       Upgrade an existing installation from this source tree.
  repair        Reapply files, permissions, schema defaults, and systemd units.
  backup        Back up plugin files, protected configuration, units, and tables.
  status        Show installed/source versions and service state (read-only).
  doctor        Validate PHP, FusionPBX, permissions, services, and active jobs.
  worker        Run one worker pass and show its result.
  reconcile     Run one full reconciliation pass and show its result.
  timers        Enable and start both maintenance timers.
  logs [name]   Show worker, reconcile, or all logs (default: all).
  help          Show this help.

Options:
  --fusionpbx-root PATH       FusionPBX root (default: /var/www/fusionpbx).
  --php-bin PATH              PHP CLI binary (default: command -v php).
  --user NAME                 FusionPBX runtime user (default: www-data).
  --group NAME                FusionPBX runtime group (default: www-data).
  --backup-dir PATH           Backup root (default: /var/backups/fusionpbx-tragofone).
  --database-name NAME        PostgreSQL database name (default: fusionpbx).
  --database-os-user NAME     Local PostgreSQL OS user (default: postgres).
  --lines NUMBER              Journal lines for logs/service runs (default: 50).
  --no-backup                 Skip the automatic upgrade/repair backup.
  --skip-database-backup      Back up files only; database backup was handled separately.
  --allow-processing          Continue even if a sync job is processing.
  -h, --help                  Show this help.

Examples:
  sudo ./tragofone/resources/install/manage.sh install
  sudo ./tragofone/resources/install/manage.sh upgrade
  sudo /var/www/fusionpbx/app/tragofone/resources/install/manage.sh doctor
  sudo /var/www/fusionpbx/app/tragofone/resources/install/manage.sh logs worker
EOF
}

die() {
	printf 'Error: %s\n' "$*" >&2
	exit 1
}

note() {
	printf '%s\n' "$*"
}

warn() {
	printf 'Warning: %s\n' "$*" >&2
}

require_option_value() {
	[[ $# -ge 2 && -n "${2:-}" ]] || die "Option $1 requires a value."
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		--fusionpbx-root)
			require_option_value "$@"
			FUSIONPBX_ROOT="$2"
			shift 2
			;;
		--php-bin)
			require_option_value "$@"
			PHP_BIN="$2"
			shift 2
			;;
		--user)
			require_option_value "$@"
			FUSIONPBX_USER="$2"
			shift 2
			;;
		--group)
			require_option_value "$@"
			FUSIONPBX_GROUP="$2"
			shift 2
			;;
		--backup-dir)
			require_option_value "$@"
			BACKUP_ROOT="$2"
			shift 2
			;;
		--database-name)
			require_option_value "$@"
			DATABASE_NAME="$2"
			shift 2
			;;
		--database-os-user)
			require_option_value "$@"
			DATABASE_OS_USER="$2"
			shift 2
			;;
		--lines)
			require_option_value "$@"
			LOG_LINES="$2"
			shift 2
			;;
		--no-backup)
			NO_BACKUP=1
			shift
			;;
		--skip-database-backup)
			SKIP_DATABASE_BACKUP=1
			shift
			;;
		--allow-processing)
			ALLOW_PROCESSING=1
			shift
			;;
		-h|--help)
			COMMAND="help"
			shift
			;;
		-*)
			die "Unknown option: $1"
			;;
		*)
			if [[ "${COMMAND}" == "logs" && -z "${COMMAND_ARGUMENT}" ]]; then
				COMMAND_ARGUMENT="$1"
				shift
			else
				die "Unexpected argument: $1"
			fi
			;;
	esac
done

case "${COMMAND}" in
	-h|--help)
		COMMAND="help"
		;;
esac

if [[ "${COMMAND}" == "help" ]]; then
	usage
	exit 0
fi

[[ "${FUSIONPBX_ROOT}" = /* && "${FUSIONPBX_ROOT}" != "/" ]] || die "FUSIONPBX_ROOT must be a non-root absolute path."
[[ "${BACKUP_ROOT}" = /* && "${BACKUP_ROOT}" != "/" ]] || die "The backup directory must be a non-root absolute path."
[[ -z "${PHP_BIN}" || "${PHP_BIN}" = /* ]] || die "PHP_BIN must be an absolute path."
[[ "${FUSIONPBX_ROOT}" =~ ^/[A-Za-z0-9._/-]+$ && "${BACKUP_ROOT}" =~ ^/[A-Za-z0-9._/-]+$ ]] || die "A configured path contains unsupported characters."
[[ -z "${PHP_BIN}" || "${PHP_BIN}" =~ ^/[A-Za-z0-9._/-]+$ ]] || die "PHP_BIN contains unsupported characters."
[[ "${FUSIONPBX_USER}" =~ ^[A-Za-z0-9_][A-Za-z0-9._-]*$ && "${FUSIONPBX_GROUP}" =~ ^[A-Za-z0-9_][A-Za-z0-9._-]*$ ]] || die "Runtime user or group contains unsupported characters."
[[ "${DATABASE_NAME}" =~ ^[A-Za-z0-9_][A-Za-z0-9._-]*$ && "${DATABASE_OS_USER}" =~ ^[A-Za-z0-9_][A-Za-z0-9._-]*$ ]] || die "Database name or OS user contains unsupported characters."
[[ "${LOG_LINES}" =~ ^[1-9][0-9]*$ ]] || die "Log line count must be a positive integer."

TARGET="${FUSIONPBX_ROOT}/app/tragofone"
[[ "${BACKUP_ROOT}/" != "${TARGET}/"* ]] || die "The backup directory cannot be inside the installed application."
CONFIG_FILE="/etc/fusionpbx/tragofone.env"
SYSTEMD_DIR="/etc/systemd/system"
WORKER_TIMER="tragofone-worker.timer"
RECONCILE_TIMER="tragofone-reconcile.timer"
WORKER_SERVICE="tragofone-worker.service"
RECONCILE_SERVICE="tragofone-reconcile.service"

source_version() {
	if [[ -f "$(dirname "${SOURCE_APP}")/VERSION" ]]; then
		tr -d '\r\n' < "$(dirname "${SOURCE_APP}")/VERSION"
	elif [[ -f "${SOURCE_APP}/VERSION" ]]; then
		tr -d '\r\n' < "${SOURCE_APP}/VERSION"
	else
		printf 'unknown'
	fi
}

installed_version() {
	if [[ -f "${TARGET}/VERSION" ]]; then
		tr -d '\r\n' < "${TARGET}/VERSION"
	else
		printf 'not-installed'
	fi
}

command_exists() {
	command -v "$1" >/dev/null 2>&1
}

require_root() {
	if [[ ${EUID} -eq 0 ]]; then
		return
	fi
	command_exists sudo || die "This command requires root and sudo was not found."
	local args=(
		"${COMMAND}"
		"--fusionpbx-root" "${FUSIONPBX_ROOT}"
		"--user" "${FUSIONPBX_USER}"
		"--group" "${FUSIONPBX_GROUP}"
		"--backup-dir" "${BACKUP_ROOT}"
		"--database-name" "${DATABASE_NAME}"
		"--database-os-user" "${DATABASE_OS_USER}"
		"--lines" "${LOG_LINES}"
	)
	[[ -n "${PHP_BIN}" ]] && args+=("--php-bin" "${PHP_BIN}")
	[[ ${NO_BACKUP} -eq 1 ]] && args+=("--no-backup")
	[[ ${SKIP_DATABASE_BACKUP} -eq 1 ]] && args+=("--skip-database-backup")
	[[ ${ALLOW_PROCESSING} -eq 1 ]] && args+=("--allow-processing")
	[[ -n "${COMMAND_ARGUMENT}" ]] && args+=("${COMMAND_ARGUMENT}")
	exec sudo -- "${SCRIPT_PATH}" "${args[@]}"
}

service_state() {
	local unit="$1"
	local enabled="unavailable"
	local active="unavailable"
	if command_exists systemctl; then
		enabled="$(systemctl is-enabled "${unit}" 2>/dev/null || true)"
		active="$(systemctl is-active "${unit}" 2>/dev/null || true)"
	fi
	printf '%-30s enabled=%-10s active=%s\n' "${unit}" "${enabled:-unknown}" "${active:-unknown}"
}

show_status() {
	note "Tragofone plugin status"
	printf '%-30s %s\n' "FusionPBX root" "${FUSIONPBX_ROOT}"
	printf '%-30s %s\n' "Source version" "$(source_version)"
	printf '%-30s %s\n' "Installed version" "$(installed_version)"
	if [[ -n "${PHP_BIN}" && -x "${PHP_BIN}" ]]; then
		printf '%-30s %s (%s)\n' "PHP CLI" "${PHP_BIN}" "$("${PHP_BIN}" -r 'echo PHP_VERSION;' 2>/dev/null || printf 'unavailable')"
	else
		printf '%-30s %s\n' "PHP CLI" "not found"
	fi
	if [[ -f "${CONFIG_FILE}" ]]; then
		if command_exists stat; then
			printf '%-30s %s mode=%s\n' "Protected configuration" "$(stat -c '%U:%G' "${CONFIG_FILE}" 2>/dev/null || printf 'unreadable')" "$(stat -c '%a' "${CONFIG_FILE}" 2>/dev/null || printf 'unknown')"
		else
			printf '%-30s %s\n' "Protected configuration" "present"
		fi
	else
		printf '%-30s %s\n' "Protected configuration" "missing"
	fi
	service_state "${WORKER_TIMER}"
	service_state "${RECONCILE_TIMER}"
}

FAILURES=0

check_ok() {
	printf '[OK]   %s\n' "$*"
}

check_fail() {
	printf '[FAIL] %s\n' "$*" >&2
	FAILURES=$((FAILURES + 1))
}

check_file() {
	local path="$1"
	local description="$2"
	if [[ -f "${path}" ]]; then
		check_ok "${description}"
	else
		check_fail "${description}: missing ${path}"
	fi
}

processing_jobs() {
	FUSIONPBX_ROOT_ENV="${FUSIONPBX_ROOT}" "${PHP_BIN}" -r '
		$root = getenv("FUSIONPBX_ROOT_ENV");
		try {
			require $root."/resources/require.php";
			$database = new database();
			$rows = $database->select("select count(*) as total from v_tragofone_sync_jobs where status = '\''processing'\''", null, "all") ?: [];
			echo (int) ($rows[0]["total"] ?? 0);
		} catch (Throwable $exception) {
			exit(2);
		}
	'
}

run_doctor() {
	require_root
	FAILURES=0
	for required in systemctl stat id getent grep; do
		if command_exists "${required}"; then
			check_ok "Command available: ${required}"
		else
			check_fail "Required command missing: ${required}"
		fi
	done
	if [[ -n "${PHP_BIN}" && -x "${PHP_BIN}" ]]; then
		if "${PHP_BIN}" -r '
			if (PHP_VERSION_ID < 80100) { exit(1); }
			foreach (["curl", "fileinfo", "gd", "json", "mbstring", "PDO", "pdo_pgsql", "sodium"] as $extension) {
				if (!extension_loaded($extension)) { exit(1); }
			}
		' >/dev/null 2>&1; then
			check_ok "PHP 8.1+ and required extensions"
		else
			check_fail "PHP version or required extensions are invalid"
		fi
	else
		check_fail "PHP CLI is missing or not executable: ${PHP_BIN:-not configured}"
	fi
	if id "${FUSIONPBX_USER}" >/dev/null 2>&1; then
		check_ok "Runtime user exists: ${FUSIONPBX_USER}"
	else
		check_fail "Runtime user does not exist: ${FUSIONPBX_USER}"
	fi
	if getent group "${FUSIONPBX_GROUP}" >/dev/null 2>&1; then
		check_ok "Runtime group exists: ${FUSIONPBX_GROUP}"
	else
		check_fail "Runtime group does not exist: ${FUSIONPBX_GROUP}"
	fi
	check_file "${FUSIONPBX_ROOT}/resources/require.php" "FusionPBX runtime bootstrap"
	check_file "${FUSIONPBX_ROOT}/core/upgrade/upgrade.php" "FusionPBX upgrade tool"
	check_file "${FUSIONPBX_ROOT}/resources/qr_code/QRCode.php" "FusionPBX QR rendering library"
	check_file "${TARGET}/app_config.php" "Tragofone application"
	check_file "${TARGET}/VERSION" "Installed Tragofone version metadata"
	check_file "${CONFIG_FILE}" "Protected Tragofone configuration"
	for unit in "${WORKER_SERVICE}" "${WORKER_TIMER}" "${RECONCILE_SERVICE}" "${RECONCILE_TIMER}"; do
		check_file "${SYSTEMD_DIR}/${unit}" "systemd unit ${unit}"
	done
	if [[ -f "${CONFIG_FILE}" ]] && command_exists stat; then
		local owner_group
		local mode
		owner_group="$(stat -c '%U:%G' "${CONFIG_FILE}" 2>/dev/null || true)"
		mode="$(stat -c '%a' "${CONFIG_FILE}" 2>/dev/null || true)"
		if [[ "${owner_group}" == "root:${FUSIONPBX_GROUP}" && "${mode}" == "640" ]]; then
			check_ok "Protected configuration ownership and mode"
		else
			check_fail "Protected configuration must be root:${FUSIONPBX_GROUP} mode 640; found ${owner_group:-unknown} mode ${mode:-unknown}"
		fi
	fi
	if [[ -f "${CONFIG_FILE}" ]]; then
		local key_count
		key_count="$(grep -Ec '^TRAGOFONE_ENCRYPTION_KEY=[0-9a-fA-F]{64}$' "${CONFIG_FILE}" || true)"
		if [[ "${key_count}" == "1" ]]; then
			check_ok "Protected configuration contains a valid encryption key"
		else
			check_fail "Protected configuration does not contain exactly one valid 256-bit encryption key"
		fi
	fi
	if [[ -d "${TARGET}" ]] && command_exists stat; then
		local target_owner
		target_owner="$(stat -c '%U:%G' "${TARGET}" 2>/dev/null || true)"
		if [[ "${target_owner}" == "${FUSIONPBX_USER}:${FUSIONPBX_GROUP}" ]]; then
			check_ok "Installed application ownership"
		else
			check_fail "Installed application must be owned by ${FUSIONPBX_USER}:${FUSIONPBX_GROUP}; found ${target_owner:-unknown}"
		fi
	fi
	for service in "${WORKER_SERVICE}" "${RECONCILE_SERVICE}"; do
		if [[ -f "${SYSTEMD_DIR}/${service}" ]]; then
			if grep -Fqx "User=${FUSIONPBX_USER}" "${SYSTEMD_DIR}/${service}" \
				&& grep -Fqx "Group=${FUSIONPBX_GROUP}" "${SYSTEMD_DIR}/${service}" \
				&& grep -Fq "${PHP_BIN}" "${SYSTEMD_DIR}/${service}" \
				&& grep -Fq "${FUSIONPBX_ROOT}" "${SYSTEMD_DIR}/${service}"; then
				check_ok "Runtime settings in ${service}"
			else
				check_fail "Runtime settings in ${service} do not match the requested PHP/root/user/group"
			fi
		fi
	done
	if command_exists systemctl; then
		for timer in "${WORKER_TIMER}" "${RECONCILE_TIMER}"; do
			if systemctl is-enabled --quiet "${timer}" && systemctl is-active --quiet "${timer}"; then
				check_ok "Timer enabled and active: ${timer}"
			else
				check_fail "Timer is not enabled and active: ${timer}"
			fi
		done
	fi
	if [[ -n "${PHP_BIN}" && -x "${PHP_BIN}" && -f "${TARGET}/app_config.php" ]]; then
		local count
		if count="$(processing_jobs 2>/dev/null)"; then
			if [[ "${count}" == "0" ]]; then
				check_ok "No synchronization jobs are stuck processing"
			else
				check_fail "Synchronization jobs currently processing: ${count}"
			fi
		else
			check_fail "Unable to query synchronization job state"
		fi
	fi
	if [[ ${FAILURES} -gt 0 ]]; then
		printf '%s\n' "Doctor found ${FAILURES} problem(s)." >&2
		return 1
	fi
	note "Doctor completed successfully."
}

backup_installation() {
	require_root
	for required in install tar sha256sum date; do
		command_exists "${required}" || die "Required backup command not found: ${required}"
	done
	local items=()
	[[ -d "${TARGET}" ]] && items+=("${TARGET#/}")
	[[ -f "${CONFIG_FILE}" ]] && items+=("${CONFIG_FILE#/}")
	for unit in "${WORKER_SERVICE}" "${WORKER_TIMER}" "${RECONCILE_SERVICE}" "${RECONCILE_TIMER}"; do
		[[ -f "${SYSTEMD_DIR}/${unit}" ]] && items+=("${SYSTEMD_DIR#/}/${unit}")
	done
	[[ ${#items[@]} -gt 0 ]] || die "No installed Tragofone files were found to back up."
	install -d -m 0700 -o root -g root "${BACKUP_ROOT}"
	local stamp
	local backup_dir
	local archive
	stamp="$(date -u +%Y%m%dT%H%M%SZ)"
	backup_dir="${BACKUP_ROOT}/${stamp}"
	install -d -m 0700 -o root -g root "${backup_dir}"
	archive="${backup_dir}/files.tar.gz"
	tar -czf "${archive}" -C / "${items[@]}"
	chmod 0600 "${archive}"
	printf 'created_at=%s\ninstalled_version=%s\nsource_version=%s\n' "${stamp}" "$(installed_version)" "$(source_version)" > "${backup_dir}/manifest.txt"
	chmod 0600 "${backup_dir}/manifest.txt"
	local checksum_files=("files.tar.gz" "manifest.txt")
	if [[ ${SKIP_DATABASE_BACKUP} -eq 0 ]]; then
		command_exists pg_dump || die "pg_dump is required for the database backup; install it or use --skip-database-backup after taking an approved database backup."
		command_exists runuser || die "runuser is required for the database backup; use --skip-database-backup only after taking an approved database backup."
		id "${DATABASE_OS_USER}" >/dev/null 2>&1 || die "Database OS user not found: ${DATABASE_OS_USER}"
		local database_dump="${backup_dir}/tragofone-tables.dump"
		if ! runuser -u "${DATABASE_OS_USER}" -- pg_dump --format=custom --no-owner --no-privileges --table='public.v_tragofone_*' --dbname="${DATABASE_NAME}" > "${database_dump}"; then
			die "Database backup failed for ${DATABASE_NAME}; files remain protected in ${backup_dir}."
		fi
		chmod 0600 "${database_dump}"
		checksum_files+=("tragofone-tables.dump")
	else
		warn "Database backup skipped by explicit request."
	fi
	(
		cd "${backup_dir}"
		sha256sum "${checksum_files[@]}" > SHA256SUMS
	)
	chmod 0600 "${backup_dir}/SHA256SUMS"
	note "Backup created: ${backup_dir}"
	note "Backup contents are root-only and may contain encrypted credentials."
}

restore_active_timers() {
	local exit_code=$?
	trap - EXIT
	if [[ ${exit_code} -ne 0 ]]; then
		warn "Maintenance failed; restoring timers that were active before the attempt."
		[[ "${WORKER_TIMER_WAS_ACTIVE:-0}" == "1" ]] && systemctl start "${WORKER_TIMER}" >/dev/null 2>&1 || true
		[[ "${RECONCILE_TIMER_WAS_ACTIVE:-0}" == "1" ]] && systemctl start "${RECONCILE_TIMER}" >/dev/null 2>&1 || true
	fi
	exit "${exit_code}"
}

deploy_source() {
	require_root
	[[ -x "${INSTALLER}" ]] || die "Installer is missing or not executable: ${INSTALLER}"
	local installed=0
	[[ -f "${TARGET}/app_config.php" ]] && installed=1
	if [[ "${COMMAND}" == "upgrade" && ${installed} -eq 0 ]]; then
		die "Tragofone is not installed; use the install command."
	fi
	if [[ ${installed} -eq 1 && "${COMMAND}" != "repair" ]]; then
		local available_version
		local current_version
		available_version="$(source_version)"
		current_version="$(installed_version)"
		[[ "${available_version}" != "unknown" && "${current_version}" != "not-installed" ]] || die "Unable to compare source and installed versions."
		[[ -n "${PHP_BIN}" && -x "${PHP_BIN}" ]] || die "PHP CLI is required to validate the upgrade version."
		if ! "${PHP_BIN}" -r 'exit(version_compare($argv[1], $argv[2], "<") ? 1 : 0);' "${available_version}" "${current_version}"; then
			die "Refusing to downgrade from ${current_version} to ${available_version}; schema migrations are forward-only."
		fi
	fi
	if [[ ( "${COMMAND}" == "install" || "${COMMAND}" == "upgrade" ) && ${installed} -eq 1 ]]; then
		if [[ "$(readlink -f "${SOURCE_APP}")" == "$(readlink -f "${TARGET}")" ]]; then
			die "Run install/upgrade from an unpacked approved source tree; use repair for the installed copy."
		fi
	fi
	WORKER_TIMER_WAS_ACTIVE=0
	RECONCILE_TIMER_WAS_ACTIVE=0
	trap restore_active_timers EXIT
	if command_exists systemctl; then
		systemctl is-active --quiet "${WORKER_TIMER}" && WORKER_TIMER_WAS_ACTIVE=1 || true
		systemctl is-active --quiet "${RECONCILE_TIMER}" && RECONCILE_TIMER_WAS_ACTIVE=1 || true
		if [[ ${installed} -eq 1 ]]; then
			systemctl stop "${WORKER_TIMER}" "${RECONCILE_TIMER}" 2>/dev/null || true
		fi
	fi
	if [[ ${installed} -eq 1 && ${ALLOW_PROCESSING} -eq 0 ]]; then
		local count
		if ! count="$(processing_jobs)"; then
			die "Unable to confirm synchronization job state; use --allow-processing only after checking manually."
		fi
		[[ "${count}" == "0" ]] || die "${count} synchronization job(s) are processing; wait for them or use --allow-processing after review."
	fi
	if [[ ${installed} -eq 1 && ${NO_BACKUP} -eq 0 ]]; then
		backup_installation
	elif [[ ${installed} -eq 1 ]]; then
		warn "Automatic backup skipped by explicit request."
	fi
	FUSIONPBX_ROOT="${FUSIONPBX_ROOT}" \
	PHP_BIN="${PHP_BIN}" \
	FUSIONPBX_USER="${FUSIONPBX_USER}" \
	FUSIONPBX_GROUP="${FUSIONPBX_GROUP}" \
		"${INSTALLER}"
	trap - EXIT
	run_doctor
}

run_service() {
	require_root
	local unit="$1"
	if ! systemctl start "${unit}"; then
		journalctl -u "${unit}" -n "${LOG_LINES}" --no-pager || true
		return 1
	fi
	systemctl show "${unit}" -p ActiveState -p SubState -p Result -p ExecMainStatus --no-pager
	journalctl -u "${unit}" -n "${LOG_LINES}" --no-pager
}

show_logs() {
	require_root
	case "${COMMAND_ARGUMENT:-all}" in
		worker)
			journalctl -u "${WORKER_SERVICE}" -n "${LOG_LINES}" --no-pager
			;;
		reconcile)
			journalctl -u "${RECONCILE_SERVICE}" -n "${LOG_LINES}" --no-pager
			;;
		all|"")
			journalctl -u "${WORKER_SERVICE}" -u "${RECONCILE_SERVICE}" -n "${LOG_LINES}" --no-pager
			;;
		*)
			die "Log name must be worker, reconcile, or all."
			;;
	esac
}

case "${COMMAND}" in
	install|upgrade|repair)
		deploy_source
		;;
	backup)
		backup_installation
		;;
	status)
		show_status
		;;
	doctor)
		run_doctor
		;;
	worker)
		run_service "${WORKER_SERVICE}"
		;;
	reconcile)
		run_service "${RECONCILE_SERVICE}"
		;;
	timers)
		require_root
		systemctl daemon-reload
		systemctl enable --now "${WORKER_TIMER}" "${RECONCILE_TIMER}"
		service_state "${WORKER_TIMER}"
		service_state "${RECONCILE_TIMER}"
		;;
	logs)
		show_logs
		;;
	*)
		die "Unknown command: ${COMMAND}. Run manage.sh help for usage."
		;;
esac
