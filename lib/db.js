const { Pool } = require('pg');

let pool;

function getPool() {
  if (!pool) {
    pool = new Pool({
      connectionString: process.env.DATABASE_URL,
      ssl: { rejectUnauthorized: false },
    });
  }
  return pool;
}

async function query(sql, params = []) {
  return getPool().query(sql, params);
}

async function initDb() {
  await query(`
    CREATE TABLE IF NOT EXISTS orders (
      id                   SERIAL PRIMARY KEY,
      order_id             TEXT NOT NULL UNIQUE,
      payment_id           TEXT DEFAULT '',
      status               TEXT DEFAULT 'pending',
      amount               TEXT DEFAULT '0.00',
      name                 TEXT DEFAULT '',
      email                TEXT DEFAULT '',
      phone                TEXT DEFAULT '',
      street               TEXT DEFAULT '',
      "number"             TEXT DEFAULT '',
      postcode             TEXT DEFAULT '',
      city                 TEXT DEFAULT '',
      country              TEXT DEFAULT '',
      notes                TEXT DEFAULT '',
      items                TEXT DEFAULT '[]',
      tracking_code        TEXT DEFAULT '',
      tracking_carrier     TEXT DEFAULT '',
      myparcel_shipment_id TEXT DEFAULT '',
      created_at           TIMESTAMPTZ DEFAULT NOW(),
      updated_at           TIMESTAMPTZ DEFAULT NOW()
    )
  `);
  await query(`
    CREATE TABLE IF NOT EXISTS templates (
      name       TEXT PRIMARY KEY,
      html       TEXT NOT NULL,
      updated_at TIMESTAMPTZ DEFAULT NOW()
    )
  `);
  await query(`ALTER TABLE orders ADD COLUMN IF NOT EXISTS confirmation_sent_at TIMESTAMPTZ`);
  await query(`ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_confirmation_sent_at TIMESTAMPTZ`);
}

module.exports = { query, initDb };
