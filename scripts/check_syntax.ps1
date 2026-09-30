$php = "C:\php\php.exe"
$base = "c:\Users\HP\Desktop\loyiha"
$allOk = $true

$files = Get-ChildItem $base -Recurse -Filter "*.php" | Where-Object {
    $_.FullName -notmatch "\\scripts\\"
}

foreach ($f in $files) {
    $result = & $php -l $f.FullName 2>&1 | Out-String
    if ($result -notmatch "No syntax errors") {
        Write-Host "XATO: $($f.Name)"
        Write-Host $result
        $allOk = $false
    }
}

if ($allOk) {
    Write-Host "BARCHA FAYLLAR OK"
}
