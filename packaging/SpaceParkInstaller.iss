; SpacePark Inno Setup installer script
#define MyAppVersion "1.0.0"

[Setup]
AppName=SpacePark POS
AppVersion={#MyAppVersion}
DefaultDirName={pf}\SpacePark
DefaultGroupName=SpacePark
Compression=none
SolidCompression=no
OutputDir={#SourcePath}\..\out
OutputBaseFilename=SpaceParkInstaller-{#MyAppVersion}
PrivilegesRequired=admin

[Files]
; Expect build\phpdesktop to contain the PHPDesktop runtime + www content (built by build_installer.ps1)
Source: "{#SourcePath}\\build\\phpdesktop\\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop icon"; GroupDescription: "Additional icons:"; Flags: unchecked

[Icons]
Name: "{group}\SpacePark"; Filename: "{app}\phpdesktop-chrome.exe"; WorkingDir: "{app}"; IconFilename: "{app}\phpdesktop-chrome.exe"
Name: "{commondesktop}\SpacePark"; Filename: "{app}\phpdesktop-chrome.exe"; Tasks: desktopicon

[Run]
; Run postinstall to init sqlite and register scheduled tasks (installer runs as admin)
Filename: "{app}\postinstall.bat"; Description: "Run post-install tasks"; Flags: runhidden shellexec waituntilterminated

[UninstallRun]
; Attempt to remove scheduled tasks during uninstall
Filename: "schtasks"; Parameters: "/Delete /TN ""SpacePark Sync Worker"" /F"; Flags: runhidden
Filename: "schtasks"; Parameters: "/Delete /TN ""SpacePark Email Worker"" /F"; Flags: runhidden
