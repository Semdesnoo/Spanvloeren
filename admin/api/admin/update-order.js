const jwt  = require('jsonwebtoken');
const { Pool } = require('pg');

let pool;
function getPool() {
  if (!pool) pool = new Pool({ connectionString: process.env.DATABASE_URL, ssl: { rejectUnauthorized: false } });
  return pool;
}

function verifyToken(req, res) {
  const auth  = req.headers.authorization || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : (req.query?._t || '');
  if (!token) { res.status(401).json({ error: 'Niet ingelogd' }); return false; }
  try {
    jwt.verify(token, process.env.JWT_SECRET || 'spanvloeren-fallback-secret');
    return true;
  } catch {
    res.status(401).json({ error: 'Sessie verlopen, log opnieuw in' }); return false;
  }
}

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', req.headers.origin || '*');
  res.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type');
  if (req.method === 'OPTIONS') return res.status(204).end();
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'POST') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  const { order_id, action } = req.body || {};
  if (!order_id || !action) return res.json({ ok: false, error: 'Vereiste velden ontbreken' });

  const db = getPool();
  const { rows } = await db.query('SELECT order_id FROM orders WHERE order_id = $1', [order_id]);
  if (!rows.length) return res.json({ ok: false, error: 'Bestelling niet gevonden' });

  if (action === 'delete') {
    await db.query('DELETE FROM orders WHERE order_id = $1', [order_id]);
    return res.json({ ok: true });
  }
  if (action === 'cancel') {
    await db.query('UPDATE orders SET status=$1, updated_at=NOW() WHERE order_id=$2', ['canceled', order_id]);
    return res.json({ ok: true });
  }
  if (action === 'retour') {
    await db.query('UPDATE orders SET status=$1, updated_at=NOW() WHERE order_id=$2', ['retour', order_id]);
    return res.json({ ok: true });
  }
  return res.json({ ok: false, error: 'Onbekende actie' });
};
