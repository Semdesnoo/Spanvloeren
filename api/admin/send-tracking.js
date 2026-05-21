const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { sendShippingConfirmation } = require('../../lib/mail');

module.exports = async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'POST') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const { order_id, tracking, carrier } = req.body || {};
  const safeCarrier = ['postnl', 'dhl'].includes(carrier) ? carrier : 'postnl';

  if (!order_id || !tracking) {
    return res.json({ ok: false, error: 'Vul een track & trace nummer in' });
  }

  await query(
    'UPDATE orders SET tracking_code=$1, tracking_carrier=$2, updated_at=NOW() WHERE order_id=$3',
    [tracking, safeCarrier, order_id]
  );

  const { rows } = await query('SELECT * FROM orders WHERE order_id=$1', [order_id]);
  if (!rows.length) return res.json({ ok: false, error: 'Bestelling niet gevonden' });

  const order = rows[0];
  if (!order.email) return res.json({ ok: false, error: 'Geen e-mailadres voor deze bestelling' });

  try {
    await sendShippingConfirmation(order, tracking, safeCarrier, { query });
    res.json({ ok: true, email: order.email });
  } catch (e) {
    res.json({ ok: false, error: e.message });
  }
};
