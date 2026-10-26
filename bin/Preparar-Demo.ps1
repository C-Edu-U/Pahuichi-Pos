[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$proyecto = Split-Path -Parent $PSScriptRoot
$compose = Join-Path $proyecto 'docker-compose.demo.yml'
if (!(Test-Path -LiteralPath $compose)) { throw 'Falta docker-compose.demo.yml.' }
if (!(Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Abre Docker Desktop e instala Docker antes de continuar.' }
docker info --format '{{.ServerVersion}}'
if ($LASTEXITCODE -ne 0) { throw 'Docker Desktop no responde. Abrelo y espera a que termine de iniciar.' }
Push-Location $proyecto
try {
    # Un proyecto y volumen propios: no detiene ni modifica pahuichi-app-1.
    docker compose -p pahuichi-demo-hu65 -f $compose up -d --build --wait --wait-timeout 600
    if ($LASTEXITCODE -ne 0) {
        docker compose -p pahuichi-demo-hu65 -f $compose logs --tail 60 app
        throw 'La demo no termino de iniciar. Comparte la salida. Los datos habituales se conservaron.'
    }
    docker compose -p pahuichi-demo-hu65 -f $compose exec -T app php bin/demo.php resumen
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar el resumen de la demo.' }
    # up no reinicia un contenedor sano existente: repetir explicitamente el cargador prueba idempotencia.
    docker compose -p pahuichi-demo-hu65 -f $compose exec -T app php bin/demo.php cargar
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo verificar la carga de la demo.' }
    $puerto = '8446'
    if ($env:HU65_HTTPS) { $puerto = $env:HU65_HTTPS }
    Write-Host "Demo HU-65 lista: https://localhost:$puerto"
    Write-Host 'Administrador: admin / Pahuichi2026. Cajeros: mariela o jorge / Pahuichi2026.'
    Write-Host 'Ventas historicas: 25/10/2026, dos turnos cerrados, 20 ventas cada uno.'
    Write-Host 'La instancia habitual y su base no se modificaron. No se hizo commit ni push.'
}
finally { Pop-Location }
