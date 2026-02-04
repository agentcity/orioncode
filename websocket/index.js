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

subscriber.subscribe("new_message_channel");
subscriber.on("message", (channel, message) => {
    try {
        const payload = JSON.parse(message);
        console.log("Redis Message:", payload);

        // КРИТИЧНО: Шлем всем в комнату беседы
        if (payload.conversationId) {
            io.to(`conversation:${payload.conversationId}`).emit("newMessage", payload);
        }

        // Шлем персонально оператору для обновления списка чатов
        if (payload.assignedToId) {
            io.to(`user:${payload.assignedToId}`).emit("newMessage", payload);
        }
    } catch (e) { console.error(e); }
});

io.listen(3000);