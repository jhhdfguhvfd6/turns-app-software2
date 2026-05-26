const express = require('express');
const nodemailer = require('nodemailer');
const { body, validationResult } = require('express-validator');

const router = express.Router();

function authMiddleware(req, res, next) {
    const secret = req.headers['x-service-secret'];
    if (secret !== process.env.SERVICE_SECRET) {
        return res.status(401).json({ success: false, message: 'No autorizado' });
    }
    next();
}

function createTransporter() {
    return nodemailer.createTransport({
        host: process.env.MAIL_HOST,
        port: parseInt(process.env.MAIL_PORT),
        secure: false,
        auth: {
            user: process.env.MAIL_USER,
            pass: process.env.MAIL_PASS,
        },
    });
}

// POST /api/notifications/send-pin
// Envía un PIN de acceso al email del usuario
router.post('/send-pin',
    authMiddleware,
    [
        body('email').isEmail().withMessage('Email inválido'),
        body('pin').isLength({ min: 6, max: 6 }).withMessage('PIN debe tener 6 dígitos'),
        body('user_name').notEmpty().withMessage('Nombre de usuario requerido'),
    ],
    async (req, res) => {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(422).json({ success: false, errors: errors.array() });
        }

        const { email, pin, user_name } = req.body;

        const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background-color: #0033A0; padding: 24px; text-align: center; }
                .header h1 { color: white; margin: 0; font-size: 22px; }
                .header span { color: #FFD100; }
                .body { padding: 32px; }
                .pin-box { background: #f0f4ff; border: 2px solid #0033A0; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
                .pin-box .pin { font-size: 40px; font-weight: bold; color: #0033A0; letter-spacing: 8px; }
                .footer { background: #f9f9f9; padding: 16px; text-align: center; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Banco de <span>Bogotá</span></h1>
                </div>
                <div class="body">
                    <p>Hola <strong>${user_name}</strong>,</p>
                    <p>Tu código de acceso al sistema de turnos es:</p>
                    <div class="pin-box">
                        <div class="pin">${pin}</div>
                    </div>
                    <p>Este código expira en <strong>3 minutos</strong>.</p>
                    <p>Si no solicitaste este código, ignora este mensaje.</p>
                </div>
                <div class="footer">
                    <p>© ${new Date().getFullYear()} Banco de Bogotá. Este es un mensaje automático.</p>
                </div>
            </div>
        </body>
        </html>`;

        try {
            const transporter = createTransporter();
            await transporter.sendMail({
                from: `"${process.env.MAIL_FROM_NAME}" <${process.env.MAIL_FROM_ADDRESS}>`,
                to: email,
                subject: 'Tu Código de Acceso - Banco de Bogotá',
                html: htmlContent,
            });

            console.log(`[notification-service] PIN enviado a ${email}`);
            res.json({ success: true, message: `PIN enviado a ${email}` });
        } catch (error) {
            console.error(`[notification-service] Error enviando PIN: ${error.message}`);
            res.status(500).json({ success: false, message: 'Error al enviar el correo', error: error.message });
        }
    }
);

// POST /api/notifications/send-turn-confirmation
// Notifica al usuario que su turno fue creado
router.post('/send-turn-confirmation',
    authMiddleware,
    [
        body('email').isEmail().withMessage('Email inválido'),
        body('user_name').notEmpty().withMessage('Nombre requerido'),
        body('turn_code').notEmpty().withMessage('Código de turno requerido'),
        body('service_name').notEmpty().withMessage('Nombre del servicio requerido'),
    ],
    async (req, res) => {
        const errors = validationResult(req);
        if (!errors.isEmpty()) {
            return res.status(422).json({ success: false, errors: errors.array() });
        }

        const { email, user_name, turn_code, service_name } = req.body;

        const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background-color: #0033A0; padding: 24px; text-align: center; }
                .header h1 { color: white; margin: 0; font-size: 22px; }
                .header span { color: #FFD100; }
                .body { padding: 32px; }
                .turn-box { background: #f0f4ff; border: 2px solid #0033A0; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
                .turn-box .code { font-size: 40px; font-weight: bold; color: #0033A0; letter-spacing: 4px; }
                .footer { background: #f9f9f9; padding: 16px; text-align: center; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Banco de <span>Bogotá</span></h1>
                </div>
                <div class="body">
                    <p>Hola <strong>${user_name}</strong>,</p>
                    <p>Tu turno para <strong>${service_name}</strong> fue registrado exitosamente.</p>
                    <div class="turn-box">
                        <p style="margin:0;color:#555;">Tu número de turno</p>
                        <div class="code">${turn_code}</div>
                    </div>
                    <p>Por favor, permanece atento a la pantalla de turnos en tu sucursal.</p>
                </div>
                <div class="footer">
                    <p>© ${new Date().getFullYear()} Banco de Bogotá. Este es un mensaje automático.</p>
                </div>
            </div>
        </body>
        </html>`;

        try {
            const transporter = createTransporter();
            await transporter.sendMail({
                from: `"${process.env.MAIL_FROM_NAME}" <${process.env.MAIL_FROM_ADDRESS}>`,
                to: email,
                subject: `Turno ${turn_code} registrado - Banco de Bogotá`,
                html: htmlContent,
            });

            console.log(`[notification-service] Confirmación de turno ${turn_code} enviada a ${email}`);
            res.json({ success: true, message: `Confirmación enviada a ${email}` });
        } catch (error) {
            console.error(`[notification-service] Error: ${error.message}`);
            res.status(500).json({ success: false, message: 'Error al enviar el correo', error: error.message });
        }
    }
);

module.exports = router;
