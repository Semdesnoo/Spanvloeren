const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { withCors }      = require('../../lib/cors');

module.exports = withCors(async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'GET') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const id = (req.query.id || '').trim();
  if (!id) return res.status(400).json({ error: 'Geen order-ID opgegeven' });

  const { rows } = await query('SELECT * FROM orders WHERE order_id = $1', [id]);
  if (!rows.length) return res.status(404).json({ error: 'Bestelling niet gevonden' });

  const o = rows[0];
  o.items_arr = JSON.parse(o.items || '[]');
  res.json(o);
});
