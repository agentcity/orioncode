import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  "appId": "app.orioncode.ru",
  "appName": "Orion",
  "webDir": "build",
  "server": {
    "url": "https://app.orioncode.ru",
    "cleartext": true,
    "allowNavigation": ["api.orioncode.ru", "ws.orioncode.ru"]
  },
  "plugins": {
    "SplashScreen": {
      "launchShowDuration": 2000,
      "backgroundColor": "#1976d2",
      "showSpinner": true,
      "androidScaleType": "CENTER_CROP",
      "splashFullScreen": true,
      "splashImmersive": true
    }
  }
};
export default config;
