param([string]$PhpPath, [switch]$Prototype)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$php = Find-EcoTradePhp $PhpPath
& $php (Join-Path $PSScriptRoot '..\tools\check.php')
if ($LASTEXITCODE -ne 0) { throw 'Semakan gagal. Rujuk mesej di atas.' }
if ($Prototype) {
    & $php (Join-Path $PSScriptRoot '..\tools\check-prototype.php')
    if ($LASTEXITCODE -ne 0) { throw 'Semakan prototype gagal. Pastikan server PHP port 8000 berjalan dan rujuk mesej di atas.' }
}
