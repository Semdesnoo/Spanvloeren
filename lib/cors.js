const ALLOWED_ORIGINS = [
  process.env.ADMIN_ORIGIN || 'https://admin.spanvloeren.nl',
];

function withCors(handler) {
  return async (req, res) => {
    const origin = req.headers.origin || '';
    const allowed = ALLOWED_ORIGINS.includes(origin) || origin.endsWith('.vercel.app');

    res.setHeader('Access-Control-Allow-Origin',  allowed ? origin : ALLOWED_ORIGINS[0]);
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    res.setHeader('Access-Control-Max-Age',       '86400');

    if (req.method === 'OPTIONS') { res.status(204).end(); return; }

    return handler(req, res);
  };
}

module.exports = { withCors };
