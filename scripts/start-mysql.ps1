param([string]$MysqlHome = 'D:\WAMP\bin\mysql\mysql8.4.7')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$mysqld = Join-Path $MysqlHome 'bin\mysqld.exe'
if (-not (Test-Path -LiteralPath $mysqld)) { throw 'Không thấy MySQL 8.4. Truyền -MysqlHome đúng thư mục cài đặt (xem README).' }
if (Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue) {
    Write-Output 'Cổng 3307 đã được dùng. Nếu là instance TECHFORGE, có thể chạy php artisan serve. Không khởi động chồng instance.'
    exit 0
}
# MySQL Windows cần đường dẫn ASCII và working directory không dấu.
$fso = New-Object -ComObject Scripting.FileSystemObject
$shortRoot = $fso.GetFolder($projectRoot).ShortPath.Replace('\', '/')
if ($shortRoot -match '[^\x00-\x7F]') { throw 'Windows chưa có tên thư mục ngắn. Hãy đặt bản sao dự án tại D:\TechForge trước khi khởi tạo MySQL.' }
$dataPath = "$shortRoot/.runtime/mysql"
New-Item -ItemType Directory -Path (Join-Path $projectRoot '.runtime/mysql') -Force | Out-Null
$mysqlBase = $MysqlHome.Replace('\', '/')
Push-Location $MysqlHome
try {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot '.runtime/mysql/mysql'))) {
        & $mysqld --no-defaults --initialize-insecure "--basedir=$mysqlBase" "--datadir=$dataPath" --console
        if ($LASTEXITCODE -ne 0) { throw 'Khởi tạo MySQL thất bại. Giữ dữ liệu để kiểm tra, không tự xóa.' }
    }
    $mysqlProcess = Start-Process -FilePath $mysqld -WorkingDirectory $MysqlHome -ArgumentList "--no-defaults --basedir=`"$mysqlBase`" --datadir=`"$dataPath`" --port=3307 --bind-address=127.0.0.1 --mysqlx=0 --log-error=techforge-error.log" -WindowStyle Hidden -PassThru
    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if (Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue) { $ready = $true; break }
        if ($mysqlProcess.HasExited) { break }
        Start-Sleep -Milliseconds 250
    }
    if (-not $ready) { throw 'MySQL chưa sẵn sàng. Xem .runtime/mysql/techforge-error.log; không xóa datadir.' }
    Write-Output 'MySQL 8.4 đang lắng nghe tại 127.0.0.1:3307. Nếu là lần cài đầu, chạy php scripts/setup-local-db.php.'
} finally { Pop-Location }
