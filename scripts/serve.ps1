param([string]$PhpPath, [ValidateRange(1024, 65535)][int]$Port = 8000)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$php = Find-EcoTradePhp $PhpPath
$public = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\public')).Path
Write-Host "EcoTrade: http://127.0.0.1:$Port/"
Write-Host 'Biarkan terminal ini terbuka. Tekan Ctrl+C untuk hentikan server PHP.'
& $php -S "127.0.0.1:$Port" -t $public
if ($LASTEXITCODE -ne 0) { throw 'Server PHP berhenti dengan ralat. Semak port atau mesej di atas.' }
