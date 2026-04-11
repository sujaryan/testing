/**
 * Icon generator — run with: node icons/create-icons.js
 * Creates icon16.png, icon48.png, icon128.png in the icons/ directory.
 * Uses only Node.js built-ins (no npm packages required).
 *
 * Generates a purple credit-card style icon using raw PNG construction.
 */

'use strict';

const fs   = require('fs');
const path = require('path');
const zlib = require('zlib');

/**
 * Build a minimal valid PNG from scratch.
 *
 * @param {number} size  - Width and height in pixels
 * @param {Buffer} rgba  - Raw RGBA pixel data (size * size * 4 bytes)
 */
function buildPng(size, rgba) {
  function crc32(buf) {
    let crc = 0xFFFFFFFF;
    const table = [];
    for (let i = 0; i < 256; i++) {
      let c = i;
      for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      table[i] = c;
    }
    for (const byte of buf) crc = table[(crc ^ byte) & 0xFF] ^ (crc >>> 8);
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }

  function chunk(type, data) {
    const typeBytes  = Buffer.from(type, 'ascii');
    const lenBuf     = Buffer.alloc(4);
    lenBuf.writeUInt32BE(data.length, 0);
    const content    = Buffer.concat([typeBytes, data]);
    const crcBuf     = Buffer.alloc(4);
    crcBuf.writeUInt32BE(crc32(content), 0);
    return Buffer.concat([lenBuf, content, crcBuf]);
  }

  // Signature
  const sig = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);

  // IHDR: width, height, bit depth 8, color type 6 (RGBA), compression, filter, interlace
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0);
  ihdr.writeUInt32BE(size, 4);
  ihdr[8]  = 8;  // bit depth
  ihdr[9]  = 6;  // RGBA
  ihdr[10] = 0;  // compression
  ihdr[11] = 0;  // filter
  ihdr[12] = 0;  // interlace

  // Raw scanlines with filter byte 0 (None) prepended to each row
  const rawRows = [];
  for (let y = 0; y < size; y++) {
    rawRows.push(0x00); // filter byte
    for (let x = 0; x < size; x++) {
      const i = (y * size + x) * 4;
      rawRows.push(rgba[i], rgba[i+1], rgba[i+2], rgba[i+3]);
    }
  }
  const rawBuf  = Buffer.from(rawRows);
  const compressed = zlib.deflateSync(rawBuf, { level: 9 });

  const idat = chunk('IDAT', compressed);
  const iend = chunk('IEND', Buffer.alloc(0));

  return Buffer.concat([sig, chunk('IHDR', ihdr), idat, iend]);
}

/**
 * Draw a simple credit-card icon on an RGBA canvas.
 *
 * Color scheme:
 *   Background: #5B4FCF (indigo-purple)
 *   Card body:  white / semi-transparent
 *   Chip:       gold
 *   Stripe:     dark band
 */
function drawIcon(size) {
  const rgba = Buffer.alloc(size * 4 * size, 0); // transparent

  // Helper: set pixel (clamped)
  function setPixel(x, y, r, g, b, a) {
    if (x < 0 || y < 0 || x >= size || y >= size) return;
    const i = (y * size + x) * 4;
    rgba[i]   = r;
    rgba[i+1] = g;
    rgba[i+2] = b;
    rgba[i+3] = a;
  }

  // Helper: fill rectangle
  function fillRect(x0, y0, w, h, r, g, b, a) {
    for (let y = y0; y < y0 + h; y++) {
      for (let x = x0; x < x0 + w; x++) {
        setPixel(x, y, r, g, b, a);
      }
    }
  }

  // Helper: draw rounded rect by filling rows
  function fillRoundRect(x0, y0, w, h, radius, r, g, b, a) {
    for (let y = y0; y < y0 + h; y++) {
      for (let x = x0; x < x0 + w; x++) {
        const dx = Math.min(x - x0, x0 + w - 1 - x);
        const dy = Math.min(y - y0, y0 + h - 1 - y);
        if (dx < radius && dy < radius) {
          const dist = Math.sqrt((radius - dx) ** 2 + (radius - dy) ** 2);
          if (dist > radius) continue;
        }
        setPixel(x, y, r, g, b, a);
      }
    }
  }

  const s = size;
  const r = Math.max(2, Math.round(s * 0.12)); // corner radius

  // Background: rounded square with indigo-purple gradient
  fillRoundRect(0, 0, s, s, r, 91, 79, 207, 255);   // #5B4FCF

  // Slightly lighter top half for gradient feel
  fillRoundRect(0, 0, s, Math.floor(s * 0.5), r, 102, 91, 220, 255); // #665BDC

  // White card body
  const cX = Math.round(s * 0.12);
  const cY = Math.round(s * 0.28);
  const cW = s - cX * 2;
  const cH = Math.round(s * 0.44);
  const cR = Math.max(1, Math.round(s * 0.07));
  fillRoundRect(cX, cY, cW, cH, cR, 255, 255, 255, 230);

  // Dark magnetic stripe at the top of the card
  const stripeY = cY + Math.round(cH * 0.18);
  const stripeH = Math.max(1, Math.round(cH * 0.2));
  fillRect(cX, stripeY, cW, stripeH, 40, 40, 60, 220);

  // Gold chip
  const chipX = cX + Math.round(cW * 0.12);
  const chipY = stripeY + stripeH + Math.round(cH * 0.08);
  const chipW = Math.max(2, Math.round(cW * 0.22));
  const chipH = Math.max(2, Math.round(cH * 0.22));
  fillRoundRect(chipX, chipY, chipW, chipH, Math.max(1, Math.round(chipW * 0.2)),
                200, 165, 40, 255); // gold

  return rgba;
}

// Generate and write PNG files
const outDir = path.join(__dirname);
const sizes = [16, 48, 128];

for (const size of sizes) {
  const rgba = drawIcon(size);
  const png  = buildPng(size, rgba);
  const outPath = path.join(outDir, `icon${size}.png`);
  fs.writeFileSync(outPath, png);
  console.log(`Created ${outPath} (${png.length} bytes)`);
}

console.log('Icons generated successfully.');
