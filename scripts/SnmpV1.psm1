function ConvertTo-SnmpBerLength {
    param([int]$Length)

    if ($Length -lt 128) {
        return ,([byte[]]@([byte]$Length))
    }

    $bytes = [System.Collections.Generic.List[byte]]::new()
    $remaining = $Length

    while ($remaining -gt 0) {
        $bytes.Insert(0, [byte]($remaining -band 0xff))
        $remaining = [math]::Floor($remaining / 256)
    }

    $result = [System.Collections.Generic.List[byte]]::new()
    $result.Add([byte](0x80 -bor $bytes.Count))
    $result.AddRange($bytes)

    return ,$result.ToArray()
}

function ConvertTo-SnmpBerTlv {
    param(
        [byte]$Tag,
        [byte[]]$Value
    )

    $result = [System.Collections.Generic.List[byte]]::new()
    $result.Add($Tag)
    $result.AddRange((ConvertTo-SnmpBerLength $Value.Length))
    $result.AddRange($Value)

    return ,$result.ToArray()
}

function ConvertTo-SnmpBerInteger {
    param([long]$Number)

    $negative = $Number -lt 0
    $remaining = [math]::Abs($Number)
    $bytes = [System.Collections.Generic.List[byte]]::new()

    do {
        $bytes.Insert(0, [byte]($remaining -band 0xff))
        $remaining = [math]::Floor($remaining / 256)
    } while ($remaining -gt 0)

    if ($negative) {
        for ($index = 0; $index -lt $bytes.Count; $index++) {
            $bytes[$index] = [byte](0xff - $bytes[$index])
        }

        for ($index = $bytes.Count - 1; $index -ge 0; $index--) {
            if ($bytes[$index] -lt 0xff) {
                $bytes[$index]++
                break
            }

            $bytes[$index] = 0
        }

        if (($bytes[0] -band 0x80) -eq 0) {
            $bytes.Insert(0, [byte]0xff)
        }
    } elseif (($bytes[0] -band 0x80) -ne 0) {
        $bytes.Insert(0, [byte]0)
    }

    return ,(ConvertTo-SnmpBerTlv 0x02 $bytes.ToArray())
}

function ConvertTo-SnmpBerOid {
    param([string]$Oid)

    $arcs = @($Oid.Trim('.').Split('.') | ForEach-Object { [long]$_ })
    if ($arcs.Count -lt 2 -or $arcs[0] -gt 2 -or $arcs[1] -lt 0 -or ($arcs[0] -lt 2 -and $arcs[1] -gt 39) -or ($arcs | Where-Object { $_ -lt 0 }).Count -gt 0) {
        throw "OID SNMP no valido: $Oid"
    }

    $values = [System.Collections.Generic.List[long]]::new()
    $values.Add((40 * $arcs[0]) + $arcs[1])
    for ($index = 2; $index -lt $arcs.Count; $index++) {
        $values.Add($arcs[$index])
    }

    $encoded = [System.Collections.Generic.List[byte]]::new()
    foreach ($value in $values) {
        $octets = [System.Collections.Generic.List[byte]]::new()
        $remaining = $value

        do {
            $octets.Insert(0, [byte]($remaining -band 0x7f))
            $remaining = [math]::Floor($remaining / 128)
        } while ($remaining -gt 0)

        for ($index = 0; $index -lt ($octets.Count - 1); $index++) {
            $octets[$index] = [byte]($octets[$index] -bor 0x80)
        }

        $encoded.AddRange($octets)
    }

    return ,(ConvertTo-SnmpBerTlv 0x06 $encoded.ToArray())
}

function Read-SnmpBerTlv {
    param(
        [byte[]]$Data,
        [ref]$Offset
    )

    if ($Offset.Value -ge $Data.Length) {
        throw 'Respuesta SNMP incompleta.'
    }

    $tag = $Data[$Offset.Value]
    $Offset.Value++
    if ($Offset.Value -ge $Data.Length) {
        throw 'Longitud BER SNMP ausente.'
    }

    $firstLength = [int]$Data[$Offset.Value]
    $Offset.Value++

    if (($firstLength -band 0x80) -eq 0) {
        $length = $firstLength
    } else {
        $lengthBytes = $firstLength -band 0x7f
        if ($lengthBytes -eq 0 -or $lengthBytes -gt 4 -or ($Offset.Value + $lengthBytes) -gt $Data.Length) {
            throw 'Longitud BER SNMP no valida.'
        }

        $length = 0
        for ($index = 0; $index -lt $lengthBytes; $index++) {
            $length = ($length * 256) + [int]$Data[$Offset.Value]
            $Offset.Value++
        }
    }

    if ($length -lt 0 -or ($Offset.Value + $length) -gt $Data.Length) {
        throw 'Contenido BER SNMP incompleto.'
    }

    $value = New-Object byte[] $length
    if ($length -gt 0) {
        [Array]::Copy($Data, $Offset.Value, $value, 0, $length)
    }
    $Offset.Value += $length

    return [pscustomobject]@{
        Tag = [byte]$tag
        Value = [byte[]]$value
    }
}

