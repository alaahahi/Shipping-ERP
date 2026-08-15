@echo off
echo 🔄 Removing old build...
rmdir /s /q build
mkdir build

echo 🚀 Running build...
npm run build

echo 📂 Copying files from public to build...
xcopy /E /I /Y public\* build\

echo ✅ Done!
pause