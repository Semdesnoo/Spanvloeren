const { initDb, query } = require('../lib/db');
const { sendOrderConfirmation } = require('../lib/mail');

const COUNTRY_MAP = {
  'nederland': 'NL', 'netherlands': 'NL', 'nl': 'NL',
  'belgie':    'BE', 'belgië':      'BE', 'belgium':     'BE', 'be': 'BE',
  'duitsland': 'DE', 'germany':     'DE', 'deutschland': 'DE', 'de': 'DE',
  'frankrijk': 'FR', 'france':      'FR', 'fr': 'FR',
  'verenigd koninkrijk': 'GB', 'uk': 'GB', 'gb': 'GB',
};

module.exports = async (req, res) => {
  if (req.method !== 'POST') { res.status(405).end(); return; }

  await initDb();

  const paymentId = req.body?.id || '';
  if (!paymentId) { res.status(400).end(); return; }

  const mollieRes = await fetch(`https://api.mollie.com/v2/payments/${encodeURIComponent(paymentId)}`, {
    headers: { Authorization: `Bearer ${process.env.MOLLIE_API_KEY}` },
  });
  if (!mollieRes.ok) { res.status(200).send('OK'); return; }

  const payment  = await mollieRes.json();
  const status   = payment.status   || 'unknown';
  const amount   = payment.amount?.value || '0.00';
  const orderId  = payment.metadata?.order_id || paymentId;
  const customer = payment.metadata?.customer || {};
  const items    = payment.metadata?.items    || [];

  await query(`
    INSERT INTO orders
      (order_id, payment_id, status, amount, name, email, phone, street, "number", postcode, city, country, notes, items)
    VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14)
    ON CONFLICT (order_id) DO UPDATE SET
      status=EXCLUDED.status, payment_id=EXCLUDED.payment_id, updated_at=NOW()
  `, [
    orderId, paymentId, status, amount,
    customer.name       || '',
    customer.email      || '',
    customer.telefoon   || '',
    customer.straat     || '',
    customer.huisnummer || '',
    customer.postcode   || '',
    customer.stad       || '',
    customer.land       || '',
    customer.notities   || '',
    JSON.stringify(items),
  ]);

  if (status === 'paid') {
    const ntfyTopic = process.env.NTFY_TOPIC || '';
    if (ntfyTopic && !ntfyTopic.includes('KIES')) {
      fetch(`https://ntfy.sh/${encodeURIComponent(ntfyTopic)}`, {
        method: 'POST',
        headers: {
          Title:    '📦 Nieuwe bestelling!',
          Priority: 'high',
          Tags:     'package,moneybag',
          Click:    `${process.env.SITE_URL || ''}/admin/?order=${encodeURIComponent(orderId)}`,
        },
        body: `Nieuwe bestelling: ${orderId}\nKlant: ${customer.name || '—'}\nBedrag: €${amount}\nStad: ${customer.stad || '—'}`,
      }).catch(() => {});
    }

    try {
      await sendOrderConfirmation(orderId, customer, items, amount, { query });
      await query('UPDATE orders SET confirmation_sent_at=NOW(), updated_at=NOW() WHERE order_id=$1', [orderId]);
    } catch (e) {
      console.error('[Mail]', e.message);
    }

    const { rows } = await query('SELECT myparcel_shipment_id FROM orders WHERE order_id=$1', [orderId]);
    if (!rows[0]?.myparcel_shipment_id) {
      const cc       = COUNTRY_MAP[(customer.land || '').toLowerCase().trim()] || 'NL';
      const postcode = (customer.postcode || '').toUpperCase().replace(/\s/g, '');
      const recipient = Object.fromEntries(Object.entries({
        cc,
        city:        customer.stad,
        street:      customer.straat,
        number:      customer.huisnummer,
        postal_code: postcode,
        person:      customer.name,
        email:       customer.email   || undefined,
        phone:       customer.telefoon || undefined,
      }).filter(([, v]) => v));

      const autoCarrier  = parseInt(process.env.AUTO_CARRIER      || '5', 10);
      const autoPkgType  = parseInt(process.env.AUTO_PACKAGE_TYPE || '1', 10);
      const mpPayload    = {
        data: { shipments: [{ recipient, carrier: autoCarrier, options: { package_type: autoPkgType, label_description: orderId } }] },
      };

      try {
        const mpRes = await fetch('https://api.myparcel.nl/shipments', {
          method: 'POST',
          headers: {
            Authorization:  'basic ' + Buffer.from(process.env.MYPARCEL_API_KEY || '').toString('base64'),
            'Content-Type': 'application/vnd.shipment+json;charset=utf-8',
            Accept:         'application/json;charset=utf-8',
          },
          body: JSON.stringify(mpPayload),
        });
        if (mpRes.ok) {
          const mpData    = await mpRes.json();
          const shipmentId = String(mpData.data?.ids?.[0]?.id || '');
          if (shipmentId) {
            await query('UPDATE orders SET myparcel_shipment_id=$1, updated_at=NOW() WHERE order_id=$2', [shipmentId, orderId]);
          }
        }
      } catch (e) {
        console.error('[MyParcel]', e.message);
      }
    }
  }

  res.status(200).send('OK');
};
