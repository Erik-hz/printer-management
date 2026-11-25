[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [string]$ConfigPath = (Join-Path $PSScriptRoot 'printers.snmp.local.json'),
    [string]$ApiUrl = $env:MONITOREO_API_URL,
    [string]$Token = $env:MONITOREO_TOKEN,
    [string]$Community = $env:SNMP_COMMUNITY_GET,
    [ValidateSet('1', '2c')][string]$SnmpVersion = '1',
    [int]$TimeoutMilliseconds = 5000,
    [int]$PrinterId = 0
)

$ErrorActionPreference = 'Stop'
Import-Module -Name (Join-Path $PSScriptRoot 'SnmpV1.psm1') -Force -ErrorAction Stop

function Test-PrivateIPv4 {
    param([System.Net.IPAddress]$Address)

    if ([System.Net.IPAddress]::IsLoopback($Address)) {
        return $true
    }

    if ($Address.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork) {
        return $false
    }

    $bytes = $Address.GetAddressBytes()

    return $bytes[0] -eq 10 `
        -or ($bytes[0] -eq 172 -and $bytes[1] -ge 16 -and $bytes[1] -le 31) `
        -or ($bytes[0] -eq 192 -and $bytes[1] -eq 168)
}

function Test-PrivateApiAddress {
    param([System.Net.IPAddress]$Address)

    if ([System.Net.IPAddress]::IsLoopback($Address)) {
        return $true
    }

    if ($Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork) {
        return Test-PrivateIPv4 $Address
    }

    if ($Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetworkV6) {
        $text = $Address.ToString().ToLowerInvariant()

        return $Address.IsIPv6LinkLocal -or $text.StartsWith('fc') -or $text.StartsWith('fd')
    }

    return $false
}

if ($null -eq $Community) {
    throw 'Defina SNMP_COMMUNITY_GET localmente. No incluya la comunidad en el archivo de configuracion.'
}
if (-not (Test-Path -LiteralPath $ConfigPath -PathType Leaf)) {
    throw "No existe el archivo local de mapeos SNMP: $ConfigPath."
}
if ($TimeoutMilliseconds -lt 250 -or $TimeoutMilliseconds -gt 10000) {
    throw 'TimeoutMilliseconds debe estar entre 250 y 10000.'
}
if ($PrinterId -lt 0) {
    throw 'PrinterId debe ser un entero mayor que cero o cero para consultar todas las impresoras elegibles.'
}

$endpoint = $null
if ([string]::IsNullOrWhiteSpace($Token)) {
    throw 'Defina MONITOREO_TOKEN en el entorno del proceso.'
}
if ([string]::IsNullOrWhiteSpace($ApiUrl)) {
    throw 'Defina MONITOREO_API_URL con la URL completa del endpoint /api/monitoring/lecturas.'
}
if (-not [System.Uri]::TryCreate($ApiUrl, [System.UriKind]::Absolute, [ref]$endpoint)) {
    throw 'MONITOREO_API_URL no es una URL absoluta valida.'
}
if ($endpoint.Scheme -notin @('https', 'http') -or $endpoint.UserInfo) {
    throw 'La URL debe usar HTTP o HTTPS y no debe incluir usuario ni contrasena.'
}
if ($endpoint.AbsolutePath.TrimEnd('/') -ne '/api/monitoring/lecturas' -or $endpoint.Query -or $endpoint.Fragment) {
    throw 'MONITOREO_API_URL debe apuntar exactamente a /api/monitoring/lecturas, sin query ni fragmento.'
}

$direccionesApi = @([System.Net.Dns]::GetHostAddresses($endpoint.DnsSafeHost))
if ($direccionesApi.Count -eq 0 -or @($direccionesApi | Where-Object { -not (Test-PrivateApiAddress $_) }).Count -gt 0) {
    throw 'La API debe resolver unicamente a localhost o a una direccion privada.'
}
if ($endpoint.Scheme -eq 'http' -and @($direccionesApi | Where-Object { -not [System.Net.IPAddress]::IsLoopback($_) }).Count -gt 0) {
    throw 'Use HTTPS para enviar el token a una API que no este en localhost.'
}

try {
    $config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
} catch {
    throw "No se pudo leer el JSON SNMP: $($_.Exception.Message)"
}
if ($null -eq $config -or
    $null -eq $config.PSObject.Properties['supply_mappings'] -or
    $null -ne $config.PSObject.Properties['printers']) {
    throw 'El JSON debe contener supply_mappings y no debe incluir el inventario de impresoras.'
}

