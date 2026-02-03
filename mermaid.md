flowchart TD
    %% Frontend
    subgraph FE["Frontend (React SPA)"]
        FE_UI["Пользовательский интерфейс"]
        FE_WS["WebSocket-клиент"]
    end
    
    %% Backend
    subgraph BE["Backend (Symfony)"]
        API["REST API\n(auth, CRUD, отправка сообщений)"]
        WEBHOOK["Webhook Listener\n(/webhook/telegram, /webhook/whatsapp, /webhook/max)"]
        BIZ["Бизнес-логика / Сервисы"]
        QUEUE["Очередь сообщений\n(Redis / Messenger)"]
        WORKERS["Worker'ы\n(обработка очередей)"]
        NOTIFY["Pub/Sub -> Redis\n(notify)"]
    end
    
    %% Real-time
    subgraph WS["WebSocket Server (Node.js / Socket.IO)"]
        WS_SERVER["Socket.IO сервер"]
        WS_REDIS["Redis Sub/Publish"]
    end
    
    %% Storage
    subgraph DATA["Хранилища"]
        PG["PostgreSQL\n(conversations, messages, users)"]
        REDIS["Redis\n(cache, queues, pub/sub)"]
        S3["Storage (S3)\n(вложения, файлы)"]
    end
    
    %% External
    subgraph EXT["Внешние мессенджеры / API"]
        TG["Telegram Bot API"]
        WA["WhatsApp Business API"]
        MAX["Max API"]
    end
    
    %% Потоки взаимодействия (Frontend <-> Backend)
    FE_UI -->|HTTP API GET/POST| API
    FE_WS -->|WebSocket connect / receive events| WS_SERVER
    
    %% Webhook flow (External -> Backend)
    TG -->|Webhook POST| WEBHOOK
    WA -->|Webhook POST| WEBHOOK
    MAX -->|Webhook POST| WEBHOOK
    WEBHOOK -->|enqueue IncomingMessage| QUEUE
    
    %% Queue -> Worker -> DB / External / Notify
    QUEUE -->|consume| WORKERS
    WORKERS -->|save message / update conversation| PG
    WORKERS -->|store attachments| S3
    WORKERS -->|publish event| NOTIFY
    WORKERS -->|call send API| TG
    WORKERS -->|call send API| WA
    WORKERS -->|call send API| MAX
    
    %% Notify -> WebSocket
    NOTIFY -->|publish channel new_message| REDIS
    WS_REDIS -->|subscribe new_message| WS_SERVER
    WS_SERVER -->|emit 'newMessage'| FE_WS
    
    %% Backend triggers
    API -->|enqueue OutgoingMessage| QUEUE
    API -->|manage accounts / set webhook| BIZ
    BIZ -->|setWebhook| TG
    BIZ -->|setWebhook| WA
    BIZ -->|setWebhook| MAX
    
    %% Storage bindings
    QUEUE --- REDIS
    NOTIFY --- REDIS
    
    %% Notes
    classDef ext fill:#f9f,stroke:#333,stroke-width:1px;
    class TG,WA,MAX ext;