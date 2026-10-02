$ErrorActionPreference = 'Stop'
$courseId = '2c4eb6499843c263'
$bucket = 'northstar-scorm-courses'
$courseRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\data\courses\$courseId")).Path
$wrangler = Join-Path $PSScriptRoot 'node_modules\wrangler\bin\wrangler.js'
$files = Get-ChildItem -LiteralPath $courseRoot -Recurse -File

function Get-MimeType([string]$extension) {
    switch ($extension.ToLowerInvariant()) {
        '.html' { 'text/html; charset=utf-8' }
        '.htm' { 'text/html; charset=utf-8' }
        '.js' { 'text/javascript; charset=utf-8' }
        '.css' { 'text/css; charset=utf-8' }
        '.json' { 'application/json' }
        '.xml' { 'application/xml' }
        '.svg' { 'image/svg+xml' }
        '.png' { 'image/png' }
        '.jpg' { 'image/jpeg' }
        '.jpeg' { 'image/jpeg' }
        '.gif' { 'image/gif' }
        '.mp4' { 'video/mp4' }
        '.webm' { 'video/webm' }
        '.mp3' { 'audio/mpeg' }
        '.woff' { 'font/woff' }
        '.woff2' { 'font/woff2' }
        '.ttf' { 'font/ttf' }
        default { 'application/octet-stream' }
    }
}

foreach ($file in $files) {
    $relative = $file.FullName.Substring($courseRoot.Length + 1).Replace('\', '/')
    $object = "$bucket/courses/$courseId/$relative"
    Write-Host "Uploading $relative"
    & node $wrangler r2 object put $object --remote --file $file.FullName --content-type (Get-MimeType $file.Extension)
    if ($LASTEXITCODE -ne 0) { throw "Upload failed for $relative (exit $LASTEXITCODE)." }
}

Write-Host "Uploaded $($files.Count) files for course $courseId."
