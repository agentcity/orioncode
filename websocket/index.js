const { Server } = require("socket.io");
const Redis = require("ioredis");

const redisUrl = process.env.REDIS_URL || "redis://orion_redis:6379";

const redis = new Redis(redisUrl); // Создаем новый клиент для записи (SET/GET)
const subscriber = new Redis(redisUrl); // Этот только для подписки (SUBSCRIBE)
const io = new Server({ cors: { origin: "*" } });
const getTime = () => `[${new Date().toLocaleTimeString('ru-RU')}]`;

subscriber.on("connect", () => console.log("✅ Redis: Connected"));

io.on("connection", (socket) => {
    socket.on("authenticate", async (userId) => {
        socket.userId = userId;
        //  Записываем статус в Redis (с TTL 1 час, чтобы не висел вечно если сервер упадет)
        await redis.set(`user:status:${userId}`, "online", "EX", 3600);

        io.emit("newMessage", {
            event: "userStatusChanged",
            userId,
            status: "online"
        });
        console.log(`${getTime()}: 📡 User ${userId} is now ONLINE`);
    });
    socket.on("heartbeat", async (data) => {
        if (data.userId) {
            // Продлеваем жизнь статусу в Redis
            await redis.expire(`user:status:${data.userId}`, 3600);
        }
    });

    socket.on("join_conversation", (id) => {
        socket.join(`conversation:${id}`);
        console.log(`${getTime()}: 👤 Socket ${socket.id} joined conversation:${id}`);
    });
    socket.on("typing", (data) => {
        // Шлем всем в комнату, КРОМЕ отправителя (через broadcast или to)
        socket.to(`conversation:${data.conversationId}`).emit("newMessage", {
            event: "typing",
            conversationId: data.conversationId,
            userId: data.userId
        });
    });
    socket.on("disconnect", async () => {
        const userId = socket.userId;
        const lastSeen = new Date().toISOString();

        // 1. Ставим статус offline в Redis и сохраняем время
        await redis.set(`user:status:${userId}`, "offline");
        await redis.set(`user:lastSeen:${userId}`, lastSeen);

        // 2. Уведомляем всех
        io.emit("newMessage", {
            event: "userStatusChanged",
            userId,
            status: "offline",
            lastSeen
        });
        console.log(`${getTime()}: ❌ User ${userId} is now OFFLINE`);
    });
});

// Слушаем ОБА канала на всякий случай
subscriber.subscribe("chat_messages", "new_message_channel");

subscriber.on("message", (channel, message) => {
    try {
        const data = JSON.parse(message);
        console.log("${getTime()}: 📥 Redis Data:", data);

        // Пытаемся найти ID беседы везде, где он может быть
        const convId = data.conversationId || (data.payload && data.payload.conversationId);
        // Пытаемся найти само сообщение
        const msg = data.payload || data;

        if (convId) {
            io.to(`conversation:${convId}`).emit("newMessage", msg);
            console.log(`${getTime()}: 🚀 Sent to conversation:${convId}`);
        }
    } catch (e) { console.error("${getTime()}: ❌ Error:", e.message); }
});

io.listen(3000);
