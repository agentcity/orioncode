// websocket/index.js
const { Server } = require("socket.io");
const Redis = require("ioredis");

// Поддержка переменной REDIS_URL из окружения
const redisUrl = process.env.REDIS_URL || "redis://orion_redis:6379";
const subscriber = new Redis(redisUrl);

const io = new Server({
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

io.on("connection", (socket) => {
    console.log("WS connected", socket.id);

    socket.on("authenticate", (token) => {
        // TODO: validate token and join user rooms
        // e.g. socket.join(`user:${userId}`)
        console.log("Auth token received (TODO validate):", token);
    });

    socket.on("disconnect", () => {
        console.log("WS disconnected", socket.id);
    });
});

subscriber.subscribe("new_message_channel", (err) => {
    if (err) console.error("Redis subscribe error:", err);
});

subscriber.on("message", (channel, message) => {
    try {
        const payload = JSON.parse(message);
        if (payload.conversationId) {
            io.to(`conversation:${payload.conversationId}`).emit("newMessage", payload);
        } else {
            io.emit("newMessage", payload);
        }
    } catch (e) {
        console.error("Failed to parse message:", e);
    }
});

io.listen(3000);
console.log("WebSocket server listening on :3000");