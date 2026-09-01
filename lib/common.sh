#!/usr/bin/env bash
# shellcheck disable=SC2034

TOOL_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

log() {
    printf '[module-deployer] %s\n' "$*"
}

die() {
    printf '[module-deployer] ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Required command is missing: $1"
}

require_value() {
    local name=$1
    [[ -n ${!name:-} ]] || die "Required configuration is empty: $name"
}

validate_safe_atom() {
    local label=$1 value=$2
    [[ $value =~ ^[A-Za-z0-9_./:@+-]+$ ]] || die "$label contains unsupported characters"
}

parse_config_argument() {
    CONFIG_FILE=
    ASSUME_YES=false

    while (($#)); do
        case "$1" in
            --config)
                (($# >= 2)) || die '--config requires a path'
                CONFIG_FILE=$2
                shift 2
                ;;
            --yes)
                ASSUME_YES=true
                shift
                ;;
            --help|-h)
                return 2
                ;;
            *)
                die "Unknown argument: $1"
                ;;
        esac
    done

    [[ -n $CONFIG_FILE ]] || die 'Use --config /absolute/path/to/module-deployer.conf'
    [[ $CONFIG_FILE = /* ]] || die 'Configuration path must be absolute'
    [[ -r $CONFIG_FILE ]] || die "Configuration is not readable: $CONFIG_FILE"
}

load_host_config() {
    # The host config is trusted root-controlled shell syntax.
    # shellcheck disable=SC1090
    source "$CONFIG_FILE"

    : "${MIN_FREE_GB:=8}"
    : "${HEALTH_ATTEMPTS:=30}"
    : "${HEALTH_DELAY_SECONDS:=2}"
    : "${STARTUP_LOG_LINES:=300}"

    local required=(
        COMPOSE_DIR COMPOSE_FILE OVERRIDE_FILE STATE_DIR BACKUP_ROOT
        APP_SERVICE APP_CONTAINER DB_CONTAINER DB_NAME HEALTH_URL
        CATTR_RUNTIME_IMAGE CATTR_BUILDER_IMAGE CANDIDATE_IMAGE_PREFIX
        ROLLBACK_IMAGE_PREFIX
    )
    local name
    for name in "${required[@]}"; do
        require_value "$name"
    done

    [[ $COMPOSE_DIR = /* && $COMPOSE_FILE = /* && $OVERRIDE_FILE = /* ]] ||
        die 'Compose paths must be absolute'
    [[ $STATE_DIR = /* && $BACKUP_ROOT = /* ]] ||
        die 'State and backup paths must be absolute'
    [[ -d $COMPOSE_DIR ]] || die "Compose directory is missing: $COMPOSE_DIR"
    [[ -f $COMPOSE_FILE ]] || die "Compose file is missing: $COMPOSE_FILE"

    validate_safe_atom APP_SERVICE "$APP_SERVICE"
    validate_safe_atom APP_CONTAINER "$APP_CONTAINER"
    validate_safe_atom DB_CONTAINER "$DB_CONTAINER"
    validate_safe_atom DB_NAME "$DB_NAME"
    validate_safe_atom CATTR_RUNTIME_IMAGE "$CATTR_RUNTIME_IMAGE"
    validate_safe_atom CATTR_BUILDER_IMAGE "$CATTR_BUILDER_IMAGE"
    validate_safe_atom CANDIDATE_IMAGE_PREFIX "$CANDIDATE_IMAGE_PREFIX"
    validate_safe_atom ROLLBACK_IMAGE_PREFIX "$ROLLBACK_IMAGE_PREFIX"

    [[ $MIN_FREE_GB =~ ^[0-9]+$ ]] || die 'MIN_FREE_GB must be an integer'
    [[ $HEALTH_ATTEMPTS =~ ^[0-9]+$ ]] || die 'HEALTH_ATTEMPTS must be an integer'
    [[ $HEALTH_DELAY_SECONDS =~ ^[0-9]+$ ]] || die 'HEALTH_DELAY_SECONDS must be an integer'
}

declare -a MODULE_KEYS=()
declare -a MODULE_SOURCE_KINDS=()
declare -a MODULE_SOURCE_URLS=()
declare -a MODULE_SOURCE_REFS=()
declare -a MODULE_SOURCE_COMMITS=()
declare -a MODULE_SOURCE_SHA256S=()
declare -a MODULE_INSTALL_DIRS=()
declare -a MODULE_PROVIDER_CLASSES=()
declare -a MODULE_CATTR_NAMES=()
declare -a MODULE_ENV_FILES=()
declare -a MODULE_ENABLE_VARIABLES=()
declare -a MODULE_REQUIRED_TABLES_LIST=()
declare -a MODULE_INCLUDE_PATHS_LIST=()

load_modules() {
    local file
    shopt -s nullglob
    local files=("$TOOL_ROOT"/modules/*.conf)
    shopt -u nullglob
    ((${#files[@]} > 0)) || die 'No module definitions found under modules/'

    for file in "${files[@]}"; do
        unset MODULE_KEY MODULE_SOURCE_KIND MODULE_SOURCE_URL MODULE_SOURCE_REF
        unset MODULE_SOURCE_COMMIT MODULE_SOURCE_SHA256 MODULE_INSTALL_DIR
        unset MODULE_PROVIDER_CLASS MODULE_CATTR_NAME MODULE_ENV_FILE
        unset MODULE_ENABLE_VARIABLE MODULE_REQUIRED_TABLES MODULE_INCLUDE_PATHS

        # Module definitions are reviewed, repository-controlled shell syntax.
        # shellcheck disable=SC1090
        source "$file"

        local required=(MODULE_KEY MODULE_SOURCE_KIND MODULE_SOURCE_URL
            MODULE_INSTALL_DIR MODULE_PROVIDER_CLASS MODULE_CATTR_NAME
            MODULE_ENV_FILE MODULE_ENABLE_VARIABLE)
        local name
        for name in "${required[@]}"; do
            require_value "$name"
        done

        [[ $MODULE_SOURCE_KIND == git || $MODULE_SOURCE_KIND == release ]] ||
            die "$file: MODULE_SOURCE_KIND must be git or release"
        [[ $MODULE_INSTALL_DIR =~ ^[A-Za-z][A-Za-z0-9]+$ ]] ||
            die "$file: invalid MODULE_INSTALL_DIR"
        [[ $MODULE_CATTR_NAME =~ ^[A-Za-z][A-Za-z0-9]+$ ]] ||
            die "$file: invalid MODULE_CATTR_NAME"
        [[ $MODULE_ENABLE_VARIABLE =~ ^[A-Z][A-Z0-9_]+$ ]] ||
            die "$file: invalid MODULE_ENABLE_VARIABLE"
        [[ $MODULE_PROVIDER_CLASS =~ ^Modules\\[A-Za-z0-9\\]+$ ]] ||
            die "$file: invalid MODULE_PROVIDER_CLASS"
        [[ $MODULE_ENV_FILE = /* ]] || die "$file: MODULE_ENV_FILE must be absolute"
        [[ -f $MODULE_ENV_FILE ]] || die "$file: missing environment file $MODULE_ENV_FILE"

        local mode permissions
        mode=$(stat -c '%a' "$MODULE_ENV_FILE")
        permissions=$((8#$mode))
        (( (permissions & 077) == 0 )) ||
            die "$MODULE_ENV_FILE must not be readable or writable by group/other"

        if [[ $MODULE_SOURCE_KIND == git ]]; then
            require_value MODULE_SOURCE_REF
            require_value MODULE_SOURCE_COMMIT
            [[ $MODULE_SOURCE_COMMIT =~ ^[0-9a-f]{40}$ ]] ||
                die "$file: MODULE_SOURCE_COMMIT must be a full lowercase SHA"
            require_value MODULE_INCLUDE_PATHS
        else
            require_value MODULE_SOURCE_SHA256
            [[ $MODULE_SOURCE_SHA256 =~ ^[0-9a-f]{64}$ ]] ||
                die "$file: MODULE_SOURCE_SHA256 must be SHA-256"
        fi

        local table
        for table in ${MODULE_REQUIRED_TABLES:-}; do
            [[ $table =~ ^[A-Za-z][A-Za-z0-9_]+$ ]] ||
                die "$file: invalid MODULE_REQUIRED_TABLES entry: $table"
        done

        MODULE_KEYS+=("$MODULE_KEY")
        MODULE_SOURCE_KINDS+=("$MODULE_SOURCE_KIND")
        MODULE_SOURCE_URLS+=("$MODULE_SOURCE_URL")
        MODULE_SOURCE_REFS+=("${MODULE_SOURCE_REF:-}")
        MODULE_SOURCE_COMMITS+=("${MODULE_SOURCE_COMMIT:-}")
        MODULE_SOURCE_SHA256S+=("${MODULE_SOURCE_SHA256:-}")
        MODULE_INSTALL_DIRS+=("$MODULE_INSTALL_DIR")
        MODULE_PROVIDER_CLASSES+=("$MODULE_PROVIDER_CLASS")
        MODULE_CATTR_NAMES+=("$MODULE_CATTR_NAME")
        MODULE_ENV_FILES+=("$MODULE_ENV_FILE")
        MODULE_ENABLE_VARIABLES+=("$MODULE_ENABLE_VARIABLE")
        MODULE_REQUIRED_TABLES_LIST+=("${MODULE_REQUIRED_TABLES:-}")
        MODULE_INCLUDE_PATHS_LIST+=("${MODULE_INCLUDE_PATHS:-}")
    done
}

verify_module_database() {
    local index table count
    for index in "${!MODULE_KEYS[@]}"; do
        for table in ${MODULE_REQUIRED_TABLES_LIST[$index]}; do
            count=$(docker exec "$DB_CONTAINER" sh -lc \
                'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$1'\'' AND table_name = '\''$2'\'';"' \
                sh "$DB_NAME" "$table")
            [[ $count == 1 ]] ||
                die "Required database table is missing for ${MODULE_KEYS[$index]}: $table"
        done
    done
}

compose_with_override() {
    docker compose --project-directory "$COMPOSE_DIR" \
        -f "$COMPOSE_FILE" -f "$OVERRIDE_FILE" "$@"
}

write_override() {
    local image=$1 mode=$2
    local temporary
    temporary=$(mktemp "${OVERRIDE_FILE}.tmp.XXXXXX")
    chmod 600 "$temporary"

    {
        printf 'services:\n'
        printf '  %s:\n' "$APP_SERVICE"
        printf "    image: '%s'\n" "$image"
        printf '    env_file:\n'
        local env_file
        for env_file in "${MODULE_ENV_FILES[@]}"; do
            printf "      - '%s'\n" "$env_file"
        done
        if [[ $mode == disabled ]]; then
            printf '    environment:\n'
            local variable
            for variable in "${MODULE_ENABLE_VARIABLES[@]}"; do
                printf "      %s: 'false'\n" "$variable"
            done
        fi
    } >"$temporary"

    mv "$temporary" "$OVERRIDE_FILE"
    chmod 600 "$OVERRIDE_FILE"
    compose_with_override config --quiet
}

wait_for_health() {
    local attempt
    for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
        if curl --fail --silent --show-error --max-time 5 "$HEALTH_URL" >/dev/null; then
            return 0
        fi
        sleep "$HEALTH_DELAY_SECONDS"
    done
    return 1
}

verify_running_app() {
    local report_dir=${1:-} expected_enable_mode=${2:-present}
    local status restart_count module_output logs
    status=$(docker inspect "$APP_CONTAINER" --format '{{.State.Status}}')
    restart_count=$(docker inspect "$APP_CONTAINER" --format '{{.RestartCount}}')
    [[ $status == running ]] || die "Application container state is $status"
    [[ $restart_count == 0 ]] || die "Application restart count is $restart_count"
    wait_for_health || die "Health endpoint did not recover: $HEALTH_URL"

    logs=$(docker logs --tail "$STARTUP_LOG_LINES" "$APP_CONTAINER" 2>&1 || true)
    if [[ -n $report_dir ]]; then
        grep -F 'service legacy-services successfully started' <<<"$logs" >/dev/null ||
            die 'Cattr startup completion marker is missing from container logs'
        if grep -E 's6-rc: warning|Fatal error|Uncaught|service .* failed' <<<"$logs" >/dev/null; then
            die 'A fatal startup pattern was found in container logs'
        fi
    fi

    module_output=$(docker exec "$APP_CONTAINER" /usr/bin/php82 /app/artisan module:list)
    local module_name
    for module_name in "${MODULE_CATTR_NAMES[@]}"; do
        grep -F "[Enabled] $module_name" <<<"$module_output" >/dev/null ||
            die "Cattr did not report module enabled: $module_name"
    done

    local variable value
    for variable in "${MODULE_ENABLE_VARIABLES[@]}"; do
        value=$(docker exec "$APP_CONTAINER" printenv "$variable") ||
            die "Runtime environment variable is missing: $variable"
        if [[ $expected_enable_mode == disabled && $value != false ]]; then
            die "$variable was not forced off during the disabled stage"
        fi
    done

    verify_module_database

    if [[ -n $report_dir ]]; then
        install -d -m 700 "$report_dir"
        printf '%s\n' "$module_output" >"$report_dir/module-list.txt"
        printf '%s\n' "$logs" >"$report_dir/app-startup.log"
        curl --fail --silent --show-error --max-time 5 "$HEALTH_URL" \
            >"$report_dir/health-response.txt"
    fi
}

save_state() {
    local state_file=$STATE_DIR/current.env
    install -d -m 700 "$STATE_DIR"
    umask 077
    {
        printf 'DEPLOYMENT_ID=%q\n' "$DEPLOYMENT_ID"
        printf 'BACKUP_DIR=%q\n' "$BACKUP_DIR"
        printf 'PREVIOUS_IMAGE_ID=%q\n' "$PREVIOUS_IMAGE_ID"
        printf 'ROLLBACK_IMAGE=%q\n' "$ROLLBACK_IMAGE"
        printf 'CANDIDATE_IMAGE=%q\n' "$CANDIDATE_IMAGE"
        printf 'PREVIOUS_OVERRIDE_PRESENT=%q\n' "$PREVIOUS_OVERRIDE_PRESENT"
    } >"$state_file"
    chmod 600 "$state_file"
}

load_state() {
    local state_file=$STATE_DIR/current.env
    [[ -r $state_file ]] || die "Deployment state is missing: $state_file"
    # State is generated by this tool and contains no secrets.
    # shellcheck disable=SC1090
    source "$state_file"
}
