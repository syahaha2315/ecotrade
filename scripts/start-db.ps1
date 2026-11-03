param([string]$MySqlBin = 'C:\xampp\mysql\bin', [ValidateRange(1024, 65535)][int]$Port = 3307)
$ErrorActionPreference = 'Stop'
$server = Join-Path $MySqlBin 'mysqld.exe'
$installer = Join-Path $MySqlBin 'mysql_install_db.exe'
if (-not (Test-Path -LiteralPath $server) -or -not (Test-Path -LiteralPath $installer)) {
    throw 'Binary MariaDB tidak dijumpai. Semak parameter -MySqlBin.'
}
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$data = Join-Path $projectRoot '.local\mysql'
$ini = Join-Path $data 'my.ini'
$client = Join-Path $MySqlBin 'mysql.exe'
if (-not (Test-Path -LiteralPath $client)) { throw 'mysql.exe tidak dijumpai. Semak parameter -MySqlBin.' }
$previousErrorAction = $ErrorActionPreference
try {
    $ErrorActionPreference = 'Continue'
    $runningData = & $client --no-defaults --protocol=tcp --host=127.0.0.1 "--port=$Port" --user=root --connect-timeout=3 --batch --skip-column-names '--execute=SELECT @@datadir' 2>$null
    $probeExitCode = $LASTEXITCODE
} finally {
    $ErrorActionPreference = $previousErrorAction
}
if ($probeExitCode -eq 0) {
    $runningPath = [System.IO.Path]::GetFullPath(($runningData -join '').Trim()).TrimEnd('\', '/')
    $expectedPath = [System.IO.Path]::GetFullPath($data).TrimEnd('\', '/')
    if ($runningPath -ne $expectedPath) {
        throw "Port $Port digunakan oleh database lain. Gunakan port lain; jangan start dua instance pada port sama."
    }
    Write-Host "Database EcoTrade sudah berjalan pada 127.0.0.1:$Port. Tidak perlu start sekali lagi."
    Write-Host 'Teruskan dengan scripts/check.ps1 atau buka http://127.0.0.1:8000/ jika PHP sudah berjalan.'
    return
}
$connection = New-Object System.Net.Sockets.TcpClient
try {
    $pending = $connection.BeginConnect('127.0.0.1', $Port, $null, $null)
    if ($pending.AsyncWaitHandle.WaitOne(1000)) {
        try { $connection.EndConnect($pending) } catch { }
    }
    if ($connection.Connected) {
        throw "Port $Port sedang digunakan tetapi database tidak dapat disahkan. Semak proses atau password admin sebelum start."
    }
} finally {
    $connection.Close()
}
if (-not (Test-Path -LiteralPath (Join-Path $data 'mysql'))) {
    if ((Test-Path -LiteralPath $data) -and (Get-ChildItem -LiteralPath $data -Force | Select-Object -First 1)) {
        throw 'Folder .local/mysql sudah mengandungi fail tetapi belum lengkap. Semak fail itu sebelum cuba initialize lagi.'
    }
    New-Item -ItemType Directory -Path (Join-Path $projectRoot '.local') -Force | Out-Null
    Write-Host 'Menyediakan data directory EcoTrade (sekali sahaja)...'
    & $installer "--datadir=$data" "--port=$Port"
    if ($LASTEXITCODE -ne 0) { throw 'Initialize database gagal. Data sedia ada tidak dipadam.' }
}
if (-not (Test-Path -LiteralPath $ini)) { throw 'Fail .local/mysql/my.ini tidak dijumpai.' }
Write-Host "Database EcoTrade: 127.0.0.1:$Port"
Write-Host 'Biarkan terminal ini terbuka. Untuk shutdown dengan kemas, jalankan scripts/stop-db.ps1 di terminal lain.'
Write-Host 'Data disimpan dalam .local/mysql. Jangan padam folder itu.'
& $server "--defaults-file=$ini" "--port=$Port" --bind-address=127.0.0.1 --console
if ($LASTEXITCODE -ne 0) { throw 'Database berhenti dengan ralat. Semak log di atas; jangan padam fail database.' }
