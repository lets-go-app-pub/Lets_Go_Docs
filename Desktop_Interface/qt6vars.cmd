REM Set up Microsoft Visual Studio 2022
CALL "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvarsall.bat" x86_amd64
SET _ROOT=D:\Qt\6.2.4\Src
SET PATH=%_ROOT%\qtbase\bin;%PATH%
SET _ROOT=