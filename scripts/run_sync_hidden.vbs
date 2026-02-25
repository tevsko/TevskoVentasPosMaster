Set WshShell = CreateObject("WScript.Shell")
WshShell.Run chr(34) & "scripts\run_sync.bat" & chr(34), 0
Set WshShell = Nothing
