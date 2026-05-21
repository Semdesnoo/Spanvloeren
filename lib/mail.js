const fs   = require('fs');
const path = require('path');

const TEMPLATES_DIR = path.join(__dirname, '../api/templates');

function esc(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function buildItemRows(items) {
  return (items || []).map(item => {
    const name  = esc(item.name || '—');
    const qty   = item.qty || 1;
    const price = '&euro;&nbsp;' + Number(item.linePrice || 0).toFixed(2).replace('.', ',');
    const type  = item.bundle ? '6-Pack' : '1L';
    return `<tr>
      <td style="padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:15px;color:#111827">${name} <span style="color:#9ca3af;font-size:13px">(${type})</span></td>
      <td style="padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:15px;color:#6b7280;text-align:center">${qty}</td>
      <td style="padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:15px;font-weight:700;color:#111827;text-align:right">${price}</td>
    </tr>`;
  }).join('');
}

function renderTemplate(html, vars) {
  let out = html;
  for (const [k, v] of Object.entries(vars)) {
    out = out.replaceAll(`{{${k}}}`, v);
  }
  return out;
}

async function getTemplate(name, db) {
  const { rows } = await db.query('SELECT html FROM templates WHERE name = $1', [name]);
  if (rows.length) return rows[0].html;
  const filePath = path.join(TEMPLATES_DIR, `${name}.html`);
  if (fs.existsSync(filePath)) return fs.readFileSync(filePath, 'utf8');
  throw new Error(`Template niet gevonden: ${name}`);
}

async function sendEmail(to, subject, html) {
  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${process.env.RESEND_API_KEY}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: 'Spanvloeren.nl <info@spanvloeren.nl>',
      to:   [to],
      subject,
      html,
    }),
  });
  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Resend fout: ${res.status} – ${err}`);
  }
  return res.json();
}

async function sendOrderConfirmation(orderId, customer, items, amount, db) {
  const email = customer.email;
  if (!email) return;
  const tpl  = await getTemplate('order-confirmation', db);
  const html = renderTemplate(tpl, {
    name:            esc(customer.name || 'klant'),
    order_id:        esc(orderId),
    items_table:     buildItemRows(items),
    total:           '&euro;&nbsp;' + Number(amount).toFixed(2).replace('.', ','),
    address_name:    esc(customer.name || ''),
    address_street:  esc(((customer.straat || '') + ' ' + (customer.huisnummer || '')).trim()),
    address_pc:      esc(((customer.postcode || '') + ' ' + (customer.stad || '')).trim()),
    address_country: esc(customer.land || ''),
  });
  await sendEmail(email, `Bevestiging van uw bestelling ${orderId}`, html);
}

async function sendShippingConfirmation(order, tracking, carrier, db) {
  const email = order.email;
  if (!email) return;
  let trackUrl, carrierName, carrierColor, carrierTextColor;
  if (carrier === 'dhl') {
    trackUrl         = `https://www.dhl.com/nl-nl/home/volg-uw-zending.html?tracking-id=${encodeURIComponent(tracking)}`;
    carrierName      = 'DHL';
    carrierColor     = '#ffcc00';
    carrierTextColor = '#000000';
  } else {
    trackUrl         = `https://postnl.nl/tracktrace/?B=${encodeURIComponent(tracking)}&P=${encodeURIComponent(order.postcode || '')}&T=C&L=NL`;
    carrierName      = 'PostNL';
    carrierColor     = '#ff6200';
    carrierTextColor = '#ffffff';
  }
  const items = JSON.parse(order.items || '[]');
  const tpl   = await getTemplate('shipping-confirmation', db);
  const html  = renderTemplate(tpl, {
    name:               esc(order.name || 'klant'),
    order_id:           esc(order.order_id),
    tracking_code:      esc(tracking),
    tracking_url:       esc(trackUrl),
    carrier_name:       carrierName,
    carrier_color:      carrierColor,
    carrier_text_color: carrierTextColor,
    items_table:        buildItemRows(items),
    address_name:       esc(order.name || ''),
    address_street:     esc(((order.street || '') + ' ' + (order.number || '')).trim()),
    address_pc:         esc(((order.postcode || '') + ' ' + (order.city || '')).trim()),
    address_country:    esc(order.country || ''),
  });
  await sendEmail(email, `Uw bestelling ${order.order_id} is verzonden!`, html);
}

module.exports = { esc, buildItemRows, renderTemplate, getTemplate, sendOrderConfirmation, sendShippingConfirmation };
