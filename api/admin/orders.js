const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { withCors }      = require('../../lib/cors');

module.exports = withCors(async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'GET') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const VALID_STATUSES = ['paid', 'pending', 'open', 'failed', 'canceled', 'expired'];
  const filter = VALID_STATUSES.includes(req.query.status) ? req.query.status : '';
  const search = (req.query.q || '').trim();

  const where  = [];
  const params = [];

  if (filter) {
    params.push(filter);
    where.push(`status = $${params.length}`);
  }
  if (search) {
    params.push(`%${search}%`);
    const n = params.length;
    where.push(`(order_id ILIKE $${n} OR name ILIKE $${n} OR email ILIKE $${n} OR city ILIKE $${n})`);
  }

  const whereSQL = where.length ? 'WHERE ' + where.join(' AND ') : '';
  const { rows } = await query(
    `SELECT * FROM orders ${whereSQL} ORDER BY created_at DESC LIMIT 300`,
    params
  );
  res.json(rows);
});
