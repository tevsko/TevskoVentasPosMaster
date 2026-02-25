; SpacePark Inno Setup installer script
#define MyAppVersion "1.0.0"

[Setup]
AppName=SpacePark POS
AppVersion={#MyAppVersion}
DefaultDirName=C:\SpacePark
DefaultGroupName=SpacePark
Compression=lzma2/max
SolidCompression=yes
OutputDir={#SourcePath}\..\out
OutputBaseFilename=SpaceParkInstaller-{#MyAppVersion}-Offline
PrivilegesRequired=admin
AppPublisher=SpacePark Master
AppContact=Tel: 1135508224 - Email: tevsko@gmail.com
AppSupportPhone=1135508224
AppSupportURL=mailto:tevsko@gmail.com

[Files]
; Expect build\phpdesktop to contain the PHPDesktop runtime + www content (built by build_installer.ps1)
Source: "{#SourcePath}\\build\\phpdesktop\\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

; NUEVO: Incluir VC++ Redistributable (si existe)
; Si no existe, el instalador continuará sin error
Source: "{#SourcePath}\\redist\\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall external skipifsourcedoesntexist

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop icon"; GroupDescription: "Additional icons:"; Flags: unchecked

[Icons]
Name: "{group}\SpacePark Pos Ventas"; Filename: "{app}\phpdesktop-chrome.exe"; WorkingDir: "{app}"; IconFilename: "{app}\spacepark.ico"; IconIndex: 0
Name: "{commondesktop}\SpacePark Pos Ventas"; Filename: "{app}\phpdesktop-chrome.exe"; Tasks: desktopicon; IconFilename: "{app}\spacepark.ico"; IconIndex: 0

[Run]
; NUEVO: Instalar VC++ Redistributable silenciosamente (si existe)
Filename: "{tmp}\\vc_redist.x64.exe"; Parameters: "/quiet /norestart"; StatusMsg: "Instalando Visual C++ Runtime..."; Flags: waituntilterminated skipifdoesntexist

; Run postinstall to init sqlite and register scheduled tasks (installer runs as admin)
Filename: "{app}\\postinstall.bat"; Description: "Run post-install tasks"; Flags: runhidden shellexec waituntilterminated

[UninstallRun]
; Attempt to remove scheduled tasks during uninstall
Filename: "schtasks"; Parameters: "/Delete /TN ""SpacePark Sync Worker"" /F"; Flags: runhidden
Filename: "schtasks"; Parameters: "/Delete /TN ""SpacePark Email Worker"" /F"; Flags: runhidden

[UninstallDelete]
; Eliminar datos de %APPDATA% al desinstalar
Type: filesandordirs; Name: "{userappdata}\SpacePark"
