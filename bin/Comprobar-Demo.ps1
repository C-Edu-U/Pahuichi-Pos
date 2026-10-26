[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$proyecto = Split-Path -Parent $PSScriptRoot
$compose = Join-Path $proyecto 'docker-compose.demo.yml'
Push-Location $proyecto
try {
    $antes = @(docker compose -p pahuichi-demo-hu65 -f $compose exec -T app php bin/demo.php huella)
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo leer la demo. Ejecuta primero bin\Preparar-Demo.ps1.' }
    docker compose -p pahuichi-demo-hu65 -f $compose exec -T app php bin/demo.php cargar
    if ($LASTEXITCODE -ne 0) { throw 'Fallo la segunda carga.' }
    $despues = @(docker compose -p pahuichi-demo-hu65 -f $compose exec -T app php bin/demo.php huella)
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo verificar el resultado.' }
    if (($antes -join '') -ne ($despues -join '')) {
        throw 'La base cambio durante la prueba. Cierra otras sesiones y repite sin usar la demo al mismo tiempo.'
    }
    Write-Host 'Escenario 2: correcto. Todas las filas quedaron iguales; no se duplicaron ni modificaron registros.'
}
finally { Pop-Location }
