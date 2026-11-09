#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")"

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
else
    echo "Docker Compose não está instalado." >&2
    exit 1
fi

SERVICE=php

exec_in_container() {
    local tty_args=()

    if [ ! -t 0 ]; then
        tty_args=(-T)
    fi

    "${COMPOSE[@]}" exec "${tty_args[@]}" "$SERVICE" "$@"
}

if [ $# -eq 0 ] || [ "$1" = "help" ] || [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    cat <<'EOF'
Uso: ./sail <comando>

Container:
  ./sail up -d          sobe os containers
  ./sail down           derruba os containers
  ./sail ps             lista os containers
  ./sail logs           acompanha os logs
  ./sail shell          abre um shell no PHP

Dentro do PHP:
  ./sail test           roda a suíte inteira
  ./sail test <arquivo> roda um arquivo do PHPUnit
  ./sail php <args>     roda o PHP
  ./sail composer <args>
  ./sail <comando>      qualquer outro comando no container
EOF
    exit 0
fi

case "$1" in
    up|down|stop|restart|ps|build|pull|logs)
        "${COMPOSE[@]}" "$@"
        ;;
    php)
        shift
        exec_in_container php "$@"
        ;;
    composer)
        shift
        exec_in_container composer "$@"
        ;;
    test|phpunit)
        shift
        exec_in_container vendor/bin/phpunit "$@"
        ;;
    shell|bash|sh)
        exec_in_container sh
        ;;
    *)
        exec_in_container "$@"
        ;;
esac
