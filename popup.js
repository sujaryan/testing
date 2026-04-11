/**
 * Payment Gateway Detector — Popup Script
 * Orchestrates: tab query → result fetch → DOM rendering
 */

'use strict';

// Category icons mapping
const METHOD_ICONS = {
  visa:        '💳',
  mastercard:  '💳',
  amex:        '💳',
  discover:    '💳',
  jcb:         '💳',
  dinersclub:  '💳',
  unionpay:    '💳',
  paypal:      '🅿',
  applepay:    '🍎',
  googlepay:   '🔵',
  amazonpay:   '📦',
  klarna:      'K',
  afterpay:    'A',
  affirm:      '✓',
  upi:         '🏦',
  netbanking:  '🏦',
  banktransfer:'🏦',
  echeck:      '📄',
  crypto:      '₿',
  wallet:      '👜'
};

let currentResults = null;
let currentTabId = null;

// ─────────────────────────────────────────────────────────────────────────────
// INITIALIZATION
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  const rescanBtn = document.getElementById('rescan-btn');
  const copyBtn   = document.getElementById('copy-btn');

  rescanBtn.addEventListener('click', handleRescan);
  copyBtn.addEventListener('click', handleCopy);

  await loadResults();
});

async function loadResults() {
  showState('loading');

  try {
    // Get the active tab
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!tab) {
      showError('No active tab found.');
      return;
    }

    currentTabId = tab.id;

    // Update URL display
    const urlEl = document.getElementById('page-url');
    try {
      const url = new URL(tab.url || '');
      urlEl.textContent = url.hostname || tab.url;
    } catch (e) {
      urlEl.textContent = tab.url || '';
    }

    // Block non-analyzable pages
    const blockedPrefixes = ['chrome://', 'chrome-extension://', 'edge://', 'about:', 'moz-extension://'];
    if (blockedPrefixes.some(p => (tab.url || '').startsWith(p))) {
      showError('This page cannot be analyzed.\nTry a regular website.');
      return;
    }

    // Try background cache first (already accumulated from all frames)
    const cacheResponse = await sendToBackground({
      type: 'GET_CACHED_RESULTS',
      tabId: tab.id
    });

    if (cacheResponse && cacheResponse.found && cacheResponse.data) {
      currentResults = cacheResponse.data;
      renderResults(currentResults);
      return;
    }

    // No cache: ask content script directly
    const results = await sendToContentScript(tab.id, { type: 'DETECT_PAYMENTS' });
    if (results) {
      currentResults = results;
      renderResults(currentResults);
    } else {
      // Content script not injected yet — inject it programmatically
      await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        files: ['content.js']
      });
      // Wait a moment for detection to run
      await sleep(600);
      const retryResults = await sendToContentScript(tab.id, { type: 'DETECT_PAYMENTS' });
      currentResults = retryResults || { gateways: [], methods: [] };
      renderResults(currentResults);
    }

  } catch (err) {
    showError('Failed to analyze page.\n' + (err.message || ''));
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RESCAN
// ─────────────────────────────────────────────────────────────────────────────
async function handleRescan() {
  const btn = document.getElementById('rescan-btn');
  btn.classList.add('spinning');
  btn.disabled = true;

  showState('loading');

  try {
    const response = await sendToBackground({
      type: 'RESCAN',
      tabId: currentTabId
    });

    if (response) {
      currentResults = response;
    } else {
      // Fallback: direct content script message
      const direct = await sendToContentScript(currentTabId, { type: 'RESCAN' });
      currentResults = direct || { gateways: [], methods: [] };
    }
    renderResults(currentResults);
  } catch (err) {
    showError('Rescan failed: ' + (err.message || ''));
  } finally {
    btn.classList.remove('spinning');
    btn.disabled = false;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// COPY REPORT
// ─────────────────────────────────────────────────────────────────────────────
async function handleCopy() {
  if (!currentResults) return;

  const report = {
    url: currentResults.url,
    scannedAt: new Date(currentResults.timestamp || Date.now()).toISOString(),
    paymentGateways: (currentResults.gateways || []).map(g => ({
      name: g.name,
      confidence: g.confidence,
      signalsDetected: g.signals
    })),
    paymentMethods: (currentResults.methods || []).map(m => m.name)
  };

  try {
    await navigator.clipboard.writeText(JSON.stringify(report, null, 2));
    const btn = document.getElementById('copy-btn');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copied!`;
    btn.classList.add('copied');
    setTimeout(() => {
      btn.innerHTML = originalHTML;
      btn.classList.remove('copied');
    }, 2000);
  } catch (e) {
    // Clipboard API failed
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RENDERING
// ─────────────────────────────────────────────────────────────────────────────
function renderResults(data) {
  const gateways = data.gateways || [];
  const methods  = data.methods  || [];

  if (gateways.length === 0 && methods.length === 0) {
    showState('empty');
    return;
  }

  showState('results');

  // Update counts
  document.getElementById('gateway-count').textContent = gateways.length;
  document.getElementById('method-count').textContent  = methods.length;

  // Render gateways
  renderGateways(gateways);

  // Render methods
  renderMethods(methods);

  // Show footer
  const footer = document.getElementById('footer');
  footer.style.display = 'flex';

  // Scan time
  if (data.timestamp) {
    const elapsed = Date.now() - data.timestamp;
    const ms = elapsed < 1000 ? `${elapsed}ms` : `${(elapsed / 1000).toFixed(1)}s`;
    document.getElementById('scan-time').textContent = `Scanned ${ms} ago`;
  }
}

function renderGateways(gateways) {
  const list = document.getElementById('gateways-list');
  list.innerHTML = '';

  for (const gw of gateways) {
    const li = document.createElement('li');
    li.className = `gateway-item confidence-${gw.confidence || 'low'}`;

    const dot = document.createElement('span');
    dot.className = 'gateway-dot';
    dot.style.backgroundColor = gw.color || '#94a3b8';

    const name = document.createElement('span');
    name.className = 'gateway-name';
    name.textContent = gw.name;

    const badges = document.createElement('div');
    badges.className = 'gateway-badges';

    const pill = document.createElement('span');
    pill.className = `confidence-pill ${gw.confidence || 'low'}`;
    pill.textContent = (gw.confidence || 'low').charAt(0).toUpperCase() +
                       (gw.confidence || 'low').slice(1);

    const sigCount = document.createElement('span');
    sigCount.className = 'signal-count';
    const sigLen = (gw.signals || []).length;
    sigCount.textContent = `${sigLen} signal${sigLen !== 1 ? 's' : ''}`;

    badges.appendChild(pill);
    badges.appendChild(sigCount);

    li.appendChild(dot);
    li.appendChild(name);
    li.appendChild(badges);
    list.appendChild(li);
  }
}

function renderMethods(methods) {
  // Clear all chip containers
  const categories = ['card', 'wallet', 'bnpl', 'bank', 'crypto', 'other'];
  for (const cat of categories) {
    document.getElementById(`chips-${cat}`).innerHTML = '';
    document.getElementById(`group-${cat}`).classList.add('hidden');
  }

  for (const method of methods) {
    const cat = method.category || 'other';
    const container = document.getElementById(`chips-${cat}`) ||
                      document.getElementById('chips-other');
    const group = document.getElementById(`group-${cat}`) ||
                  document.getElementById('group-other');

    const chip = document.createElement('span');
    chip.className = `method-chip cat-${cat}`;

    const icon = METHOD_ICONS[method.id];
    if (icon) {
      const iconSpan = document.createElement('span');
      iconSpan.textContent = icon;
      chip.appendChild(iconSpan);
    }

    const label = document.createElement('span');
    label.textContent = method.name;
    chip.appendChild(label);

    container.appendChild(chip);
    group.classList.remove('hidden');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// STATE MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────
function showState(state) {
  const states = ['loading', 'error', 'empty', 'results'];
  for (const s of states) {
    const el = document.getElementById(`${s}-state`);
    if (el) el.classList.toggle('hidden', s !== state);
  }
  const footer = document.getElementById('footer');
  if (state !== 'results') footer.style.display = 'none';
}

function showError(message) {
  showState('error');
  document.getElementById('error-message').textContent = message;
}

// ─────────────────────────────────────────────────────────────────────────────
// MESSAGING HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function sendToBackground(message) {
  return new Promise((resolve) => {
    try {
      chrome.runtime.sendMessage(message, (response) => {
        if (chrome.runtime.lastError) {
          resolve(null);
        } else {
          resolve(response);
        }
      });
    } catch (e) {
      resolve(null);
    }
  });
}

function sendToContentScript(tabId, message) {
  return new Promise((resolve) => {
    try {
      chrome.tabs.sendMessage(tabId, message, (response) => {
        if (chrome.runtime.lastError) {
          resolve(null);
        } else {
          resolve(response);
        }
      });
    } catch (e) {
      resolve(null);
    }
  });
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}
