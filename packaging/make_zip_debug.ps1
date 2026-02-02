Add-Type -AssemblyName System.IO.Compression.FileSystem
$src='packaging\\build\\phpdesktop'
$out='packaging\\out'
if(-not(Test-Path $out)){ New-Item -ItemType Directory -Path $out | Out-Null }
$zip=Join-Path $out 'SpacePark-portable-1.0.0.zip'
if(Test-Path $zip){Remove-Item $zip}
[System.IO.Compression.ZipFile]::CreateFromDirectory($src,$zip)
Write-Host 'Zip created:' $zip
Get-Item $zip | Select-Object FullName, Length
