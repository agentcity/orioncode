FROM node:18-alpine

WORKDIR /app

# Копируем только файлы зависимостей
COPY package*.json ./

# Устанавливаем ТОЛЬКО продакшн-зависимости (быстрее и легче)
RUN npm ci --only=production --silent

# Копируем остальной код
COPY . .

ARG REDIS_URL
ENV REDIS_URL=$REDIS_URL

EXPOSE 3000

CMD ["node", "index.js"]