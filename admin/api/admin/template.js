const { verifyToken } = require('../../lib/auth');
const { initDb, query } = require('../../lib/db');
const { buildItemRows, renderTemplate, getTemplate } = require('../../lib/mail');
const { withCors }      = require('../../lib/cors');

const ALLOWED = ['order-confirmation', 'shipping-confirmation'];

const SAMPLE_ITEMS = [
  { name: 'Spanvloer Atlas 6x6m', qty: 1, linePrice: 1249.00, bundle: false },
  { name: 'Tatami Puzzelmatten',   qty: 4, linePrice: 199.80,  bundle: false },
];

module.exports = withCors(async (req, res) => {
  if (req.method !== 'GET') return res.status(405).end();
  if (!verifyToken(req, res)) return;

  await initDb();

  const name = (req.query.name || '').trim();
  if (!ALLOWED.includes(name)) return res.status(400).json({ error: 'Ongeldig sjabloon' });

  const html = await getTemplate(name, { query });

  if (req.query.preview === '1') {
    const vars = name === 'order-confirmation'
      ? {
          name: 'Jan Jansen', order_id: 'ORD-20260519-DEMO',
          items_table: buildItemRows(SAMPLE_ITEMS),
          total: '&euro;&nbsp;1.448,80',
          address_name: 'Jan Jansen', address_street: 'Hoofdstraat 42',
          address_pc: '1234 AB Amsterdam', address_country: 'Nederland',
        }
      : {
          name: 'Jan Jansen', order_id: 'ORD-20260519-DEMO',
          tracking_code: '3SDEVC987654321', tracking_url: '#',
          carrier_name: 'PostNL', carrier_color: '#ff6200', carrier_text_color: '#ffffff',
          items_table: buildItemRows(SAMPLE_ITEMS),
          address_name: 'Jan Jansen', address_street: 'Hoofdstraat 42',
          address_pc: '1234 AB Amsterdam', address_country: 'Nederland',
        };
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    return res.send(renderTemplate(html, vars));
  }

  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.json({ html });
});
