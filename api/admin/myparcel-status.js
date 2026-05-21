const { verifyToken } = require('../../lib/auth');
const { withCors }    = require('../../lib/cors');

module.exports = withCors(async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'GET') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  const authHeader = 'basic ' + Buffer.from(process.env.MYPARCEL_API_KEY || '').toString('base64');
  const status     = { ok: false, name: '', shop: '', billing_ok: true, error: '' };

  try {
    const mpRes = await fetch('https://api.myparcel.nl/accounts', {
      headers: { Authorization: authHeader, Accept: 'application/json;charset=utf-8' },
      signal: AbortSignal.timeout(5000),
    });
    if (mpRes.ok) {
      const data    = await mpRes.json();
      const account = data.data?.accounts?.[0];
      if (account) {
        status.ok         = true;
        status.name       = `${account.first_name || ''} ${account.last_name || ''}`.trim();
        status.shop       = account.shops?.[0]?.name || '';
        status.billing_ok = account.shops?.[0]?.subscription_fee?.transaction_status !== 'unpaid';
      }
    } else {
      status.error = `Verbindingsfout (HTTP ${mpRes.status})`;
    }
  } catch (e) {
    status.error = e.message;
  }

  res.json(status);
});
