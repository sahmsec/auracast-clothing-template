$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
& node (Join-Path $projectRoot 'scripts\theme-release.mjs') --package
if ($LASTEXITCODE -ne 0) { throw 'Theme packaging failed.' }
