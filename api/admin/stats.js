const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');

module.exports = async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'GET') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const { rows } = await query(`
    SELECT
      COUNT(*)::int                                                                                          AS total,
      SUM(CASE WHEN status='paid'                              THEN 1 ELSE 0 END)::int                      AS paid,
      SUM(CASE WHEN status IN ('pending','open')               THEN 1 ELSE 0 END)::int                      AS open_count,
      SUM(CASE WHEN status IN ('failed','canceled','expired')  THEN 1 ELSE 0 END)::int                      AS failed,
      ROUND(SUM(CASE WHEN status='paid' THEN amount::numeric ELSE 0 END)::numeric, 2)                       AS revenue,
      ROUND(SUM(CASE WHEN status='paid' AND created_at::date = NOW()::date THEN amount::numeric ELSE 0 END)::numeric, 2) AS today
    FROM orders
  `);
  res.json(rows[0]);
};
