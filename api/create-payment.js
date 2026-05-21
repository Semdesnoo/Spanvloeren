const { initDb } = require('../lib/db');

module.exports = async (req, res) => {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method Not Allowed' });

  await initDb();

  const input = req.body;
  if (!input || !input.amount) return res.status(400).json({ error: 'Ongeldige aanvraag' });

  const amount = Math.round(parseFloat(input.amount) * 100) / 100;
  if (amount <= 0) return res.status(400).json({ error: 'Ongeldig bedrag' });

  const orderRef = 'ORD-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-'
                 + Math.random().toString(36).slice(-6).toUpperCase();
  const customer = input.customer || {};
  const items    = input.items    || [];
  const desc     = `Spanvloeren.nl – ${customer.name || 'Bestelling'} [${orderRef}]`;
  const baseUrl  = process.env.SITE_URL || 'https://www.spanvloeren.nl';

  const payload = {
    amount:      { currency: 'EUR', value: amount.toFixed(2) },
    description: desc,
    redirectUrl: `${baseUrl}/betaald.html?order=${encodeURIComponent(orderRef)}`,
    cancelUrl:   `${baseUrl}/checkout.html?geannuleerd=1`,
    webhookUrl:  `${baseUrl}/api/webhook`,
    locale:      'nl_NL',
    metadata:    { order_id: orderRef, customer, items },
  };

  const mollieRes = await fetch('https://api.mollie.com/v2/payments', {
    method: 'POST',
    headers: {
      Authorization:   `Bearer ${process.env.MOLLIE_API_KEY}`,
      'Content-Type':  'application/json',
    },
    body: JSON.stringify(payload),
  });

  if (!mollieRes.ok) {
    const data = await mollieRes.json().catch(() => ({}));
    return res.status(502).json({ error: 'Betaling aanmaken mislukt', detail: data.detail || '' });
  }

  const data = await mollieRes.json();
  if (!data._links?.checkout?.href) {
    return res.status(502).json({ error: 'Geen checkout URL ontvangen' });
  }

  return res.json({
    checkoutUrl: data._links.checkout.href,
    orderId:     orderRef,
    paymentId:   data.id,
  });
};
