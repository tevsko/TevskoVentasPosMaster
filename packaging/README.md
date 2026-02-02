Packaging build: PHPDesktop + Inno Setup

This folder contains the helper files to prepare a Windows installer for SpacePark.

Overview
- You need a PHPDesktop runtime (Chromium + PHP build matching PHP 8.2/8.3).
- The build scripts expect to receive the PHPDesktop folder path and will copy the project web files into `phpdesktop/www`.
- Inno Setup Compiler (ISCC.exe) is required to create the .exe installer. Default path used by the helper is:
  `C:\Program Files (x86)\Inno Setup 6\ISCC.exe`

Quick steps to build installer (example):
1. Download and extract PHPDesktop (choose a Chromium+PHP build matching your PHP target).
2. Open PowerShell as Administrator and run `packaging\build_installer.ps1` and when prompted paste the path to your PHPDesktop folder.
3. The script will assemble `packaging\build\phpdesktop` and call ISCC to produce `packaging\out\SpaceParkInstaller-<version>.exe`.

If ISCC is not installed or you want to run manually:
- Run `"C:\Program Files (x86)\Inno Setup 6\ISCC.exe" packaging\SpaceParkInstaller.iss /DMyAppVersion=1.0.0`

Notes
- The installer runs `postinstall.bat` as admin to init SQLite and register Task Scheduler jobs (the installer requires admin privileges).
- For testing without building an installer, run `packaging\create_portable_zip.ps1` to produce a portable ZIP you can extract and run on a Windows VM.
- Consider code signing the final .exe using `signtool.exe` (not handled here).

Support & Troubleshooting
- If the installer fails to register tasks, ensure installer was run as Administrator and that `schtasks` is available.
- For packaging help, open an issue or ask for assistance including logs from the build script.
