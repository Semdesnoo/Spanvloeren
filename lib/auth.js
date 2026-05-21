const jwt = require('jsonwebtoken');

function getSecret() {
  return process.env.JWT_SECRET || 'spanvloeren-fallback-secret';
}

function signToken() {
  return jwt.sign({ admin: true }, getSecret(), { expiresIn: '24h' });
}

function verifyToken(req, res) {
  const auth  = req.headers.authorization || '';
  // Also allow token via query string for direct links (iframes, PDF downloads)
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : (req.query?._t || '');
  if (!token) {
    res.status(401).json({ error: 'Niet ingelogd' });
    return false;
  }
  try {
    jwt.verify(token, getSecret());
    return true;
  } catch {
    res.status(401).json({ error: 'Sessie verlopen, log opnieuw in' });
    return false;
  }
}

module.exports = { signToken, verifyToken };
