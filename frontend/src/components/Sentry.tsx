import * as Sentry from "@sentry/react";

if (process.env.NODE_ENV === 'production') {
    Sentry.init({
        dsn: "https://8fe352ed4dac4eb1a89a5358107515b9@sentry.orioncode.ru/2,",
        integrations: [Sentry.browserTracingIntegration()],
        tracesSampleRate: 1.0,
    });
}