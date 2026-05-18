# Pull Alyve production DB from SiteGround via SSH (requires ~/.ssh/config Host alyve-sg-host).
$ErrorActionPreference = 'Stop'
$OutDir = Join-Path (Split-Path $PSScriptRoot -Parent) 'db-backups'
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$RemoteSql = "~/tmp/alyve-export-$Stamp.sql"
$LocalGz = Join-Path $OutDir "alyve-$Stamp.sql.gz"

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

Write-Host "» verifying SSH + webroot"
ssh alyve-sg-host "test -d ~/www/alyvepeptides.com/public_html" || {
    Write-Error "Remote webroot missing: ~/www/alyvepeptides.com/public_html"
}

Write-Host "» exporting on server"
ssh alyve-sg-host "cd ~/www/alyvepeptides.com/public_html && wp db export $RemoteSql && gzip -f $RemoteSql"

Write-Host "» downloading"
scp "alyve-sg-host:${RemoteSql}.gz" $LocalGz

Write-Host "» cleaning remote temp"
ssh alyve-sg-host "rm -f ${RemoteSql}.gz"

Write-Host "✓ saved $LocalGz"
