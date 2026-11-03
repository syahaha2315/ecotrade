param([string]$PhpPath, [string]$AdminUser = 'root', [switch]$AskAdminPassword)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$php = Find-EcoTradePhp $PhpPath
$previousUser = $env:ECOTRADE_ADMIN_USER
$previousPassword = $env:ECOTRADE_ADMIN_PASSWORD
try {
    $env:ECOTRADE_ADMIN_USER = $AdminUser
    if ($AskAdminPassword) {
        $secret = Read-Host 'Password admin MySQL (input disembunyikan)' -AsSecureString
        $credential = New-Object System.Management.Automation.PSCredential($AdminUser, $secret)
        $env:ECOTRADE_ADMIN_PASSWORD = $credential.GetNetworkCredential().Password
    }
    & $php (Join-Path $PSScriptRoot '..\tools\setup.php')
    if ($LASTEXITCODE -ne 0) { throw 'Setup database gagal. Rujuk mesej di atas.' }
} finally {
    $env:ECOTRADE_ADMIN_USER = $previousUser
    $env:ECOTRADE_ADMIN_PASSWORD = $previousPassword
}
