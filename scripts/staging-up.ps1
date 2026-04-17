param(
    [string]$ComposeFile = "staging/docker-compose.yml",
    [string]$EnvFile = "staging/.env"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $EnvFile)) {
    Copy-Item "staging/.env.example" $EnvFile
    Write-Host "Created $EnvFile from template. Update credentials if needed."
}

docker compose --env-file $EnvFile -f $ComposeFile up -d db wordpress wpcli

# Run bootstrap once containers are up.
docker compose --env-file $EnvFile -f $ComposeFile exec -T wpcli sh /scripts/bootstrap-staging.sh

Write-Host "Staging ready at http://localhost:8080"
Write-Host "WP Admin: http://localhost:8080/wp-admin"