$mapeos = @($config.supply_mappings | Where-Object { $null -ne $_ })
$mapeosPorImpresora = @{}
foreach ($mapeo in $mapeos) {
    $id = 0
    if (-not [int]::TryParse([string]$mapeo.printer_id, [ref]$id) -or $id -le 0) {
        throw 'Cada mapeo debe tener un printer_id entero mayor que cero.'
    }

    $descripcion = [string]$mapeo.description
    if ([string]::IsNullOrWhiteSpace($descripcion)) {
        throw "El mapeo de suministro para impresora $id requiere una descripcion no vacia."
    }

    $tipoConsumibleId = 0
    if (-not [int]::TryParse([string]$mapeo.tipo_consumible_id, [ref]$tipoConsumibleId) -or $tipoConsumibleId -le 0) {
        throw "La asignacion de suministro '$descripcion' para impresora $id requiere un tipo_consumible_id entero mayor que cero."
    }

    $color = [string]$mapeo.color
    if ($color -notin @('negro', 'cian', 'magenta', 'amarillo', 'tricolor', 'no_aplica')) {
        throw "La asignacion de suministro '$descripcion' para impresora $id tiene un color no valido."
    }

    $httpSupplyPath = [string]$mapeo.http_supply_path
    if ($httpSupplyPath -ne '' -and
        ($httpSupplyPath -notmatch '^/[A-Za-z0-9._/-]+$' -or $httpSupplyPath -match '(^|/)\.\.(/|$)')) {
        throw "La ruta HTTP de suministros para impresora $id no es valida. Use una ruta relativa sin host, query ni fragmento."
    }

    $idKey = [string]$id
    if (-not $mapeosPorImpresora.ContainsKey($idKey)) {
        $mapeosPorImpresora[$idKey] = @{
            descripciones = @{}
            niveles = @{}
            items = [System.Collections.Generic.List[object]]::new()
        }
    }
    $mapeosImpresora = $mapeosPorImpresora[$idKey]
    $nivelKey = "$tipoConsumibleId|$color"
    if ($mapeosImpresora.descripciones.ContainsKey($descripcion)) {
        throw "Las asignaciones de suministros para impresora $id deben tener descripciones unicas."
    }
    if ($mapeosImpresora.niveles.ContainsKey($nivelKey)) {
        throw "Las asignaciones de suministros para impresora $id repiten tipo y color ($nivelKey)."
    }

    $mapeosImpresora.descripciones[$descripcion] = $true
    $mapeosImpresora.niveles[$nivelKey] = $true
    $mapeosImpresora.items.Add($mapeo)
}

$listaUri = [System.UriBuilder]$endpoint
$listaUri.Path = '/api/monitoring/impresoras'
$listaUri.Query = if ($PrinterId -gt 0) { "printer_id=$PrinterId" } else { '' }
$impresorasRespuesta = Invoke-RestMethod `
    -Uri $listaUri.Uri `
    -Method Get `
    -Headers @{ Authorization = "Bearer $Token" } `
    -TimeoutSec 15
$impresoras = @($impresorasRespuesta.printers)
if ($impresoras.Count -eq 0) {
    if ($PrinterId -gt 0) {
        throw "La API no devolvio una impresora elegible con printer_id=$PrinterId."
    }
    throw 'La API no devolvio impresoras elegibles desde SQL.'
}

$idsVistos = @{}
$ipsVistas = @{}
foreach ($impresora in $impresoras) {
    $id = 0
    if (-not [int]::TryParse([string]$impresora.printer_id, [ref]$id) -or $id -le 0) {
        throw 'La API devolvio un printer_id invalido.'
    }

    $ip = $null
    if (-not [System.Net.IPAddress]::TryParse([string]$impresora.ip, [ref]$ip) -or -not (Test-PrivateIPv4 $ip)) {
        throw "La API devolvio una direccion que no es IPv4 privada para impresora $id."
    }

    $port = 0
    if (-not [int]::TryParse([string]$impresora.port, [ref]$port) -or $port -lt 1 -or $port -gt 65535) {
        throw "La API devolvio un puerto SNMP invalido para impresora $id."
    }

    if ($idsVistos.ContainsKey([string]$id) -or $ipsVistas.ContainsKey($ip.ToString())) {
        throw "La API devolvio printer_id $id o IP $ip duplicados."
    }

    $idsVistos[[string]$id] = $true
    $ipsVistas[$ip.ToString()] = $true
}

