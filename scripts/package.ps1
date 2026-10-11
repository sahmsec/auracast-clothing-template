$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$themeRoot = Join-Path $projectRoot 'auracast'
$archivePath = Join-Path $projectRoot 'dist\auracast.zip'
if (-not (Test-Path -LiteralPath (Join-Path $themeRoot 'style.css'))) { throw 'Theme source is missing.' }
New-Item -ItemType Directory -Path (Join-Path $projectRoot 'dist') -Force | Out-Null
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$stream = [IO.File]::Open($archivePath,[IO.FileMode]::Create,[IO.FileAccess]::Write)
$archive = [IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create,$false)
try {
    foreach ($file in Get-ChildItem -LiteralPath $themeRoot -Recurse -File) {
        $entry = 'auracast/' + $file.FullName.Substring($themeRoot.Length + 1).Replace('\','/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$file.FullName,$entry,[IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $archive.Dispose(); $stream.Dispose() }
Get-Item -LiteralPath $archivePath | Select-Object FullName,Length
