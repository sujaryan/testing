// ============================================================
// Razorpay Webhook Handler — Google Apps Script
// ============================================================
// SETUP:
//   1. Paste this into Extensions > Apps Script in your Google Sheet
//   2. Set WEBHOOK_SECRET to your Razorpay webhook secret
//   3. Create a sheet tab named "Payments" (or change SHEET_NAME)
//   4. Deploy > New deployment > Web app
//      - Execute as: Me
//      - Who has access: Anyone
//   5. Copy the Web App URL into Razorpay Dashboard > Webhooks
// ============================================================

const WEBHOOK_SECRET = 'your_razorpay_webhook_secret'; // <-- replace this
const SHEET_NAME = 'Payments';

// Column headers written on first run
const HEADERS = [
  'Timestamp', 'Order ID', 'Payment ID',
  'Amount (INR)', 'Currency', 'Status',
  'Email', 'Contact', 'Payment Method'
];

function doPost(e) {
  try {
    const rawBody = e.postData.contents;
    const signature = e.parameter['x-razorpay-signature']
      || (e.postData.headers && e.postData.headers['x-razorpay-signature']);

    // Razorpay sends the signature as a request header.
    // Apps Script exposes headers via e.postData.headers (if available)
    // or you can skip signature check during initial testing.
    if (signature && !isValidSignature(rawBody, signature)) {
      return jsonResponse(400, { error: 'Invalid signature' });
    }

    const payload = JSON.parse(rawBody);
    const event = payload.event;

    if (event === 'order.paid') {
      const order   = payload.payload.order.entity;
      const payment = payload.payload.payment.entity;

      const row = [
        new Date().toISOString(),
        order.id   || '',
        payment.id || '',
        (payment.amount / 100).toFixed(2), // paise → INR
        payment.currency || '',
        payment.status   || '',
        payment.email    || '',
        payment.contact  || '',
        payment.method   || ''
      ];

      appendToSheet(row);
      Logger.log('Logged order.paid: ' + order.id + ' / ' + payment.id);
    }

    return jsonResponse(200, { received: true });

  } catch (err) {
    Logger.log('Error: ' + err.message);
    return jsonResponse(500, { error: err.message });
  }
}

// --------------- Helpers ---------------

function isValidSignature(body, signature) {
  const secretBytes = Utilities.newBlob(WEBHOOK_SECRET).getBytes();
  const bodyBytes   = Utilities.newBlob(body).getBytes();
  const computed    = Utilities.computeHmacSha256Signature(bodyBytes, secretBytes);
  const hex         = bytesToHex(computed);
  return hex === signature;
}

function bytesToHex(bytes) {
  return bytes.map(function(b) {
    return ('0' + (b & 0xff).toString(16)).slice(-2);
  }).join('');
}

function appendToSheet(row) {
  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  let sheet   = ss.getSheetByName(SHEET_NAME);

  // Create the sheet and add headers if it doesn't exist yet
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    sheet.appendRow(HEADERS);
    sheet.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold');
  }

  sheet.appendRow(row);
}

function jsonResponse(statusCode, data) {
  const output = ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
  return output;
}
