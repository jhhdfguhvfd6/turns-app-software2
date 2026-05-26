const express = require('express');
const { body, query, validationResult } = require('express-validator');
const AuditLog = require('../models/AuditLog');

const router = express.Router();

function authMiddleware(req, res, next) {
    const secret = req.headers['x-service-secret'];
    if (secret !== process.env.SERVICE_SECRET) {
        return res.status(401).json({ success: false, message: 'No autorizado' });
    }
    next();
}

// POST /api/audit/logs
// Registra una nueva entrada de auditoría
router.post('/logs',
    authMiddleware,
    [
        body('action').notEmpty().withMessage('Acción requerida'),
    ],
    async (req, res) => {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(422).json({ success: false, errors: errors.array() });
        }

        const { user_id, user_name, user_email, action, description, ip_address, user_agent } = req.body;

        try {
            const log = await AuditLog.create({
                user_id: user_id || null,
                user_name: user_name || 'Sistema',
                user_email: user_email || null,
                action,
                description: description || '',
                ip_address: ip_address || null,
                user_agent: user_agent || null,
            });

            console.log(`[audit-service] Log registrado: ${action} por ${user_name || 'Sistema'}`);
            res.status(201).json({ success: true, data: log });
        } catch (error) {
            console.error(`[audit-service] Error al registrar log: ${error.message}`);
            res.status(500).json({ success: false, message: 'Error al registrar la auditoría' });
        }
    }
);

// GET /api/audit/logs
// Obtiene logs con filtros y paginación
router.get('/logs',
    authMiddleware,
    async (req, res) => {
        try {
            const { action, user_name, date_from, date_to, page = 1, per_page = 20 } = req.query;

            const filter = {};

            if (action) filter.action = action;
            if (user_name) filter.user_name = { $regex: user_name, $options: 'i' };
            if (date_from || date_to) {
                filter.created_at = {};
                if (date_from) filter.created_at.$gte = new Date(date_from);
                if (date_to) {
                    const end = new Date(date_to);
                    end.setHours(23, 59, 59, 999);
                    filter.created_at.$lte = end;
                }
            }

            const skip = (parseInt(page) - 1) * parseInt(per_page);
            const total = await AuditLog.countDocuments(filter);
            const logs = await AuditLog.find(filter)
                .sort({ created_at: -1 })
                .skip(skip)
                .limit(parseInt(per_page));

            const actions = await AuditLog.distinct('action');

            res.json({
                success: true,
                data: logs,
                actions,
                pagination: {
                    total,
                    per_page: parseInt(per_page),
                    current_page: parseInt(page),
                    last_page: Math.ceil(total / parseInt(per_page)),
                },
            });
        } catch (error) {
            console.error(`[audit-service] Error al obtener logs: ${error.message}`);
            res.status(500).json({ success: false, message: 'Error al obtener los registros' });
        }
    }
);

// GET /api/audit/stats
// Devuelve estadísticas para las gráficas del panel admin
router.get('/stats',
    authMiddleware,
    async (req, res) => {
        try {
            // Acciones por tipo (top 10)
            const actionCounts = await AuditLog.aggregate([
                { $group: { _id: '$action', count: { $sum: 1 } } },
                { $sort: { count: -1 } },
                { $limit: 10 },
            ]);

            // Actividad diaria (últimos 7 días)
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

            const dailyActivity = await AuditLog.aggregate([
                { $match: { created_at: { $gte: sevenDaysAgo } } },
                {
                    $group: {
                        _id: { $dateToString: { format: '%Y-%m-%d', date: '$created_at' } },
                        count: { $sum: 1 },
                    },
                },
                { $sort: { _id: 1 } },
            ]);

            // Usuarios más activos (top 5)
            const topUsers = await AuditLog.aggregate([
                { $group: { _id: '$user_name', count: { $sum: 1 } } },
                { $sort: { count: -1 } },
                { $limit: 5 },
            ]);

            res.json({
                success: true,
                data: {
                    action_counts: actionCounts,
                    daily_activity: dailyActivity,
                    top_users: topUsers,
                },
            });
        } catch (error) {
            console.error(`[audit-service] Error al obtener stats: ${error.message}`);
            res.status(500).json({ success: false, message: 'Error al obtener estadísticas' });
        }
    }
);

module.exports = router;
