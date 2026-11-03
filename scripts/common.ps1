function Find-EcoTradePhp {
    param([string]$PhpPath)
    if ($PhpPath) {
        if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw "PHP tidak dijumpai: $PhpPath" }
        return (Resolve-Path -LiteralPath $PhpPath).Path
    }
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { return $phpCommand.Source }
    if (Test-Path -LiteralPath 'C:\xampp\php\php.exe') { return 'C:\xampp\php\php.exe' }
    throw 'PHP tidak dijumpai. Pasang XAMPP atau gunakan parameter -PhpPath.'
}
