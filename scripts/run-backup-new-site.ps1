$ErrorActionPreference = 'Stop'

if (-not $env:PN_BACKUP_IDENTITY) {
    $env:PN_BACKUP_IDENTITY = 'C:\Users\mtomb\AppData\Local\PNBackup\site-backup-identity.txt'
}
if (-not $env:PN_BACKUP_DEST) {
    $env:PN_BACKUP_DEST = 'C:\Users\mtomb\OneDrive\Резервные копии серверов\VDSina\Новый сайт'
}
if (-not $env:PN_BACKUP_SSH_KEY) {
    $env:PN_BACKUP_SSH_KEY = 'C:\Users\mtomb\.ssh\jora_codex_ed25519'
}

$bash = 'C:\Program Files\Git\bin\bash.exe'
$script = Join-Path $PSScriptRoot 'backup-new-site.sh'
$log = 'C:\Users\mtomb\AppData\Local\PNBackup\backup-task.log'
try {
    Add-Content -LiteralPath $log -Value ("START " + (Get-Date -Format o))
    if (-not (Test-Path -LiteralPath $bash) -or -not (Test-Path -LiteralPath $script)) {
        throw 'Backup runtime or script is missing.'
    }
    & $bash $script 2>&1 | ForEach-Object { Add-Content -LiteralPath $log -Value ([string]$_) }
    if ($LASTEXITCODE -ne 0) {
        throw "Encrypted backup failed with exit code $LASTEXITCODE."
    }
    Add-Content -LiteralPath $log -Value ("SUCCESS " + (Get-Date -Format o))
} catch {
    Add-Content -LiteralPath $log -Value ("ERROR " + (Get-Date -Format o) + ' ' + $_.Exception.Message)
    throw
}
