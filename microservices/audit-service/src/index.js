require('dotenv').config();
const express = require('express');
const mongoose = require('mongoose');
const auditRoutes = require('./routes/audits');

const app = express();
const PORT = process.env.PORT || 3002;

app.use(express.json());

mongoose.connect(process.env.MONGO_URI)
    .then(() => console.log('[audit-service] Conectado a MongoDB'))
    .catch(err => {
        console.error(`[audit-service] Error conectando a MongoDB: ${err.message}`);
        process.exit(1);
    });

app.get('/health', (req, res) => {
    res.json({ status: 'ok', service: 'audit-service', timestamp: new Date().toISOString() });
});

app.use('/api/audit', auditRoutes);

app.use((err, req, res, next) => {
    console.error(`[audit-service] Error: ${err.message}`);
    res.status(500).json({ success: false, message: 'Error interno del servicio de auditoría' });
});

app.listen(PORT, () => {
    console.log(`[audit-service] Corriendo en puerto ${PORT}`);
});
