# frontend/Dockerfile
FROM node:20-alpine

WORKDIR /app

COPY package*.json ./

ARG REACT_APP_API_URL
ARG REACT_APP_WS_URL
ENV REACT_APP_API_URL=$REACT_APP_API_URL
ENV REACT_APP_WS_URL=$REACT_APP_WS_URL

RUN npm install

COPY . .

EXPOSE 3000

CMD ["npm", "start"]