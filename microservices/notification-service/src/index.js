require('dotenv').config();
const express = require('express');
const notificationRoutes = require('./routes/notifications');

const app = express();
const PORT = process.env.PORT || 3001;

app.use(express.json());

app.get('/health', (req, res) => {
    res.json({ status: 'ok', service: 'notification-service', timestamp: new Date().toISOString() });
});

app.use('/api/notifications', notificationRoutes);

app.use((err, req, res, next) => {
    console.error(`[notification-service] Error: ${err.message}`);
    res.status(500).json({ success: false, message: 'Error interno del servicio de notificaciones' });
});

app.listen(PORT, () => {
    console.log(`[notification-service] Corriendo en puerto ${PORT}`);
});
