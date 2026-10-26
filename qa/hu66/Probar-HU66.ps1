[CmdletBinding()]
param([ValidateSet('Menu','Preparar','Cobertura','Seguridad','Carga','Todo','Detener')][string]$Accion='Menu')
$ErrorActionPreference='Stop'
$compose=Join-Path $PSScriptRoot 'compose.yml'
$proyecto=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
if (!(Test-Path -LiteralPath (Join-Path $proyecto 'composer.json'))) { throw 'Ejecuta el script instalado dentro de qa\hu66 del proyecto.' }
if ($Accion -eq 'Menu') {
    do {
        Write-Host ''
        Write-Host 'HU-66 - PRUEBAS DEL SISTEMA' -ForegroundColor Cyan
        Write-Host '1. Escenario 1: PHPUnit y cobertura (minimo 60% por area)'
        Write-Host '2. Escenario 2: seguridad con OWASP ZAP (puede tardar varios minutos)'
        Write-Host '3. Escenario 3: carga con k6 (perfil de 50 segundos)'
        Write-Host '4. Ejecutar los tres escenarios'
        Write-Host '5. Abrir la carpeta de evidencias'
        Write-Host '6. Detener el entorno de pruebas, conservando evidencias'
        Write-Host '0. Salir'
        $opcion = Read-Host 'Escribe el numero y pulsa Enter'
        $elegida = switch ($opcion) {
            '1' { 'Cobertura' }; '2' { 'Seguridad' }; '3' { 'Carga' }
            '4' { 'Todo' }; '6' { 'Detener' }; default { '' }
        }
        if ($opcion -eq '5') {
            $carpeta = Join-Path $PSScriptRoot 'evidencias'
            [void][IO.Directory]::CreateDirectory($carpeta)
            Start-Process -FilePath $carpeta
        } elseif ($elegida) {
            try { & $PSCommandPath -Accion $elegida }
            catch { Write-Host $_.Exception.Message -ForegroundColor Red }
        }
    } while ($opcion -ne '0')
    return
}
$mutex=New-Object Threading.Mutex($false,'PahuichiHU66')
if (!$mutex.WaitOne(0)) { $mutex.Dispose();throw 'Ya hay una prueba HU-66 ejecutandose. Espera a que termine.' }
$anterior=$env:HU66_EVIDENCIAS
$transcripcion=$false
function Invoke-Qa([string[]]$Argumentos,[switch]$PermitirFallo) {
    & docker compose --project-name pahuichi-hu66 --file $compose @Argumentos | Out-Host
    $codigo=$LASTEXITCODE
    if ($codigo -ne 0 -and !$PermitirFallo) { throw "Fallo Docker en HU-66 (codigo $codigo). Comparte la salida." }
    return $codigo
}
try {
    if ($Accion -eq 'Detener') {
        Invoke-Qa -Argumentos @('stop') | Out-Null
        Write-Host 'QA detenida. Los contenedores pahuichi habituales no se tocaron. Se conservaron volumenes y evidencias.'
        return
    }
    $salida=Join-Path $PSScriptRoot ('evidencias\'+(Get-Date -Format 'yyyyMMdd-HHmmss-fff')+'-'+$Accion)
    New-Item -ItemType Directory -Path $salida -Force | Out-Null
    $env:HU66_EVIDENCIAS=$salida
    Start-Transcript -Path (Join-Path $salida 'ejecucion-powershell.txt') | Out-Null
    $transcripcion=$true
    Write-Host 'La primera ejecucion necesita internet para descargar imagenes. Espera a que termine.'
    Write-Host 'HU-66 usa una instantanea y bases propias. No cambia la aplicacion habitual.' -ForegroundColor Cyan
    Invoke-Qa -Argumentos @('config','--quiet') | Out-Null
    Invoke-Qa -Argumentos @('stop','app') | Out-Null
    Invoke-Qa -Argumentos @('build','preparar') | Out-Null
    Invoke-Qa -Argumentos @('up','-d','--wait','db') | Out-Null
    Invoke-Qa -Argumentos @('run','--rm','-T','preparar') | Out-Null
    $resultados=@()
    if ($Accion -in @('Cobertura','Todo')) {
        $codigo=Invoke-Qa -Argumentos @('run','--rm','-T','pruebas') -PermitirFallo
        $resultados += [PSCustomObject]@{Escenario=1;Prueba='Cobertura y PHPUnit';Codigo=$codigo}
    }
    foreach ($prueba in @('Seguridad','Carga')) {
        if ($Accion -ne $prueba -and $Accion -ne 'Todo') { continue }
        Invoke-Qa -Argumentos @('stop','app') | Out-Null
        Invoke-Qa -Argumentos @('run','--rm','-T','datos') | Out-Null
        Invoke-Qa -Argumentos @('up','-d','--no-build','--no-deps','--force-recreate','--wait','app') | Out-Null
        if ($prueba -eq 'Seguridad') {
            Write-Host 'Escenario 2: descargando ZAP e iniciando escaneo real. Revisa el progreso en esta ventana.' -ForegroundColor Cyan
            Invoke-Qa -Argumentos @('pull','zap') | Out-Null
            $motor=Invoke-Qa -Argumentos @('run','--rm','-T','zap') -PermitirFallo
            $codigo=Invoke-Qa -Argumentos @('run','--rm','-T','evaluar','seguridad') -PermitirFallo
            $resultados += [PSCustomObject]@{Escenario=2;Prueba='ZAP';Codigo=$codigo;Motor=$motor}
        } else {
            Write-Host 'Escenario 3: k6 ejecutara el perfil de 50 segundos contra la copia de QA.' -ForegroundColor Cyan
            Invoke-Qa -Argumentos @('pull','carga') | Out-Null
            $motor=Invoke-Qa -Argumentos @('run','--rm','-T','carga') -PermitirFallo
            [IO.File]::WriteAllText((Join-Path $salida 'k6-exit.txt'),[string]$motor)
            $codigo=Invoke-Qa -Argumentos @('run','--rm','-T','evaluar','carga') -PermitirFallo
            $resultados += [PSCustomObject]@{Escenario=3;Prueba='k6';Codigo=$codigo;Motor=$motor}
        }
    }
    $imagenes = @('pahuichi-hu66-app', 'mariadb:11.4')
    if ($Accion -in @('Seguridad','Todo')) { $imagenes += 'ghcr.io/zaproxy/zaproxy:stable' }
    if ($Accion -in @('Carga','Todo')) { $imagenes += 'grafana/k6:latest' }
    & docker image inspect $imagenes | Set-Content -LiteralPath (Join-Path $salida 'imagenes-docker.json') -Encoding UTF8
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron registrar las imagenes utilizadas.' }
    Invoke-Qa -Argumentos @('images') | Out-Host
    & docker image inspect pahuichi-hu66-app --format '{{.Id}}' | Set-Content -LiteralPath (Join-Path $salida 'imagen-php.txt') -Encoding UTF8
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo registrar la imagen usada por QA.' }
    ConvertTo-Json -InputObject @($resultados) -Depth 4 | Set-Content -LiteralPath (Join-Path $salida 'resultados.json') -Encoding UTF8
    Write-Host "Evidencias: $salida" -ForegroundColor Cyan
    if ($Accion -eq 'Preparar') {
        Write-Host 'Entorno preparado. Ejecuta -Accion Cobertura, luego Seguridad y Carga.'
    } elseif (@($resultados | Where-Object { $_.Codigo -ne 0 -or ($_.PSObject.Properties.Name -contains 'Motor' -and $_.Motor -ne 0) }).Count -gt 0) {
        Write-Host 'Hay pruebas pendientes o fallidas. No marques la HU como terminada; comparte los reportes.' -ForegroundColor Yellow
    } else {
        Write-Host 'Las pruebas solicitadas cumplieron en esta instantanea. Conserva los informes para revision de Eduardo.' -ForegroundColor Green
    }
    foreach ($tipo in @('cobertura','seguridad','carga')) {
        $informe = Join-Path $salida ('resultado-' + $tipo + '.html')
        if (Test-Path -LiteralPath $informe) { Start-Process -FilePath $informe }
    }
} finally {
    if ($transcripcion) { Stop-Transcript | Out-Null }
    $env:HU66_EVIDENCIAS=$anterior
    $mutex.ReleaseMutex();$mutex.Dispose()
}
