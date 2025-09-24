#!/bin/bash

set -e

scriptDir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
projectDir="$scriptDir/.."
composeFile="$scriptDir/docker/docker-compose.yml"
envFile="$scriptDir/docker/.env"

# Load .env manually
if [ -f "$envFile" ]; then
    export $(grep -v '^#' "$envFile" | xargs)
fi

# Default command
cmd="${1:-up --build}"

echo "📦 APP_PATH:     $APP_PATH"
echo "📄 Compose file: $composeFile"
echo "▶️  docker compose $cmd"
echo

# Actions
case "$cmd" in
  down)
    echo "🔻 docker compose down"
    docker compose -f "$composeFile" --env-file "$envFile" --project-directory "$projectDir" down --remove-orphans
    ;;
  restart)
    echo "🔁 docker compose restart"
    docker compose -f "$composeFile" --env-file "$envFile" --project-directory "$projectDir" restart
    ;;
  build)
    echo "🔨 docker compose build --progress=plain"
    docker compose -f "$composeFile" --env-file "$envFile" --project-directory "$projectDir" build --progress=plain
    ;;
  *)
    echo "🚀 docker compose up -d --build"
    docker compose -f "$composeFile" --env-file "$envFile" --project-directory "$projectDir" up -d --build
    ;;
esac
