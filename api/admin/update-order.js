const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { withCors }      = require('../../lib/cors');

module.exports = withCors(async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'POST') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const { order_id, action } = req.body || {};
  if (!order_id || !action) return res.json({ ok: false, error: 'Vereiste velden ontbreken' });

  const { rows } = await query('SELECT order_id FROM orders WHERE order_id = $1', [order_id]);
  if (!rows.length) return res.json({ ok: false, error: 'Bestelling niet gevonden' });

  if (action === 'delete') {
    await query('DELETE FROM orders WHERE order_id = $1', [order_id]);
    return res.json({ ok: true });
  }

  if (action === 'cancel') {
    await query('UPDATE orders SET status=$1, updated_at=NOW() WHERE order_id=$2', ['canceled', order_id]);
    return res.json({ ok: true });
  }

  if (action === 'retour') {
    await query('UPDATE orders SET status=$1, updated_at=NOW() WHERE order_id=$2', ['retour', order_id]);
    return res.json({ ok: true });
  }

  return res.json({ ok: false, error: 'Onbekende actie' });
});
