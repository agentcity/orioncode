import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'app.orioncode.ru',
  appName: 'Orion',
  webDir: 'build'
  server: {
    // Укажи локальный IP твоего компьютера, чтобы телефон видел бэкенд
    url: 'http://192.168.1.13:3001',
    cleartext: true
  }
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000, // Показываем 2 секунды
      backgroundColor: "#1976d2", // Фирменный синий
      showSpinner: true,
      androidScaleType: "CENTER_CROP",
      splashFullScreen: true,
      splashImmersive: true,
    },
  },

};

export default config;
