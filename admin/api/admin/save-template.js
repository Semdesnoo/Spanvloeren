const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { withCors }      = require('../../lib/cors');

const ALLOWED = ['order-confirmation', 'shipping-confirmation'];

module.exports = withCors(async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'POST') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const { template, html } = req.body || {};
  if (!ALLOWED.includes(template)) return res.json({ ok: false, error: 'Ongeldig sjabloon' });
  if (!html || html.length < 50)   return res.json({ ok: false, error: 'Sjabloon is te kort' });

  await query(`
    INSERT INTO templates (name, html, updated_at) VALUES ($1, $2, NOW())
    ON CONFLICT (name) DO UPDATE SET html = $2, updated_at = NOW()
  `, [template, html]);

  res.json({ ok: true });
});
