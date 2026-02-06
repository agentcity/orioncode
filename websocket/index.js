const { Server } = require("socket.io");
const Redis = require("ioredis");

const redisUrl = process.env.REDIS_URL || "redis://orion_redis:6379";
const subscriber = new Redis(redisUrl);
const io = new Server({ cors: { origin: "*" } });

const activeUsers = new Map();

io.on("connection", (socket) => {
    socket.on("authenticate", (userId) => {
        if (!userId) return;
        socket.userId = userId;
        socket.join(`user:${userId}`);

        if (!activeUsers.has(userId)) {
            activeUsers.set(userId, new Set());
            io.emit("newMessage", { event: "userStatusChanged", userId, status: true });
        }
        activeUsers.get(userId).add(socket.id);
        console.log(`User ${userId} authenticated`);
    });

    socket.on("join_conversation", (conversationId) => {
        socket.join(`conversation:${conversationId}`);
        console.log(`Socket ${socket.id} joined conversation:${conversationId}`);
    });

    socket.on("disconnect", () => {
        if (socket.userId && activeUsers.has(socket.userId)) {
            const sockets = activeUsers.get(socket.userId);
            sockets.delete(socket.id);
            if (sockets.size === 0) {
                activeUsers.delete(socket.userId);
                io.emit("newMessage", { event: "userStatusChanged", userId: socket.userId, status: false });
            }
        }
    });
});

subscriber.subscribe("chat_messages");
// 1. Подписываемся на правильный канал
subscriber.subscribe("chat_messages", (err, count) => {
    if (err) console.error("❌ Redis subscribe error:", err);
    console.log(`📡 Subscribed to chat_messages. Channels active: ${count}`);
});

// 2. Обрабатываем сообщение
subscriber.on("message", (channel, message) => {
    console.log("📥 Received from Redis:", message);
    try {
        const data = JSON.parse(message);

        // У тебя в логе данные приходят в корне или в payload.
        // Если PHP шлет {"conversationId": "...", "payload": {...}}
        const conversationId = data.conversationId;
        const msgPayload = data.payload;

        if (conversationId) {
            // Шлем в комнату с префиксом conversation: (как у тебя в join_conversation)
            io.to(`conversation:${conversationId}`).emit("newMessage", msgPayload);
            console.log(`🚀 Broadcasted to conversation:${conversationId}`);
        }
    } catch (e) {
        console.error("❌ Parse error:", e);
    }
});

io.listen(3000);