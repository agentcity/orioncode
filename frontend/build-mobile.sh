#!/bin/bash

# Цвета для вывода
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'


echo -e "${BLUE}>>> Начало сборки Orion Aggregator...${NC}"

# 1. Сборка веб-части React
echo -e "${GREEN}1. Сборка React проекта...${NC}"
npm run build

# 0. Очистка старых билдов
echo -e "${GREEN}0. Очистка...${NC}"
cd mobile
[ -d "android" ] && (cd android && ./gradlew clean && cd ..)
[ -d "electron" ] && rm -rf electron/out electron/dist
cd ..


# 2. Синхронизация кода с нативными платформами
echo -e "${GREEN}2. Синхронизация с Capacitor...${NC}"
npx cap copy android --project mobile/android
npx cap copy ios --project mobile/ios

# АНДРОИД: Копируем билд в папку ресурсов Android
mkdir -p mobile/android/app/src/main/assets/public
rm -rf mobile/android/app/src/main/assets/public/*
cp -R build/* mobile/android/app/src/main/assets/public/


echo -e "${GREEN}Подготовка файлов для Electron...${NC}"
mkdir -p mobile/electron/app # Создаем папку, если её нет
cp -R build/* mobile/electron/app/ # Копируем твой React-билд внутрь Электрона

npx cap sync android
npx cap sync ios

# 3. Сборка Android (Генерация APK/Bundle)
if [ -d "mobile/android" ]; then
    echo -e "${GREEN}3. Сборка Android проекта (Gradle)...${NC}"
    cd mobile/android && ./gradlew assembleDebug && cd ../..
    echo -e "${BLUE}APK готов: frontend/mobile/android/app/build/outputs/apk/debug/app-debug.apk${NC}"
fi

# 4. Сборка macOS (Electron)
if [ -d "mobile/electron" ]; then
    echo -e "${GREEN}4. Сборка macOS приложения...${NC}"
    cd mobile/electron
    # Устанавливаем зависимости внутри электрона, если их нет
    [ ! -d "node_modules" ] && npm install
    # Сборка под текущую ОС (Mac)
    rm -rf redist dist
    npm run electron:make
    # Раскомментируй ниже, когда понадобятся другие ОС:
    # npx electron-builder --linux --mac --win
    cd ../..
    echo -e "${BLUE}✅ macOS build готов в папке: frontend/electron/dist/${NC}"
fi

# 4. Сборка iOS (требуется Xcode)
if [ -d "mobile/ios" ]; then
    echo -e "${GREEN}4. Подготовка iOS/macOS...${NC}"
    # Открывает Xcode для финальной подписи и сборки
    # Для macOS (Catalyst) используется та же папка ios
    echo -e "${BLUE}Для финальной сборки iOS/macOS выполните: npx cap open ios${NC}"
fi

echo -e "${BLUE}>>> Сборка завершена успешно!${NC}"