function ConvertFrom-SnmpBerInteger {
    param(
        [byte[]]$Value,
        [switch]$Unsigned
    )

    if ($Value.Length -eq 0 -or $Value.Length -gt 8) {
        throw 'Entero SNMP fuera del rango admitido.'
    }

    $number = [long]0
    if (-not $Unsigned -and ($Value[0] -band 0x80) -ne 0) {
        $number = -1
    }

    foreach ($octet in $Value) {
        $number = ($number * 256) + [int]$octet
    }

    return $number
}

function ConvertFrom-SnmpBerOid {
    param([byte[]]$Value)

    if ($Value.Length -eq 0) {
        throw 'OID SNMP vacio.'
    }

    $subidentifiers = [System.Collections.Generic.List[long]]::new()
    $current = [long]0

    foreach ($octet in $Value) {
        $current = ($current * 128) + ($octet -band 0x7f)
        if (($octet -band 0x80) -eq 0) {
            $subidentifiers.Add($current)
            $current = 0
        }
    }

    if (($Value[$Value.Length - 1] -band 0x80) -ne 0 -or $subidentifiers.Count -eq 0) {
        throw 'OID SNMP incompleto.'
    }

    $first = $subidentifiers[0]
    if ($first -lt 40) {
        $arcs = [System.Collections.Generic.List[long]]::new()
        $arcs.Add(0)
        $arcs.Add($first)
    } elseif ($first -lt 80) {
        $arcs = [System.Collections.Generic.List[long]]::new()
        $arcs.Add(1)
        $arcs.Add($first - 40)
    } else {
        $arcs = [System.Collections.Generic.List[long]]::new()
        $arcs.Add(2)
        $arcs.Add($first - 80)
    }

    for ($index = 1; $index -lt $subidentifiers.Count; $index++) {
        $arcs.Add($subidentifiers[$index])
    }

    return ($arcs -join '.')
}

function ConvertFrom-SnmpValue {
    param($Tlv)

    switch ([int]$Tlv.Tag) {
        0x02 { return ConvertFrom-SnmpBerInteger $Tlv.Value }
        0x41 { return ConvertFrom-SnmpBerInteger $Tlv.Value -Unsigned }
        0x42 { return ConvertFrom-SnmpBerInteger $Tlv.Value -Unsigned }
        0x43 { return ConvertFrom-SnmpBerInteger $Tlv.Value -Unsigned }
        0x46 { return ConvertFrom-SnmpBerInteger $Tlv.Value -Unsigned }
        0x04 { return [System.Text.Encoding]::ASCII.GetString($Tlv.Value) }
        0x05 { return $null }
        0x06 { return ConvertFrom-SnmpBerOid $Tlv.Value }
        0x40 { return ([System.Net.IPAddress]::new([byte[]]$Tlv.Value)).ToString() }
        default { return [System.BitConverter]::ToString($Tlv.Value) }
    }
}

function New-SnmpV1GetNextPacket {
    param(
        [string]$Community,
        [string]$Oid,
        [int]$RequestId,
        [ValidateSet('1', '2c')][string]$Version = '1'
    )

    $bindingContent = [System.Collections.Generic.List[byte]]::new()
    $bindingContent.AddRange((ConvertTo-SnmpBerOid $Oid))
    $bindingContent.AddRange((ConvertTo-SnmpBerTlv 0x05 ([byte[]]@())))
    $binding = ConvertTo-SnmpBerTlv 0x30 $bindingContent.ToArray()
    $bindings = ConvertTo-SnmpBerTlv 0x30 $binding

    $pduContent = [System.Collections.Generic.List[byte]]::new()
    $pduContent.AddRange((ConvertTo-SnmpBerInteger $RequestId))
    $pduContent.AddRange((ConvertTo-SnmpBerInteger 0))
    $pduContent.AddRange((ConvertTo-SnmpBerInteger 0))
    $pduContent.AddRange($bindings)
    $pdu = ConvertTo-SnmpBerTlv 0xA1 $pduContent.ToArray()

    $messageContent = [System.Collections.Generic.List[byte]]::new()
    $versionNumber = if ($Version -eq '2c') { 1 } else { 0 }
    $messageContent.AddRange((ConvertTo-SnmpBerInteger $versionNumber))
    $messageContent.AddRange((ConvertTo-SnmpBerTlv 0x04 ([System.Text.Encoding]::ASCII.GetBytes($Community))))
    $messageContent.AddRange($pdu)

    return ,(ConvertTo-SnmpBerTlv 0x30 $messageContent.ToArray())
}

