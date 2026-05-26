const mongoose = require('mongoose');

const auditLogSchema = new mongoose.Schema({
    user_id: { type: String, default: null },
    user_name: { type: String, default: 'Sistema' },
    user_email: { type: String, default: null },
    action: { type: String, required: true },
    description: { type: String, default: '' },
    ip_address: { type: String, default: null },
    user_agent: { type: String, default: null },
}, {
    timestamps: { createdAt: 'created_at', updatedAt: false },
    collection: 'audit_logs',
});

auditLogSchema.index({ action: 1 });
auditLogSchema.index({ created_at: -1 });
auditLogSchema.index({ user_name: 1 });

module.exports = mongoose.model('AuditLog', auditLogSchema);
