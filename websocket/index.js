const { Server } = require("socket.io");
const Redis = require("ioredis");

const redisUrl = process.env.REDIS_URL || "redis://orion_redis:6379";
const subscriber = new Redis(redisUrl);
const io = new Server({ cors: { origin: "*" } });

subscriber.on("connect", () => console.log("✅ Redis: Connected"));

io.on("connection", (socket) => {
    socket.on("authenticate", (userId) => {
        socket.userId = userId;
        // Рассылаем всем: "Я в сети!"
        io.emit("newMessage", { event: "userStatusChanged", userId, status: true });
    });
    socket.on("join_conversation", (id) => {
        socket.join(`conversation:${id}`);
        console.log(`👤 Socket ${socket.id} joined conversation:${id}`);
    });
    socket.on("typing", (data) => {
        // Шлем всем в комнату, КРОМЕ отправителя (через broadcast или to)
        socket.to(`conversation:${data.conversationId}`).emit("newMessage", {
            event: "typing",
            conversationId: data.conversationId,
            userId: data.userId
        });
    });
    socket.on("disconnect", () => {
        console.log(`❌ Socket disconnected: ${socket.id}`);
    });
});

// Слушаем ОБА канала на всякий случай
subscriber.subscribe("chat_messages", "new_message_channel");

subscriber.on("message", (channel, message) => {
    try {
        const data = JSON.parse(message);
        console.log("📥 Redis Data:", data);

        // Пытаемся найти ID беседы везде, где он может быть
        const convId = data.conversationId || (data.payload && data.payload.conversationId);
        // Пытаемся найти само сообщение
        const msg = data.payload || data;

        if (convId) {
            io.to(`conversation:${convId}`).emit("newMessage", msg);
            console.log(`🚀 Sent to conversation:${convId}`);
        }
    } catch (e) { console.error("❌ Error:", e.message); }
});

io.listen(3000);
