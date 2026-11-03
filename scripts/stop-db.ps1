param([string]$MySqlBin = 'C:\xampp\mysql\bin', [ValidateRange(1024, 65535)][int]$Port = 3307)
$ErrorActionPreference = 'Stop'
$client = Join-Path $MySqlBin 'mysqladmin.exe'
if (-not (Test-Path -LiteralPath $client)) { throw 'mysqladmin.exe tidak dijumpai. Semak -MySqlBin.' }
& $client --no-defaults --host=127.0.0.1 "--port=$Port" --user=root --connect-timeout=5 shutdown
if ($LASTEXITCODE -ne 0) { throw 'Shutdown gagal. Semak port dan sama ada database sedang berjalan.' }
Write-Host 'Database EcoTrade sudah dihentikan dengan kemas. Data kekal tersimpan.'
