const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { withCors }      = require('../../lib/cors');

const COUNTRY_MAP = {
  'nederland': 'NL', 'netherlands': 'NL', 'nl': 'NL',
  'belgie':    'BE', 'belgië':      'BE', 'belgium':     'BE', 'be': 'BE',
  'duitsland': 'DE', 'germany':     'DE', 'deutschland': 'DE', 'de': 'DE',
  'frankrijk': 'FR', 'france':      'FR', 'fr': 'FR',
  'verenigd koninkrijk': 'GB', 'uk': 'GB', 'gb': 'GB',
};

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

module.exports = withCors(async (req, res) => {
  if (req.method !== 'POST') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const { order_id, carrier = 1, package_type = 1 } = req.body || {};
  const safeCarrier = [1, 5].includes(Number(carrier))       ? Number(carrier)       : 1;
  const safePkgType = [1, 2].includes(Number(package_type))  ? Number(package_type)  : 1;

  if (!order_id) {
    res.setHeader('Content-Type', 'application/json');
    return res.json({ ok: false, error: 'Geen order-ID opgegeven' });
  }

  const { rows } = await query('SELECT * FROM orders WHERE order_id=$1', [order_id]);
  if (!rows.length) {
    res.setHeader('Content-Type', 'application/json');
    return res.json({ ok: false, error: 'Bestelling niet gevonden' });
  }

  const order    = rows[0];
  const cc       = COUNTRY_MAP[(order.country || '').toLowerCase().trim()] || 'NL';
  const postcode = (order.postcode || '').toUpperCase().replace(/\s/g, '');

  const recipient = Object.fromEntries(Object.entries({
    cc,
    city:        order.city,
    street:      order.street,
    number:      order.number,
    postal_code: postcode,
    person:      order.name,
    email:       order.email || undefined,
    phone:       order.phone || undefined,
  }).filter(([, v]) => v));

  const payload    = { data: { shipments: [{ recipient, carrier: safeCarrier, options: { package_type: safePkgType, label_description: order_id } }] } };
  const authHeader = 'basic ' + Buffer.from(process.env.MYPARCEL_API_KEY || '').toString('base64');

  const mpRes = await fetch('https://api.myparcel.nl/shipments', {
    method: 'POST',
    headers: {
      Authorization:  authHeader,
      'Content-Type': 'application/vnd.shipment+json;charset=utf-8',
      Accept:         'application/json;charset=utf-8',
    },
    body: JSON.stringify(payload),
  });

  if (!mpRes.ok) {
    const data = await mpRes.json().catch(() => ({}));
    res.setHeader('Content-Type', 'application/json');
    return res.json({ ok: false, error: `MyParcel: ${data.message || data.errors?.[0]?.message || 'Onbekende fout'}` });
  }

  const data       = await mpRes.json();
  const shipmentId = String(data.data?.ids?.[0]?.id || '');
  if (!shipmentId) {
    res.setHeader('Content-Type', 'application/json');
    return res.json({ ok: false, error: 'Geen zending-ID ontvangen van MyParcel' });
  }

  await query('UPDATE orders SET myparcel_shipment_id=$1, updated_at=NOW() WHERE order_id=$2', [shipmentId, order_id]);

  // Probeer PDF op te halen (max 3 pogingen, 3s wachten)
  let labelPdf = null;
  for (let i = 0; i < 3; i++) {
    await sleep(3000);
    const labelRes = await fetch(`https://api.myparcel.nl/shipment_labels/${shipmentId}?format=pdf&dpi=96`, {
      headers: { Authorization: authHeader, Accept: 'application/pdf' },
      redirect: 'follow',
    });
    if (labelRes.ok) {
      const buf = await labelRes.arrayBuffer();
      if (buf.byteLength > 1000) { labelPdf = Buffer.from(buf); break; }
    }
  }

  if (!labelPdf) {
    res.setHeader('Content-Type', 'application/json');
    return res.json({
      ok: true, pending: true, shipment_id: shipmentId,
      message: `Zending aangemaakt (#${shipmentId}). Label wordt nog gegenereerd — download het via de knop hieronder.`,
    });
  }

  res.setHeader('Content-Type', 'application/pdf');
  res.setHeader('Content-Disposition', `attachment; filename="label-${order_id}.pdf"`);
  res.send(labelPdf);
});
