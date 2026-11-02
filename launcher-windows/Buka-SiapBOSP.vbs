' Peluncur senyap SiapBOSP — membuka aplikasi tanpa menampilkan jendela command prompt (CMD).
' Berkas ini menjalankan launcher-windows\SiapBOSP.bat dalam mode tersembunyi (mode 0).
Set WshShell = CreateObject("WScript.Shell")
Set Fso = CreateObject("Scripting.FileSystemObject")

' Pindah ke folder induk aplikasi
DirInduk = Fso.GetParentFolderName(Fso.GetParentFolderName(WScript.ScriptFullName))
WshShell.CurrentDirectory = DirInduk

' Jalankan SiapBOSP.bat dengan window style 0 (tersembunyi total)
BatPath = Fso.BuildPath(Fso.BuildPath(DirInduk, "launcher-windows"), "SiapBOSP.bat")
WshShell.Run "cmd.exe /c """ & BatPath & """", 0, False
