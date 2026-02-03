Структура Frontend-приложения

```
frontend/
├── public/
├── src/
│  ├── api/         # Axios-клиенты для взаимодействия с Backend API
│  │  ├── auth.ts
│  │  ├── accounts.ts
│  │  └── conversations.ts
│  ├── components/      # Переиспользуемые UI-компоненты
│  │  ├── Header.tsx
│  │  ├── ChatList.tsx
│  │  ├── MessageInput.tsx
│  │  └── MessageItem.tsx
│  ├── hooks/        # Кастомные React-хуки (например, useAuth, useWebSocket)
│  │  ├── useAuth.ts
│  │  └── useWebSocket.ts
│  ├── pages/        # Страницы приложения
│  │  ├── LoginPage.tsx
│  │  ├── DashboardPage.tsx
│  │  ├── ChatPage.tsx
│  │  └── SettingsPage.tsx
│  ├── context/       # React Context для глобального состояния (AuthContext, ChatContext)
│  │  ├── AuthContext.tsx
│  │  └── ChatContext.tsx
│  ├── App.tsx        # Основной компонент приложения, маршрутизация
│  ├── index.tsx       # Точка входа
│  └── types.ts       # TypeScript-определения

```