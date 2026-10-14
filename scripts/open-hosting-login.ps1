param([int]$DebugPort = 9416)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$profilePath = Join-Path $projectRoot '.local\hosting-login-profile'
New-Item -ItemType Directory -Path $profilePath -Force | Out-Null
$browserPath = Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'
if (-not (Test-Path -LiteralPath $browserPath)) { throw 'Microsoft Edge is required for the hosting login window.' }
Start-Process -FilePath $browserPath -ArgumentList @(
    ('--user-data-dir="' + $profilePath + '"'),
    ('--remote-debugging-port=' + $DebugPort),
    '--remote-debugging-address=127.0.0.1', '--no-first-run',
    'https://premium364.web-hosting.com:2083/'
) -WindowStyle Normal
Write-Output 'Sign in to cPanel in the dedicated Edge window. Keep it open during deployment setup.'
