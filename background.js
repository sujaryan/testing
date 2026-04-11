/**
 * Payment Gateway Detector - Background Service Worker
 *
 * Accumulates detection results pushed by content scripts from all frames,
 * merges them, and serves them to the popup on request.
 */

'use strict';

// In-memory cache: tabId → merged detection results
const tabCache = new Map();

/**
 * Merge new frame results into the existing cache for a tab.
 * Deduplicates gateways and methods by id, keeping highest-score gateway.
 */
function mergeResults(existing, incoming) {
  // Merge gateways
  const gatewayMap = new Map();
  for (const gw of (existing.gateways || [])) {
    gatewayMap.set(gw.id, gw);
  }
  for (const gw of (incoming.gateways || [])) {
    const current = gatewayMap.get(gw.id);
    if (!current || gw.score > current.score) {
      gatewayMap.set(gw.id, gw);
    }
  }

  // Merge methods
  const methodMap = new Map();
  for (const m of (existing.methods || [])) {
    methodMap.set(m.id, m);
  }
  for (const m of (incoming.methods || [])) {
    if (!methodMap.has(m.id)) {
      methodMap.set(m.id, m);
    }
  }

  return {
    url: existing.url || incoming.url,
    hostname: existing.hostname || incoming.hostname,
    gateways: [...gatewayMap.values()].sort((a, b) => b.score - a.score),
    methods: [...methodMap.values()],
    timestamp: incoming.timestamp || existing.timestamp,
    frameCount: (existing.frameCount || 1) + 1
  };
}

// Listen for results pushed by content scripts (from all frames)
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  // Content script pushing its frame results
  if (msg.type === 'FRAME_RESULTS' && sender.tab) {
    const tabId = sender.tab.id;
    const existing = tabCache.get(tabId) || { gateways: [], methods: [], frameCount: 0 };
    tabCache.set(tabId, mergeResults(existing, msg.data));
    return;
  }

  // Popup requesting cached results
  if (msg.type === 'GET_CACHED_RESULTS') {
    const tabId = msg.tabId;
    const cached = tabCache.get(tabId);
    if (cached) {
      sendResponse({ found: true, data: cached });
    } else {
      // No cache yet — tell popup to ask the content script directly
      sendResponse({ found: false });
    }
    return true;
  }

  // Popup requesting a forced rescan (relays RESCAN to the active tab)
  if (msg.type === 'RESCAN') {
    const tabId = msg.tabId;
    // Clear cache so fresh results accumulate
    tabCache.delete(tabId);
    chrome.tabs.sendMessage(tabId, { type: 'RESCAN' }, (response) => {
      if (chrome.runtime.lastError) {
        // Content script not ready — inject it
        chrome.scripting.executeScript(
          { target: { tabId }, files: ['content.js'] },
          () => {
            setTimeout(() => {
              chrome.tabs.sendMessage(tabId, { type: 'DETECT_PAYMENTS' }, (r) => {
                sendResponse(r || { gateways: [], methods: [] });
              });
            }, 500);
          }
        );
      } else {
        sendResponse(response || { gateways: [], methods: [] });
      }
    });
    return true;
  }
});

// Clear cache when a tab navigates to a new page
chrome.tabs.onUpdated.addListener((tabId, changeInfo) => {
  if (changeInfo.status === 'loading') {
    tabCache.delete(tabId);
  }
});

// Clear cache when a tab is closed
chrome.tabs.onRemoved.addListener((tabId) => {
  tabCache.delete(tabId);
});
