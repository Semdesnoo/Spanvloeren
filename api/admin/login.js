const { signToken } = require('../../lib/auth');
const { withCors }  = require('../../lib/cors');

module.exports = withCors((req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method Not Allowed' });

  const { password } = req.body || {};
  if (!password || password !== process.env.ADMIN_PASSWORD) {
    return res.status(401).json({ error: 'Onjuist wachtwoord. Probeer het opnieuw.' });
  }

  return res.json({ token: signToken() });
});
