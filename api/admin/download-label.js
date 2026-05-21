const { verifyToken } = require('../../lib/auth');
const { withCors }    = require('../../lib/cors');

module.exports = withCors(async (req, res) => {
  if (req.method !== 'GET') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  const shipmentId = (req.query.id    || '').trim();
  const orderId    = (req.query.order || 'label').trim();
  const inline     = !!req.query.print;

  if (!shipmentId || !/^\d+$/.test(shipmentId)) {
    return res.status(400).send('Ongeldig zending-ID');
  }

  const authHeader = 'basic ' + Buffer.from(process.env.MYPARCEL_API_KEY || '').toString('base64');
  const labelRes   = await fetch(`https://api.myparcel.nl/shipment_labels/${shipmentId}?format=pdf&dpi=96`, {
    headers: { Authorization: authHeader, Accept: 'application/pdf' },
    redirect: 'follow',
  });

  if (!labelRes.ok) {
    return res.status(502).send('Label nog niet beschikbaar. Probeer het over een moment opnieuw.');
  }

  const buf = await labelRes.arrayBuffer();
  if (buf.byteLength < 1000) {
    return res.status(502).send('Label nog niet beschikbaar. Probeer het over een moment opnieuw.');
  }

  res.setHeader('Content-Type', 'application/pdf');
  res.setHeader('Content-Disposition', `${inline ? 'inline' : 'attachment'}; filename="label-${orderId}.pdf"`);
  res.send(Buffer.from(buf));
});
