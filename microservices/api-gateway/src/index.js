require('dotenv').config();
const express = require('express');
const morgan = require('morgan');
const { createProxyMiddleware } = require('http-proxy-middleware');

const app = express();
const PORT = process.env.PORT || 8080;

const NOTIFICATION_SERVICE_URL = process.env.NOTIFICATION_SERVICE_URL || 'http://localhost:3001';
const AUDIT_SERVICE_URL        = process.env.AUDIT_SERVICE_URL        || 'http://localhost:3002';
const MAIN_APP_URL             = process.env.MAIN_APP_URL             || 'http://localhost:8000';

app.use(morgan('combined'));

// Health check del gateway
app.get('/gateway/health', (req, res) => {
    res.json({
        status: 'ok',
        service: 'api-gateway',
        timestamp: new Date().toISOString(),
        routes: {
            '/api/notifications/*': NOTIFICATION_SERVICE_URL,
            '/api/audit/*':         AUDIT_SERVICE_URL,
            '/*':                   MAIN_APP_URL,
        },
    });
});

// Proxy → notification-service
app.use('/api/notifications', createProxyMiddleware({
    target: NOTIFICATION_SERVICE_URL,
    changeOrigin: true,
    on: {
        error: (err, req, res) => {
            console.error(`[api-gateway] Error → notification-service: ${err.message}`);
            res.status(502).json({ success: false, message: 'notification-service no disponible' });
        },
    },
}));

// Proxy → audit-service
app.use('/api/audit', createProxyMiddleware({
    target: AUDIT_SERVICE_URL,
    changeOrigin: true,
    on: {
        error: (err, req, res) => {
            console.error(`[api-gateway] Error → audit-service: ${err.message}`);
            res.status(502).json({ success: false, message: 'audit-service no disponible' });
        },
    },
}));

// Proxy → aplicación Laravel principal (todo lo demás)
app.use('/', createProxyMiddleware({
    target: MAIN_APP_URL,
    changeOrigin: true,
    on: {
        error: (err, req, res) => {
            console.error(`[api-gateway] Error → app principal: ${err.message}`);
            res.status(502).json({ success: false, message: 'Aplicación principal no disponible' });
        },
    },
}));

app.listen(PORT, () => {
    console.log(`[api-gateway] Corriendo en puerto ${PORT}`);
    console.log(`  /api/notifications/* → ${NOTIFICATION_SERVICE_URL}`);
    console.log(`  /api/audit/*         → ${AUDIT_SERVICE_URL}`);
    console.log(`  /*                   → ${MAIN_APP_URL}`);
});
