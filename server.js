require('dotenv').config();

const express = require('express');
const path = require('path');
const multer = require('multer');
const mysql = require('mysql2/promise');
const nodemailer = require('nodemailer');

const app = express();
const upload = multer();
const port = Number(process.env.PORT || 3000);

app.use(express.urlencoded({ extended: true }));
app.use(express.json());

function getEmail(value, fallback) {
  const direct = value || fallback || '';
  return String(direct).trim();
}

function normaliseBoolean(value) {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') return ['1', 'true', 'yes', 'ja', 'on'].includes(value.toLowerCase());
  return Boolean(value);
}

function deriveSource(req, body) {
  const rawSource = body.source || body.page || body['source-name'] || body.source_name || '';
  if (rawSource) return String(rawSource);

  const referer = req.headers.referer || '';
  const match = referer.match(/(?:\/|index\.html|)([A-Za-z0-9-]+)\/index\.html|(?:\/|)([A-Za-z0-9-]+)\.html$/);
  if (match) {
    const found = match[1] || match[2] || '';
    if (found && found !== 'index') return found;
  }

  return 'website';
}

function getDbConfig() {
  return {
    host: process.env.DB_HOST || 'localhost',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'aiesec_leads',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    charset: 'utf8mb4'
  };
}

let pool;

function getPool() {
  if (!pool) {
    pool = mysql.createPool(getDbConfig());
  }
  return pool;
}

async function insertSubmission(data) {
  const connection = await getPool().getConnection();
  try {
    const sql = `
      INSERT INTO form_submissions (
        source,
        company,
        website,
        first_name,
        last_name,
        full_name,
        email,
        phone,
        product_interest,
        city,
        source_channel,
        interest,
        profile,
        message,
        consent_contact,
        consent_privacy,
        raw_payload
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    const values = [
      data.source,
      data.company,
      data.website,
      data.first_name,
      data.last_name,
      data.full_name,
      data.email,
      data.phone,
      data.product_interest,
      data.city,
      data.source_channel,
      data.interest,
      data.profile,
      data.message,
      data.consent_contact ? 1 : 0,
      data.consent_privacy ? 1 : 0,
      JSON.stringify(data.raw_payload)
    ];

    const [result] = await connection.execute(sql, values);
    return result.insertId;
  } finally {
    connection.release();
  }
}

async function sendNotificationMail(data) {
  const smtpHost = process.env.SMTP_HOST;
  const smtpUser = process.env.SMTP_USER;
  const smtpPass = process.env.SMTP_PASS;

  if (!smtpHost || !smtpUser || !smtpPass) {
    console.log('SMTP not configured. Skipping email notification.');
    return;
  }

  const transporter = nodemailer.createTransport({
    host: smtpHost,
    port: Number(process.env.SMTP_PORT || 587),
    secure: String(process.env.SMTP_SECURE || 'false').toLowerCase() === 'true',
    auth: {
      user: smtpUser,
      pass: smtpPass
    }
  });

  const subjectLine = `Nuevo lead: ${data.company || data.full_name || 'AIESEC formulario'}`;
  const lines = [
    `Fuente: ${data.source}`,
    `Empresa: ${data.company || '-'}`,
    `Nombre: ${data.full_name || '-'}`,
    `Email: ${data.email || '-'}`,
    `Teléfono: ${data.phone || '-'}`,
    `Producto / interés: ${data.product_interest || data.interest || '-'}`,
    `Ciudad: ${data.city || '-'}`,
    `Cómo nos encontró: ${data.source_channel || '-'}`,
    `Website: ${data.website || '-'}`,
    `Perfil: ${data.profile || '-'}`,
    `Mensaje: ${data.message || '-'}`,
    `Consentimiento contacto: ${data.consent_contact ? 'Sí' : 'No'}`,
    `Consentimiento privacidad: ${data.consent_privacy ? 'Sí' : 'No'}`
  ];

  await transporter.sendMail({
    from: process.env.SMTP_FROM || smtpUser,
    to: process.env.SMTP_TO || smtpUser,
    subject: subjectLine,
    text: lines.join('\n')
  });
}

app.get('/health', (req, res) => {
  res.json({ ok: true, timestamp: new Date().toISOString() });
});

app.post('/api/forms', upload.none(), async (req, res) => {
  try {
    const body = req.body || {};
    const nameFromBody = body.name || '';
    const firstName = body.Vorname || body.first_name || body.firstname || '';
    const lastName = body.Nachname || body.last_name || body.lastname || '';
    const company = body.Unternehmen || body.company || '';
    const email = getEmail(body['E-Mail'] || body.email, '');

    if (!email) {
      return res.status(400).json({ success: false, message: 'Email is required.' });
    }

    const payload = {
      source: deriveSource(req, body),
      company: company || null,
      website: body.Website || body.website || null,
      first_name: firstName || null,
      last_name: lastName || null,
      full_name: body.full_name || nameFromBody || [firstName, lastName].filter(Boolean).join(' ') || null,
      email,
      phone: body.Telefon || body.phone || null,
      product_interest: body['Produktinteresse'] || body.product_interest || body.interest || null,
      city: body.Stadt || body.city || null,
      source_channel: body.Quelle || body.source_channel || null,
      interest: body.interest || null,
      profile: body.profil || body.profile || null,
      message: body.message || null,
      consent_contact: normaliseBoolean(body['Einwilligung Kontakt'] || body.consent_contact || body['contact-consent']),
      consent_privacy: normaliseBoolean(body['Datenschutz akzeptiert'] || body.consent_privacy || body['privacy-consent']),
      raw_payload: body
    };

    try {
      const insertId = await insertSubmission(payload);
      payload.id = insertId;
      await sendNotificationMail(payload);
      return res.status(200).json({ success: true, message: 'Form submitted successfully.', id: insertId });
    } catch (dbError) {
      console.error('Error inserting into database:', dbError);
      return res.status(500).json({ success: false, message: 'Could not save form entry. Check MariaDB config.' });
    }
  } catch (error) {
    console.error('Unhandled form error:', error);
    return res.status(500).json({ success: false, message: 'Unexpected server error.' });
  }
});

app.use(express.static(path.join(__dirname), { index: false, dotfiles: 'ignore' }));

app.get('*', (req, res) => {
  if (req.path.startsWith('/.') || path.basename(req.path).startsWith('.')) {
    return res.status(403).send('Forbidden');
  }

  const normalized = req.path === '/' ? '/index.html' : req.path;
  const filePath = path.join(__dirname, normalized);
  if (!filePath.startsWith(__dirname)) {
    return res.status(403).send('Forbidden');
  }
  res.sendFile(filePath, (err) => {
    if (err && !res.headersSent) {
      res.status(404).send('Not found');
    }
  });
});

app.listen(port, () => {
  console.log(`Server running on http://localhost:${port}`);
  console.log('Set DB_* and SMTP_* values in your .env file before testing submissions.');
});