function ConvertFrom-SnmpV1Response {
    param(
        [byte[]]$Data,
        [string]$Community,
        [int]$RequestId,
        [ValidateSet('1', '2c')][string]$Version = '1'
    )

    $offset = 0
    $message = Read-SnmpBerTlv $Data ([ref]$offset)
    if ($message.Tag -ne 0x30 -or $offset -ne $Data.Length) {
        throw 'Respuesta SNMP no valida.'
    }

    $messageOffset = 0
    $versionTlv = Read-SnmpBerTlv $message.Value ([ref]$messageOffset)
    $communityValue = Read-SnmpBerTlv $message.Value ([ref]$messageOffset)
    $pdu = Read-SnmpBerTlv $message.Value ([ref]$messageOffset)

    $expectedVersion = if ($Version -eq '2c') { 1 } else { 0 }
    if ((ConvertFrom-SnmpBerInteger $versionTlv.Value) -ne $expectedVersion -or $pdu.Tag -ne 0xA2) {
        throw "La respuesta no corresponde a SNMPv$Version."
    }

    if ([System.Text.Encoding]::ASCII.GetString($communityValue.Value) -cne $Community) {
        throw 'La comunidad de la respuesta SNMP no coincide.'
    }

    $pduOffset = 0
    $responseId = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
    $errorStatus = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
    $errorIndex = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
    $bindings = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)

    if ((ConvertFrom-SnmpBerInteger $responseId.Value) -ne $RequestId -or $bindings.Tag -ne 0x30) {
        throw 'La respuesta SNMP no coincide con la solicitud.'
    }

    $status = ConvertFrom-SnmpBerInteger $errorStatus.Value
    if ($status -eq 2) {
        return $null
    }
    if ($status -ne 0) {
        throw "El agente SNMP devolvio error $status en la variable $(ConvertFrom-SnmpBerInteger $errorIndex.Value)."
    }

    $bindingsOffset = 0
    $binding = Read-SnmpBerTlv $bindings.Value ([ref]$bindingsOffset)
    $bindingOffset = 0
    $oid = Read-SnmpBerTlv $binding.Value ([ref]$bindingOffset)
    $value = Read-SnmpBerTlv $binding.Value ([ref]$bindingOffset)

    if ($binding.Tag -ne 0x30 -or $oid.Tag -ne 0x06) {
        throw 'La respuesta SNMP no contiene una variable valida.'
    }
    return [pscustomobject]@{
        Oid = ConvertFrom-SnmpBerOid $oid.Value
        Tag = [byte]$value.Tag
        Value = ConvertFrom-SnmpValue $value
    }
}

function Invoke-SnmpV1GetNext {
    param(
        [string]$Address,
        [int]$Port = 161,
        [string]$Community,
        [string]$Oid,
        [ValidateSet('1', '2c')][string]$Version = '1',
        [int]$TimeoutMilliseconds = 2000
    )

    $ip = [System.Net.IPAddress]::Parse($Address)
    if ($ip.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork) {
        throw 'SNMP requiere una direccion IPv4 en esta version del recolector.'
    }

    $requestId = Get-Random -Minimum 1 -Maximum 2147483647
    $packet = New-SnmpV1GetNextPacket -Community $Community -Oid $Oid -RequestId $requestId -Version $Version
    $client = [System.Net.Sockets.UdpClient]::new()
    $client.Client.ReceiveTimeout = $TimeoutMilliseconds

    try {
        $destination = [System.Net.IPEndPoint]::new($ip, $Port)
        [void]$client.Send($packet, $packet.Length, $destination)
        $remote = [System.Net.IPEndPoint]::new([System.Net.IPAddress]::Any, 0)
        $response = $client.Receive([ref]$remote)

        if (-not $remote.Address.Equals($ip) -or $remote.Port -ne $Port) {
            throw 'La respuesta SNMP vino de una direccion o puerto inesperado.'
        }

        return ConvertFrom-SnmpV1Response -Data $response -Community $Community -RequestId $requestId -Version $Version
    } catch [System.Net.Sockets.SocketException] {
        throw "No hubo respuesta SNMP de $Address`:$Port dentro de $TimeoutMilliseconds ms."
    } finally {
        $client.Dispose()
    }
}

function Get-SnmpV1Walk {
    param(
        [string]$Address,
        [int]$Port = 161,
        [string]$Community,
        [string]$RootOid,
        [ValidateSet('1', '2c')][string]$Version = '1',
        [int]$TimeoutMilliseconds = 2000,
        [int]$MaximumRows = 64
    )

    $cursor = $RootOid
    $vistos = @{}

    for ($index = 0; $index -lt $MaximumRows; $index++) {
        $respuesta = Invoke-SnmpV1GetNext `
            -Address $Address `
            -Port $Port `
            -Community $Community `
            -Oid $cursor `
            -Version $Version `
            -TimeoutMilliseconds $TimeoutMilliseconds

        if ($null -eq $respuesta -or -not $respuesta.Oid.StartsWith("$RootOid.", [System.StringComparison]::Ordinal)) {
            return
        }

        if ($vistos.ContainsKey($respuesta.Oid)) {
            throw "El agente SNMP repitio el OID $($respuesta.Oid)."
        }

        $vistos[$respuesta.Oid] = $true
        Write-Output $respuesta
        $cursor = $respuesta.Oid
    }

    throw "La caminata SNMP excedio el limite de $MaximumRows filas para $RootOid."
}

Export-ModuleMember -Function Get-SnmpV1Walk
