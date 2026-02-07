FROM node:20-alpine AS base
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .

# Стадия для РАЗРАБОТКИ
FROM base AS dev
EXPOSE 3000
CMD ["npm", "start"]

# Стадия для ПРОДАКШЕНА
FROM base AS prod
ARG REACT_APP_API_URL
ARG REACT_APP_WS_URL
ENV REACT_APP_API_URL=$REACT_APP_API_URL
ENV REACT_APP_WS_URL=$REACT_APP_WS_URL

# 1. Собираем проект (Vite создаст папку /app/dist)
RUN npm run build

# 2. Устанавливаем сервер глобально
RUN npm install -g serve

# 3. ПРОВЕРКА: Если билд упал, докер не пойдет дальше
RUN ls -la /app/dist

EXPOSE 3000

# 4. Явно указываем путь к папке с флагом -s (Single Page Application)
ENTRYPOINT ["serve", "-s", "dist", "-l", "3000"]