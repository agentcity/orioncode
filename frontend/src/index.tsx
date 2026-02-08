import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';
import reportWebVitals from './reportWebVitals';
import * as Sentry from "@sentry/react";

if (process.env.NODE_ENV === 'production') {
    Sentry.init({
        dsn: "http://sentry.orioncode.ru/api/2/security/?glitchtip_key=8fe352ed4dac4eb1a89a5358107515b9",
        integrations: [Sentry.browserTracingIntegration()],
        tracesSampleRate: 1.0,
    });
}


const root = ReactDOM.createRoot(
  document.getElementById('root') as HTMLElement
);
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
reportWebVitals();
