param([string]$MysqlHome = 'D:\WAMP\bin\mysql\mysql8.4.7')
$ErrorActionPreference = 'Stop'
$credentials = Join-Path (Split-Path -Parent $PSScriptRoot) '.runtime/mysql-admin.cnf'
if (-not (Test-Path -LiteralPath $credentials)) { throw 'Chưa có thông tin instance riêng của TECHFORGE.' }
$fso = New-Object -ComObject Scripting.FileSystemObject
$shortCredentials = $fso.GetFile($credentials).ShortPath
Push-Location $MysqlHome
try {
    & (Join-Path $MysqlHome 'bin/mysqladmin.exe') "--defaults-file=$shortCredentials" shutdown
} finally { Pop-Location }
if ($LASTEXITCODE -ne 0) { throw 'Chưa dừng được MySQL. Không cưỡng bức tắt tiến trình.' }
