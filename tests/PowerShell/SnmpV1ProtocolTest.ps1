$ErrorActionPreference = 'Stop'

$modulePath = (Resolve-Path (Join-Path $PSScriptRoot '..\..\scripts\SnmpV1.psm1')).Path
Import-Module -Name $modulePath -Force
$module = Get-Module SnmpV1

& $module {
    $oid = '1.3.6.1.2.1.43.10.2.1.4'
    $encoded = ConvertTo-SnmpBerOid $oid
    $offset = 0
    $tlv = Read-SnmpBerTlv $encoded ([ref]$offset)
    if ((ConvertFrom-SnmpBerOid $tlv.Value) -ne $oid) {
        throw 'OID BER round-trip failed.'
    }
}

$probe = [System.Net.Sockets.UdpClient]::new(0)
$port = ([System.Net.IPEndPoint]$probe.Client.LocalEndPoint).Port
$probe.Dispose()
$job = Start-Job -ArgumentList $port, $modulePath -ScriptBlock {
    param($Port, $ModulePath)

    Import-Module -Name $ModulePath -Force
    $module = Get-Module SnmpV1
    $udp = [System.Net.Sockets.UdpClient]::new([int]$Port)

    try {
        for ($count = 0; $count -lt 2; $count++) {
            $remote = [System.Net.IPEndPoint]::new([System.Net.IPAddress]::Any, 0)
            $request = $udp.Receive([ref]$remote)
            $response = & $module {
                param($Data)

                $offset = 0
                $message = Read-SnmpBerTlv $Data ([ref]$offset)
                $messageOffset = 0
                $version = Read-SnmpBerTlv $message.Value ([ref]$messageOffset)
                $versionNumber = ConvertFrom-SnmpBerInteger $version.Value
                if ($versionNumber -ne 0) {
                    throw 'The collector did not send an SNMPv1 request.'
                }
                $community = Read-SnmpBerTlv $message.Value ([ref]$messageOffset)
                $pdu = Read-SnmpBerTlv $message.Value ([ref]$messageOffset)
                if ($pdu.Tag -ne 0xA1) {
                    throw 'The collector sent a non-read-only SNMP PDU.'
                }

                $pduOffset = 0
                $requestId = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
                $null = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
                $null = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
                $bindings = Read-SnmpBerTlv $pdu.Value ([ref]$pduOffset)
                $bindingsOffset = 0
                $binding = Read-SnmpBerTlv $bindings.Value ([ref]$bindingsOffset)
                $bindingOffset = 0
                $requestedOidTlv = Read-SnmpBerTlv $binding.Value ([ref]$bindingOffset)
                $requestedOid = ConvertFrom-SnmpBerOid $requestedOidTlv.Value
                $root = '1.3.6.1.2.1.43.10.2.1.4'

                if ($requestedOid -eq $root) {
                    $status = 0
                    $responseOid = "$root.1.1"
                    $value = ConvertTo-SnmpBerTlv 0x41 ([byte[]]@(0x30, 0x39))
                } else {
                    $status = 2
                    $responseOid = $requestedOid
                    $value = ConvertTo-SnmpBerTlv 0x05 ([byte[]]@())
                }

                $bindingContent = [System.Collections.Generic.List[byte]]::new()
                $bindingContent.AddRange((ConvertTo-SnmpBerOid $responseOid))
                $bindingContent.AddRange($value)
                $responseBinding = ConvertTo-SnmpBerTlv 0x30 $bindingContent.ToArray()
                $responseBindings = ConvertTo-SnmpBerTlv 0x30 $responseBinding
                $responsePduContent = [System.Collections.Generic.List[byte]]::new()
                $responsePduContent.AddRange((ConvertTo-SnmpBerInteger (ConvertFrom-SnmpBerInteger $requestId.Value)))
                $responsePduContent.AddRange((ConvertTo-SnmpBerInteger $status))
                $responsePduContent.AddRange((ConvertTo-SnmpBerInteger $(if ($status -eq 0) { 0 } else { 1 })))
                $responsePduContent.AddRange($responseBindings)
                $responsePdu = ConvertTo-SnmpBerTlv 0xA2 $responsePduContent.ToArray()
                $responseMessage = [System.Collections.Generic.List[byte]]::new()
                $responseMessage.AddRange((ConvertTo-SnmpBerInteger $versionNumber))
                $responseMessage.AddRange((ConvertTo-SnmpBerTlv 0x04 $community.Value))
                $responseMessage.AddRange($responsePdu)

                return ,(ConvertTo-SnmpBerTlv 0x30 $responseMessage.ToArray())
            } $request

            [void]$udp.Send($response, $response.Length, $remote)
        }
    } finally {
        $udp.Dispose()
    }
}

try {
    Start-Sleep -Milliseconds 250
    $rows = @(Get-SnmpV1Walk `
        -Address '127.0.0.1' `
        -Port $port `
        -Community 'test' `
        -RootOid '1.3.6.1.2.1.43.10.2.1.4' `
        -TimeoutMilliseconds 2000 `
        -MaximumRows 5)

    if ($rows.Count -ne 1 -or $rows[0].Value -ne 12345) {
        throw 'SNMPv1 GET-NEXT walk did not decode the expected Counter32 value.'
    }

    $job | Wait-Job -Timeout 5 | Out-Null
    if ($job.State -ne 'Completed') {
        throw 'The local mock SNMP agent did not complete.'
    }
    Receive-Job -Job $job -ErrorAction Stop | Out-Null

    Write-Output 'SNMPv1 protocol tests passed.'
} finally {
    Stop-Job -Job $job -ErrorAction SilentlyContinue
    Remove-Job -Job $job -Force -ErrorAction SilentlyContinue
}
