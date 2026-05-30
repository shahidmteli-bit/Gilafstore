$source = "c:\xampp\htdocs\Gilaf Ecommerce website"
$zipPath = "$env:USERPROFILE\OneDrive\Desktop\gilafstore_upload.zip"

# Remove old zip if exists
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

# Create temp staging folder
$staging = "$env:TEMP\gilafstore_staging"
if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Path $staging | Out-Null

# Folders to exclude
$excludeDirs = @('.git', '.windsurf-backups', '.vscode', 'backups', 'cache', 'logs', 'node_modules', 'archive')

# File patterns to exclude
$excludeFiles = @('composer.phar', 'temp_backup_index.php', 'SIMPLE_FIX.txt', 'create_hostinger_zip.ps1')

# Get all items
$items = Get-ChildItem -Path $source -Recurse -Force

foreach ($item in $items) {
    $relativePath = $item.FullName.Substring($source.Length + 1)
    
    # Skip excluded directories
    $skip = $false
    foreach ($dir in $excludeDirs) {
        if ($relativePath -like "$dir\*" -or $relativePath -eq $dir) {
            $skip = $true
            break
        }
    }
    if ($skip) { continue }
    
    # Skip specific files
    if ($item.PSIsContainer -eq $false) {
        # Skip excluded filenames
        if ($excludeFiles -contains $item.Name) { continue }
        
        # Skip debug/test files
        if ($item.Name -match '^debug_') { continue }
        if ($item.Name -match '^test_') { continue }
        
        # Skip .md files in root only
        if ($item.Name -match '\.md$' -and $item.Directory.FullName -eq $source) { continue }
        
        # Skip .sql files in root only
        if ($item.Name -match '\.sql$' -and $item.Directory.FullName -eq $source) { continue }
        
        # Skip .backup_ files
        if ($item.Name -match '\.backup_') { continue }
    }
    
    $destPath = Join-Path $staging $relativePath
    
    if ($item.PSIsContainer) {
        if (-not (Test-Path $destPath)) {
            New-Item -ItemType Directory -Path $destPath -Force | Out-Null
        }
    } else {
        $destDir = Split-Path $destPath -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Copy-Item $item.FullName -Destination $destPath -Force
    }
}

# Create ZIP
Compress-Archive -Path "$staging\*" -DestinationPath $zipPath -Force

# Cleanup staging
Remove-Item $staging -Recurse -Force

$zipSize = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host ""
Write-Host "====================================" -ForegroundColor Green
Write-Host " ZIP CREATED SUCCESSFULLY!" -ForegroundColor Green
Write-Host "====================================" -ForegroundColor Green
Write-Host "Location: $zipPath"
Write-Host "Size: ${zipSize} MB"
Write-Host ""
Write-Host "NEXT: Upload this ZIP to Hostinger File Manager -> public_html/ -> Extract"