$pageCounterRoot = '1.3.6.1.2.1.43.10.2.1.4'
$supplyColumns = @{
    description = '1.3.6.1.2.1.43.11.1.1.6'
    colorant_index = '1.3.6.1.2.1.43.11.1.1.3'
    supply_class = '1.3.6.1.2.1.43.11.1.1.4'
    supply_type = '1.3.6.1.2.1.43.11.1.1.5'
    supply_unit = '1.3.6.1.2.1.43.11.1.1.7'
    max_capacity = '1.3.6.1.2.1.43.11.1.1.8'
    level = '1.3.6.1.2.1.43.11.1.1.9'
}

$fallos = @()
$sinContador = @()
$preparadas = 0
$enviadas = 0

foreach ($impresora in $impresoras) {
    $id = [int]$impresora.printer_id
    $ip = [string]$impresora.ip
    $port = [int]$impresora.port
    $mapeosImpresora = if ($mapeosPorImpresora.ContainsKey([string]$id)) {
        $mapeosPorImpresora[[string]$id]
    } else {
        @{ descripciones = @{}; items = [System.Collections.Generic.List[object]]::new() }
    }

    try {
        $pageCounters = @(Get-SnmpV1Walk `
            -Address $ip `
            -Port $port `
            -Community $Community `
            -RootOid $pageCounterRoot `
            -Version $SnmpVersion `
            -TimeoutMilliseconds $TimeoutMilliseconds `
            -MaximumRows 32)
        $candidateCounters = @($pageCounters | Where-Object { $_.Value -is [long] -and $_.Value -ge 0 })

        $contadorOid = $null
        if ($candidateCounters.Count -ne 1) {
            $sinContador += $id
            Write-Warning "SNMPv1 no devolvio un unico contador Printer-MIB para impresora $id (contadores=$($candidateCounters.Count))."
            $contadorPaginas = $null
        } else {
            $contadorPaginas = [long]$candidateCounters[0].Value
            $contadorOid = [string]$candidateCounters[0].Oid
            Write-Output "Contador encontrado para impresora $id mediante SNMPv1."
        }

        $supplyMaps = @{}
        foreach ($column in $supplyColumns.GetEnumerator()) {
            $values = @{}
            try {
                foreach ($row in @(Get-SnmpV1Walk `
                    -Address $ip `
                    -Port $port `
                    -Community $Community `
                    -RootOid $column.Value `
                    -Version $SnmpVersion `
                    -TimeoutMilliseconds $TimeoutMilliseconds `
                    -MaximumRows 64)) {
                    $suffix = $row.Oid.Substring($column.Value.Length + 1)
                    $values[$suffix] = $row.Value
                }
            } catch {
                Write-Output "ADVERTENCIA: Fallo SNMP para impresora $id en columna de suministros '$($column.Name)' (OID $($column.Value)): $($_.Exception.Message)"
            }
            $supplyMaps[$column.Name] = $values
        }

        $mapeosPorDescripcion = @{}
        foreach ($mapeo in $mapeosImpresora.items) {
            $mapeosPorDescripcion[[string]$mapeo.description] = $mapeo
        }
        $descripcionesEncontradas = @{}
        $niveles = [System.Collections.Generic.List[object]]::new()
        $supplyIndexes = @(
            $supplyMaps.level.Keys
            $supplyMaps.description.Keys
            $supplyMaps.max_capacity.Keys
            $supplyMaps.colorant_index.Keys
            $supplyMaps.supply_class.Keys
            $supplyMaps.supply_type.Keys
            $supplyMaps.supply_unit.Keys
        ) | Sort-Object -Unique

        foreach ($index in $supplyIndexes) {
            $description = ([string]$supplyMaps.description[$index]).TrimEnd([char[]]@(0))
            $level = $supplyMaps.level[$index]
            $maximum = $supplyMaps.max_capacity[$index]
            $colorant = $supplyMaps.colorant_index[$index]
            $class = $supplyMaps.supply_class[$index]
            $type = $supplyMaps.supply_type[$index]
            $unit = $supplyMaps.supply_unit[$index]
            Write-Output "SNMPv1 supply printer_id=$id index=$index description=$description level=$level max=$maximum unit=$unit colorant_index=$colorant class=$class type=$type"

            if (-not $mapeosPorDescripcion.ContainsKey([string]$description)) {
                continue
            }

            $descripcionesEncontradas[[string]$description] = $true
            $mapeo = $mapeosPorDescripcion[[string]$description]
            $nivelValor = [long]0
            $capacidadValor = [long]0
            if (-not [long]::TryParse([string]$level, [ref]$nivelValor)) {
                Write-Warning "No se pudo interpretar el nivel del suministro '$description' para impresora $id (level=$level, max=$maximum, unit=$unit)."
                continue
            }

            if ([int]$unit -eq 19) {
                if ($nivelValor -lt 0 -or $nivelValor -gt 100) {
                    Write-Warning "El porcentaje SNMP del suministro '$description' para impresora $id esta fuera del rango 0-100 (level=$level)."
                    continue
                }

                $nivelNormalizado = [Math]::Round(([double]$nivelValor / 100), 4)
                Write-Output "Nivel mapeado para impresora ${id}: $description = $nivelNormalizado (SNMP $nivelValor %, unidad percent)."
            } else {
                if (-not [long]::TryParse([string]$maximum, [ref]$capacidadValor) -or
                    $nivelValor -lt 0 -or $capacidadValor -le 0 -or $nivelValor -gt $capacidadValor) {
                    Write-Warning "No se pudo normalizar el suministro '$description' para impresora $id (level=$level, max=$maximum, unit=$unit)."
                    continue
                }

                $nivelNormalizado = [Math]::Round(([double]$nivelValor / [double]$capacidadValor), 4)
                Write-Output "Nivel mapeado para impresora ${id}: $description = $nivelNormalizado (SNMP $nivelValor/$capacidadValor, unidad $unit)."
            }

            $niveles.Add(@{
                tipo_consumible_id = [int]$mapeo.tipo_consumible_id
                color = [string]$mapeo.color
                nivel = $nivelNormalizado
            })
        }

        $origenLectura = 'snmp'
        $rutasHttpSuministros = @(
            $mapeosImpresora.items |
                ForEach-Object { [string]$_.http_supply_path } |
                Where-Object { $_ -ne '' } |
                Sort-Object -Unique
        )

        if ($niveles.Count -eq 0 -and $rutasHttpSuministros.Count -gt 0) {
            if ($rutasHttpSuministros.Count -gt 1) {
                Write-Output "ADVERTENCIA: La impresora $id tiene mas de una ruta HTTP de suministros; se omitio la lectura HTTP."
            } else {
                $rutaHttp = [string]$rutasHttpSuministros[0]
                try {
                    $respuestaHttp = Invoke-WebRequest `
                        -Uri "http://${ip}${rutaHttp}" `
                        -UseBasicParsing `
                        -TimeoutSec 10 `
                        -MaximumRedirection 3
                    $htmlSuministros = [string]$respuestaHttp.Content
                    $htmlSuministros = [regex]::Replace($htmlSuministros, '(?is)<script\b.*?</script>|<style\b.*?</style>', ' ')
                    $textoSuministros = [System.Net.WebUtility]::HtmlDecode($htmlSuministros)
                    $textoSuministros = [regex]::Replace($textoSuministros, '<[^>]+>', ' ')
                    $textoSuministros = [regex]::Replace($textoSuministros, '\s+', ' ').Trim()

                    foreach ($mapeo in $mapeosImpresora.items) {
                        $codigosProducto = [regex]::Matches(
                            [string]$mapeo.description,
                            '\b[A-Z]{1,3}\s?\d{3,5}[A-Z]{0,2}\b',
                            [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
                        )
                        $porcentajeEncontrado = $null
                        foreach ($codigo in $codigosProducto) {
                            $patronPorcentaje = [regex]::Escape($codigo.Value.Trim()) + '.{0,160}?(\d{1,3}(?:[.,]\d+)?)\s*%'
                            $coincidenciaPorcentaje = [regex]::Match(
                                $textoSuministros,
                                $patronPorcentaje,
                                [System.Text.RegularExpressions.RegexOptions]::IgnoreCase -bor
                                    [System.Text.RegularExpressions.RegexOptions]::Singleline
                            )
                            if ($coincidenciaPorcentaje.Success) {
                                $porcentajeEncontrado = $coincidenciaPorcentaje.Groups[1].Value.Replace(',', '.')
                                break
                            }
                        }

                        if ($null -eq $porcentajeEncontrado) {
                            continue
                        }

                        $porcentaje = 0.0
                        if (-not [double]::TryParse(
                            $porcentajeEncontrado,
                            [System.Globalization.NumberStyles]::AllowDecimalPoint,
                            [System.Globalization.CultureInfo]::InvariantCulture,
                            [ref]$porcentaje
                        ) -or $porcentaje -lt 0 -or $porcentaje -gt 100) {
                            Write-Output "ADVERTENCIA: El porcentaje HTTP para '$($mapeo.description)' de impresora $id no es valido."
                            continue
                        }

                        $nivelNormalizado = [Math]::Round(($porcentaje / 100), 4)
                        $niveles.Add(@{
                            tipo_consumible_id = [int]$mapeo.tipo_consumible_id
                            color = [string]$mapeo.color
                            nivel = $nivelNormalizado
                        })
                        $descripcionesEncontradas[[string]$mapeo.description] = $true
                        $origenLectura = 'powershell'
                        Write-Output "Nivel HTTP mapeado para impresora ${id}: $($mapeo.description) = $nivelNormalizado ($porcentaje %)."
                    }

                    if ($niveles.Count -eq 0) {
                        Write-Output "ADVERTENCIA: La pagina HTTP de suministros de impresora $id respondio, pero no se encontro un porcentaje asociado a los codigos de consumible configurados."
                    }
                } catch {
                    Write-Output "ADVERTENCIA: No se pudo leer la pagina HTTP de suministros de impresora $id ($ip$rutaHttp): $($_.Exception.Message)"
                }
            }
        }

        foreach ($descripcionMapeada in $mapeosPorDescripcion.Keys) {
            if (-not $descripcionesEncontradas.ContainsKey($descripcionMapeada)) {
                Write-Warning "No se encontro el suministro configurado '$descripcionMapeada' para impresora $id; se omitio el nivel."
            }
        }

        if ($null -eq $contadorPaginas -and $niveles.Count -eq 0) {
            Write-Warning "No hay contador ni niveles mapeados para impresora $id; no se preparó una lectura."
            continue
        }

        $observaciones = if ($origenLectura -eq 'powershell') {
            "Niveles de consumibles leidos desde HTTP ($rutaHttp)." + $(if ($null -ne $contadorOid) { " Contador obtenido mediante SNMPv$SnmpVersion, OID $contadorOid." } else { ' Contador SNMP no disponible.' })
        } elseif ($null -ne $contadorOid) {
            "SNMPv1 Printer-MIB, OID $contadorOid."
        } else {
            'SNMPv1 Printer-MIB; contador no disponible.'
        }
        $datosLectura = @{
            impresora_id = $id
            fecha_lectura = [DateTimeOffset]::UtcNow.ToString('o')
            origen = $origenLectura
            contador_paginas = $contadorPaginas
            observaciones = $observaciones
        }
        if ($niveles.Count -gt 0) {
            $datosLectura.niveles = $niveles.ToArray()
        }
        $payload = $datosLectura | ConvertTo-Json -Depth 5 -Compress

        if ($PSCmdlet.ShouldProcess("impresora_id=$id ip=$ip", 'Enviar lectura al API de monitoreo')) {
            $respuesta = Invoke-RestMethod `
                -Uri $endpoint `
                -Method Post `
                -Headers @{ Authorization = "Bearer $Token" } `
                -ContentType 'application/json' `
                -Body $payload `
                -TimeoutSec 15

            $enviadas++
            Write-Output "Lectura registrada para impresora $id; origen=$origenLectura; lectura_id=$($respuesta.id)."
        } else {
            $preparadas++
            Write-Output "WhatIf: preparada lectura SNMP para impresora $id; niveles=$($niveles.Count)."
        }
    } catch {
        $mensaje = "Fallo SNMP para impresora $id ($ip): $($_.Exception.Message)"
        $fallos += $mensaje
        Write-Warning $mensaje
    }
}

if ($fallos.Count -gt 0) {
    throw "No se completaron $($fallos.Count) consulta(s) SNMP. Revise las advertencias anteriores."
}
if (($enviadas + $preparadas) -eq 0) {
    throw 'No se encontro un contador ni niveles mapeados; no se envio ninguna lectura.'
}
if ($sinContador.Count -gt 0) {
    Write-Warning "Sin contador Printer-MIB para printer_id: $($sinContador -join ', ')."
}
