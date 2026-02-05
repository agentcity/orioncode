#!/bin/bash

# Цвета для вывода
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

cd android && ./gradlew clean && cd ..

echo -e "${BLUE}>>> Начало сборки Orion Aggregator...${NC}"

# 1. Сборка веб-части React
echo -e "${GREEN}1. Сборка React проекта...${NC}"
npm run build

# 2. Синхронизация кода с нативными платформами
echo -e "${GREEN}2. Синхронизация с Capacitor...${NC}"
npx cap copy
npx cap sync

# 3. Сборка Android (Генерация APK/Bundle)
if [ -d "android" ]; then
    echo -e "${GREEN}3. Сборка Android проекта (Gradle)...${NC}"
    cd android && ./gradlew assembleDebug && cd ..
    echo -e "${BLUE}APK готов: frontend/android/app/build/outputs/apk/debug/app-debug.apk${NC}"
fi

# 4. Сборка iOS и macOS (требуется Xcode)
if [ -d "ios" ]; then
    echo -e "${GREEN}4. Подготовка iOS/macOS...${NC}"
    # Открывает Xcode для финальной подписи и сборки
    # Для macOS (Catalyst) используется та же папка ios
    echo -e "${BLUE}Для финальной сборки iOS/macOS выполните: npx cap open ios${NC}"
fi

echo -e "${BLUE}>>> Сборка завершена успешно!${NC}"
