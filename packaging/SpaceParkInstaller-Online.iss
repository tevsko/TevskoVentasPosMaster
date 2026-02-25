; SpacePark Inno Setup installer script - ONLINE VERSION
#define MyAppVersion "1.0.0"
#define DownloadURL "https://tevsko.com.ar/downloads/"

[Setup]
AppName=SpacePark POS
AppVersion={#MyAppVersion}
DefaultDirName=C:\SpacePark
DefaultGroupName=SpacePark
Compression=lzma2/max
SolidCompression=yes
OutputDir={#SourcePath}\\..\\out
OutputBaseFilename=SpaceParkInstaller-{#MyAppVersion}-Online
PrivilegesRequired=admin
AppPublisher=SpacePark Master
AppContact=Tel: 1135508224 - Email: tevsko@gmail.com
AppSupportPhone=1135508224
AppSupportURL=mailto:tevsko@gmail.com

[Files]
; Solo incluir archivos de la aplicación (www), NO PHPDesktop
Source: "{#SourcePath}\\build\\phpdesktop\\www\\*"; DestDir: "{app}\\www"; Flags: recursesubdirs createallsubdirs ignoreversion

; Incluir script de descarga
Source: "{#SourcePath}\\download_runtime.ps1"; DestDir: "{app}"; Flags: ignoreversion

; Incluir VC++ Redistributable (si existe)
Source: "{#SourcePath}\\redist\\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall external skipifsourcedoesntexist

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop icon"; GroupDescription: "Additional icons:"; Flags: unchecked

[Run]
; Instalar VC++ Redistributable silenciosamente (si existe)
Filename: "{tmp}\\vc_redist.x64.exe"; Parameters: "/quiet /norestart"; StatusMsg: "Instalando Visual C++ Runtime..."; Flags: waituntilterminated skipifdoesntexist

; Descargar PHPDesktop runtime
Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\\download_runtime.ps1"" ""{app}"" ""{#DownloadURL}"""; StatusMsg: "Descargando SpacePark POS Ventas..."; Flags: waituntilterminated

; Run postinstall to init sqlite and register scheduled tasks
Filename: "{app}\\postinstall.bat"; Description: "Run post-install tasks"; Flags: runhidden shellexec waituntilterminated

[Icons]
Name: "{group}\\SpacePark Pos Ventas"; Filename: "{app}\\phpdesktop-chrome.exe"; WorkingDir: "{app}"; IconFilename: "{app}\\spacepark.ico"; IconIndex: 0
Name: "{commondesktop}\\SpacePark Pos Ventas"; Filename: "{app}\\phpdesktop-chrome.exe"; Tasks: desktopicon; IconFilename: "{app}\\spacepark.ico"; IconIndex: 0

[UninstallRun]
Filename: "schtasks"; Parameters: "/Delete /TN ""SpacePark Sync Worker"" /F"; Flags: runhidden
Filename: "schtasks"; Parameters: "/Delete /TN ""SpacePark Email Worker"" /F"; Flags: runhidden

[UninstallDelete]
Type: filesandordirs; Name: "{userappdata}\\SpacePark"

