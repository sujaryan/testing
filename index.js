require('dotenv').config();

const express = require('express');
const crypto = require('crypto');
const { appendRow } = require('./sheets');

const app = express();
const PORT = process.env.PORT || 3000;
const WEBHOOK_SECRET = process.env.RAZORPAY_WEBHOOK_SECRET;
const SHEET_ID = process.env.GOOGLE_SHEET_ID;

// Parse raw body for signature verification
app.use('/webhook', express.raw({ type: 'application/json' }));
app.use(express.json());

app.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

app.post('/webhook', async (req, res) => {
  const signature = req.headers['x-razorpay-signature'];

  if (!signature) {
    console.error('Missing Razorpay signature header');
    return res.status(400).json({ error: 'Missing signature' });
  }

  // Verify webhook signature
  const expectedSignature = crypto
    .createHmac('sha256', WEBHOOK_SECRET)
    .update(req.body)
    .digest('hex');

  if (expectedSignature !== signature) {
    console.error('Webhook signature verification failed');
    return res.status(400).json({ error: 'Invalid signature' });
  }

  let payload;
  try {
    payload = JSON.parse(req.body.toString());
  } catch (err) {
    console.error('Failed to parse webhook payload:', err.message);
    return res.status(400).json({ error: 'Invalid JSON payload' });
  }

  const event = payload.event;
  console.log(`Received event: ${event}`);

  if (event === 'order.paid') {
    try {
      const order = payload.payload.order.entity;
      const payment = payload.payload.payment.entity;

      const timestamp = new Date().toISOString();
      const orderId = order.id || '';
      const paymentId = payment.id || '';
      const amount = (payment.amount / 100).toFixed(2); // Convert paise to INR
      const currency = payment.currency || '';
      const status = payment.status || '';
      const email = payment.email || '';
      const contact = payment.contact || '';
      const method = payment.method || '';

      const row = [timestamp, orderId, paymentId, amount, currency, status, email, contact, method];

      await appendRow(SHEET_ID, row);
      console.log(`Logged order.paid to Google Sheets: Order ${orderId}, Payment ${paymentId}`);
    } catch (err) {
      console.error('Failed to log to Google Sheets:', err.message);
      return res.status(500).json({ error: 'Failed to log to sheet' });
    }
  }

  res.status(200).json({ received: true });
});

app.listen(PORT, () => {
  console.log(`Webhook server running on port ${PORT}`);
});
