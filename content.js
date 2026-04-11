/**
 * Payment Gateway Detector - Content Script
 * Injected into every page (all frames) at document_idle.
 * Detects payment gateways and payment methods using multiple signals.
 */

'use strict';

// ─────────────────────────────────────────────────────────────────────────────
// GATEWAY DEFINITIONS
// Each entry defines signals used to detect the gateway's presence on a page.
// ─────────────────────────────────────────────────────────────────────────────
const GATEWAY_DEFINITIONS = [
  {
    id: 'stripe',
    name: 'Stripe',
    color: '#635BFF',
    website: 'stripe.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'applepay', 'googlepay'],
    signals: {
      scripts:      [/js\.stripe\.com\/v[23]/, /js\.stripe\.com\/basil/],
      globals:      ['Stripe', 'StripeV3'],
      domSelectors: ['.StripeElement', '[data-stripe]', '#stripe-payment-element',
                     '__PrivateStripeElement', '.stripe-element', '[class*="StripeElement"]'],
      formFields:   ['stripeToken', 'stripe_token', 'stripe-token'],
      iframeSrc:    [/js\.stripe\.com/, /hooks\.stripe\.com/],
      inlineScript: [/new Stripe\s*\(/, /Stripe\.setPublishableKey/, /stripe\.elements\s*\(/],
      linkHref:     [/js\.stripe\.com/]
    }
  },
  {
    id: 'paypal',
    name: 'PayPal',
    color: '#003087',
    website: 'paypal.com',
    methods: ['paypal', 'visa', 'mastercard', 'amex'],
    signals: {
      scripts:      [/paypalobjects\.com/, /pay\.paypal\.com/, /www\.paypal\.com\/sdk\/js/,
                     /www\.paypal\.com\/webapps\/merchantboarding/],
      globals:      ['paypal', 'PAYPAL'],
      domSelectors: ['.paypal-button', '[data-paypal-button]', '#paypal-button',
                     '.paypal-button-container', '#paypal-button-container',
                     '[class*="paypal"]', '[id*="paypal"]', 'paypal-button-v4'],
      formFields:   ['paypal_token', 'paypalToken'],
      iframeSrc:    [/paypal\.com/, /paypalobjects\.com/],
      inlineScript: [/paypal\.Buttons\s*\(/, /paypal\.render\s*\(/, /PAYPAL\.apps/]
    }
  },
  {
    id: 'square',
    name: 'Square',
    color: '#3E4348',
    website: 'squareup.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'applepay', 'googlepay'],
    signals: {
      scripts:      [/web\.squarecdn\.com/, /squareupsandbox\.com\/js/, /js\.squareup\.com/,
                     /squareup\.com\/js/],
      globals:      ['Square', 'SqPaymentForm'],
      domSelectors: ['#sq-card-number', '.sq-payment-form', '#sq-payment-form',
                     '[data-testid*="square"]', '.square-payment'],
      formFields:   ['sq_token', 'squareToken'],
      iframeSrc:    [/squareup\.com/, /squareupsandbox\.com/],
      inlineScript: [/new Square\.payments\s*\(/, /new SqPaymentForm\s*\(/, /Square\.payments\b/]
    }
  },
  {
    id: 'braintree',
    name: 'Braintree',
    color: '#1F3354',
    website: 'braintreepayments.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'paypal', 'applepay', 'googlepay'],
    signals: {
      scripts:      [/js\.braintreegateway\.com/, /assets\.braintreegateway\.com/],
      globals:      ['braintree'],
      domSelectors: ['[data-braintree-name]', '#braintree-container', '.braintree-hosted-field',
                     '[class*="braintree"]'],
      formFields:   ['payment_method_nonce', 'braintree_nonce'],
      iframeSrc:    [/braintreegateway\.com/, /paypal\.com.*braintree/],
      inlineScript: [/braintree\.client\.create\s*\(/, /braintree\.hostedFields\s*\(/]
    }
  },
  {
    id: 'adyen',
    name: 'Adyen',
    color: '#0ABF53',
    website: 'adyen.com',
    methods: ['visa', 'mastercard', 'amex', 'applepay', 'googlepay', 'klarna', 'paypal'],
    signals: {
      scripts:      [/checkoutshopper-live\.adyen\.com/, /checkoutshopper-test\.adyen\.com/,
                     /live\.adyen\.com/, /test\.adyen\.com/],
      globals:      ['AdyenCheckout'],
      domSelectors: ['.adyen-checkout', '[class*="adyen-checkout"]', '[data-testid*="adyen"]',
                     '#adyen-checkout', '.adyen-component'],
      formFields:   ['encryptedCardNumber', 'adyenEncrypted'],
      iframeSrc:    [/adyen\.com/],
      inlineScript: [/new AdyenCheckout\s*\(/, /AdyenCheckout\s*\(/]
    }
  },
  {
    id: 'authorizenet',
    name: 'Authorize.Net',
    color: '#007DC6',
    website: 'authorize.net',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'echeck'],
    signals: {
      scripts:      [/acceptjs\.authorize\.net/, /jstest\.authorize\.net/,
                     /js\.authorize\.net/],
      globals:      ['Accept', 'AuthorizeNetPopup'],
      domSelectors: ['#AcceptUIContainer', '#AcceptUIBackground', '[class*="AcceptUI"]',
                     '[data-apiLoginID]'],
      formFields:   ['dataDescriptor', 'dataValue', 'authnet_nonce'],
      iframeSrc:    [/authorize\.net/],
      inlineScript: [/Accept\.dispatchData\s*\(/, /AuthorizeNetPopup\.openAuthorizenetPopup/]
    }
  },
  {
    id: 'klarna',
    name: 'Klarna',
    color: '#FFB3C7',
    website: 'klarna.com',
    methods: ['klarna', 'visa', 'mastercard'],
    signals: {
      scripts:      [/js\.klarna\.com/, /x\.klarnacdn\.net/, /osm\.klarnaservices\.com/,
                     /klarna\.com\/us\/shopping\/osm/],
      globals:      ['Klarna'],
      domSelectors: ['klarna-placement', 'klarna-checkout', '.klarna-payment-category',
                     '[class*="klarna"]', '[data-testid*="klarna"]', '#klarna-checkout'],
      formFields:   ['klarna_order_id'],
      iframeSrc:    [/klarna\.com/, /klarnacdn\.net/],
      inlineScript: [/Klarna\.load\s*\(/, /Klarna\.Payments\.init\s*\(/]
    }
  },
  {
    id: 'shopify',
    name: 'Shopify Payments',
    color: '#96BF48',
    website: 'shopify.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'applepay', 'googlepay', 'paypal'],
    signals: {
      scripts:      [/pay\.shopify\.com/, /cdn\.shopify\.com\/s\/assets\/storefront/,
                     /cdn\.shopify\.com\/s\/javascripts\/shopify_pay/],
      globals:      ['Shopify', 'ShopifyBuy', 'ShopifyAnalytics'],
      domSelectors: ['#shopify-buy-btn', '.shopify-payment-button', '[data-shopify]',
                     '.shopify-buy__product', '#shopify-product-reviews',
                     '[class*="shopify-pay"]'],
      formFields:   ['checkout[payment_gateway]', 'authenticity_token'],
      iframeSrc:    [/pay\.shopify\.com/, /checkout\.shopify\.com/],
      inlineScript: [/Shopify\.checkout/, /Shopify\.theme/, /ShopifyBuy\.buildClient/]
    }
  },
  {
    id: 'razorpay',
    name: 'Razorpay',
    color: '#3395FF',
    website: 'razorpay.com',
    methods: ['visa', 'mastercard', 'amex', 'upi', 'netbanking', 'wallet'],
    signals: {
      scripts:      [/checkout\.razorpay\.com/, /api\.razorpay\.com\/v1/],
      globals:      ['Razorpay'],
      domSelectors: ['#razorpay-container', '#razorpay-payment-form',
                     '[class*="razorpay"]', '[data-key*="rzp_"]'],
      formFields:   ['razorpay_payment_id', 'razorpay_order_id'],
      iframeSrc:    [/razorpay\.com/],
      inlineScript: [/new Razorpay\s*\(/, /Razorpay\.open\s*\(/]
    }
  },
  {
    id: 'paytm',
    name: 'Paytm',
    color: '#00BAF2',
    website: 'paytm.com',
    methods: ['visa', 'mastercard', 'upi', 'wallet', 'netbanking'],
    signals: {
      scripts:      [/securegw\.paytm\.in/, /securegw-stage\.paytm\.in/,
                     /assets\.paytm\.com/],
      globals:      ['Paytm'],
      domSelectors: ['#paytm-checkoutjs', '[class*="paytm"]', '[id*="paytm"]'],
      formFields:   ['TXN_AMOUNT', 'ORDER_ID', 'paytm_token'],
      iframeSrc:    [/paytm\.in/, /paytm\.com/],
      inlineScript: [/new Paytm\s*\(/, /Paytm\.initialize\s*\(/]
    }
  },
  {
    id: '2checkout',
    name: '2Checkout / Verifone',
    color: '#FF6600',
    website: '2checkout.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'paypal'],
    signals: {
      scripts:      [/2checkout\.com\/checkout\/api/, /avangate\.com/,
                     /verifone\.com.*checkout/],
      globals:      ['TCO', 'TwoCheckout'],
      domSelectors: ['[class*="2co-"]', '[id*="2checkout"]', '[class*="verifone"]'],
      formFields:   ['li_type', '2co_order_number', 'merchant_order_id'],
      iframeSrc:    [/2checkout\.com/, /avangate\.com/],
      inlineScript: [/TCO\.checkout\s*\(/, /TwoCheckout\.checkout\s*\(/]
    }
  },
  {
    id: 'woocommerce',
    name: 'WooCommerce',
    color: '#7F54B3',
    website: 'woocommerce.com',
    methods: ['visa', 'mastercard', 'amex', 'paypal'],
    signals: {
      scripts:      [/woocommerce\b/, /wc-checkout/, /plugins\/woocommerce/],
      globals:      ['woocommerce_params', 'wc_checkout_params'],
      domSelectors: ['#woocommerce-checkout-payment', '.woocommerce-checkout',
                     '.woocommerce-payment-methods', '.wc_payment_method',
                     '[class*="woocommerce"]'],
      formFields:   ['payment_method', 'woocommerce-process-checkout-nonce'],
      iframeSrc:    [],
      inlineScript: [/woocommerce_params/, /wc_checkout_params/, /WC\.checkout/]
    }
  },
  {
    id: 'amazonpay',
    name: 'Amazon Pay',
    color: '#FF9900',
    website: 'pay.amazon.com',
    methods: ['visa', 'mastercard', 'amex', 'discover'],
    signals: {
      scripts:      [/static-na\.payments-amazon\.com/, /pay\.amazon\.com\/checkout\.js/,
                     /payments\.amazon\.com/, /amazon-pay-checkout\.js/],
      globals:      ['amazon', 'OffAmazonPayments'],
      domSelectors: ['#AmazonPayButton', '.amazonpay-button', '[data-amazon-pay]',
                     '[class*="amazon-pay"]', 'amazonpayments-button'],
      formFields:   ['amazon_order_reference_id', 'access_token'],
      iframeSrc:    [/amazon\.com.*pay/, /payments-amazon\.com/],
      inlineScript: [/OffAmazonPayments\.Button\s*\(/, /amazon\.Login\.authorize\s*\(/,
                     /new window\.amazon\.Pay/]
    }
  },
  {
    id: 'googlepay',
    name: 'Google Pay',
    color: '#4285F4',
    website: 'pay.google.com',
    methods: ['googlepay', 'visa', 'mastercard', 'amex', 'discover'],
    signals: {
      scripts:      [/pay\.google\.com\/gp\/p\/js/, /pay\.google\.com\/static\/js/],
      globals:      ['google'],
      domSelectors: ['.gpay-button', 'google-pay-button', '[class*="gpay"]',
                     '[class*="google-pay"]', '[data-google-pay]'],
      formFields:   ['google_pay_token'],
      iframeSrc:    [/pay\.google\.com/],
      inlineScript: [/google\.payments\.api/, /new google\.payments\.api\.PaymentsClient\s*\(/,
                     /PaymentsClient\b/]
    }
  },
  {
    id: 'applepay',
    name: 'Apple Pay',
    color: '#000000',
    website: 'apple.com',
    methods: ['applepay', 'visa', 'mastercard', 'amex', 'discover'],
    signals: {
      scripts:      [/applepay\.apple\.com/, /apple-pay\.js/],
      globals:      ['ApplePaySession'],
      domSelectors: ['.apple-pay-button', 'apple-pay-button', '[class*="apple-pay"]',
                     '[data-apple-pay]', '[onclick*="ApplePaySession"]'],
      formFields:   ['apple_pay_token', 'applePayToken'],
      iframeSrc:    [],
      inlineScript: [/new ApplePaySession\s*\(/, /ApplePaySession\.canMakePayments\s*\(/,
                     /ApplePaySession\.supportsVersion\s*\(/]
    }
  },
  {
    id: 'affirm',
    name: 'Affirm',
    color: '#0FA0EA',
    website: 'affirm.com',
    methods: ['affirm'],
    signals: {
      scripts:      [/cdn1\.affirm\.com/, /cdn-sandbox\.affirm\.com/],
      globals:      ['affirm'],
      domSelectors: ['.affirm-as-low-as', '[data-affirm-type]', '[data-affirm-color]',
                     '[class*="affirm"]', 'affirm-as-low-as'],
      formFields:   ['affirm_checkout_token'],
      iframeSrc:    [/affirm\.com/],
      inlineScript: [/affirm\.checkout\s*\(/, /affirm\.ui\.ready\s*\(/, /_affirm_config\b/]
    }
  },
  {
    id: 'afterpay',
    name: 'Afterpay / Clearpay',
    color: '#B2FCE4',
    website: 'afterpay.com',
    methods: ['afterpay'],
    signals: {
      scripts:      [/js\.afterpay\.com/, /portal\.afterpay\.com/, /js\.clearpay\.co\.uk/,
                     /portal\.clearpay\.co\.uk/],
      globals:      ['afterpay', 'AfterPay', 'Clearpay'],
      domSelectors: ['.afterpay-paragraph', '[data-afterpay]', '[class*="afterpay"]',
                     '[class*="clearpay"]', 'afterpay-placement', 'clearpay-placement'],
      formFields:   ['afterpay_token', 'clearpay_token'],
      iframeSrc:    [/afterpay\.com/, /clearpay\.co\.uk/],
      inlineScript: [/afterpay\.initialize\s*\(/, /AfterPay\.initialize\s*\(/,
                     /Clearpay\.initialize\s*\(/]
    }
  },
  {
    id: 'mollie',
    name: 'Mollie',
    color: '#FF6640',
    website: 'mollie.com',
    methods: ['visa', 'mastercard', 'amex', 'paypal', 'klarna', 'applepay'],
    signals: {
      scripts:      [/js\.mollie\.com/, /app\.mollie\.com/],
      globals:      ['Mollie'],
      domSelectors: ['.mollie-component', '[class*="mollie"]', '[data-mollie]',
                     '#mollie-checkout'],
      formFields:   ['mollie_card_token'],
      iframeSrc:    [/mollie\.com/],
      inlineScript: [/Mollie\s*\(/, /new Mollie\s*\(/]
    }
  },
  {
    id: 'worldpay',
    name: 'Worldpay',
    color: '#E31837',
    website: 'worldpay.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'applepay', 'googlepay'],
    signals: {
      scripts:      [/secure\.worldpay\.com/, /cdn\.worldpay\.com/,
                     /access\.worldpay\.com/],
      globals:      ['Worldpay', 'WPCL', 'WorldpayTokens'],
      domSelectors: ['#worldpay-access-checkout', '[class*="worldpay"]',
                     '[data-worldpay]', '#paymentSection'],
      formFields:   ['paymentToken', 'worldpay_token'],
      iframeSrc:    [/worldpay\.com/],
      inlineScript: [/Worldpay\.checkout\s*\(/, /WPCL\.Library\.checkout\s*\(/]
    }
  },
  {
    id: 'checkoutcom',
    name: 'Checkout.com',
    color: '#FF6640',
    website: 'checkout.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'applepay', 'googlepay'],
    signals: {
      scripts:      [/cdn\.checkout\.com/, /js\.checkout\.com/],
      globals:      ['Frames', 'CheckoutWebComponents'],
      domSelectors: ['[class*="checkout-com"]', '[data-checkout]', '.frames-container',
                     '#card-holder-name'],
      formFields:   ['cko-card-token', 'cko_card_token'],
      iframeSrc:    [/checkout\.com/],
      inlineScript: [/Frames\.init\s*\(/, /CheckoutWebComponents\s*\(/]
    }
  },
  {
    id: 'cybersource',
    name: 'Cybersource',
    color: '#0070AD',
    website: 'cybersource.com',
    methods: ['visa', 'mastercard', 'amex', 'discover'],
    signals: {
      scripts:      [/flex\.cybersource\.com/, /testflex\.cybersource\.com/,
                     /ebc2\.cybersource\.com/],
      globals:      ['FLEX', 'Cybersource'],
      domSelectors: ['[class*="cybersource"]', '#cybersource-payment-form',
                     '[data-cybersource]'],
      formFields:   ['token', 'flexToken'],
      iframeSrc:    [/cybersource\.com/],
      inlineScript: [/FLEX\.microform\s*\(/, /new FLEX\.Microform\s*\(/]
    }
  },
  {
    id: 'bolt',
    name: 'Bolt',
    color: '#FF6A00',
    website: 'bolt.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'applepay', 'googlepay', 'paypal'],
    signals: {
      scripts:      [/connect\.bolt\.com/, /cdn\.bolt\.com/],
      globals:      ['bolt'],
      domSelectors: ['.bolt-checkout-button', '[class*="bolt-"]', '[data-bolt]',
                     '#bolt-checkout'],
      formFields:   ['bolt_payment_token'],
      iframeSrc:    [/bolt\.com/],
      inlineScript: [/bolt\.configure\s*\(/, /bolt\.checkout\s*\(/]
    }
  },
  {
    id: 'recurly',
    name: 'Recurly',
    color: '#FF4C2B',
    website: 'recurly.com',
    methods: ['visa', 'mastercard', 'amex', 'discover'],
    signals: {
      scripts:      [/js\.recurly\.com/, /app\.recurly\.com/],
      globals:      ['recurly'],
      domSelectors: ['[data-recurly]', '.recurly-element', '[class*="recurly"]'],
      formFields:   ['recurly-token', 'recurly_token'],
      iframeSrc:    [/recurly\.com/],
      inlineScript: [/recurly\.configure\s*\(/, /recurly\.token\s*\(/]
    }
  },
  {
    id: 'zuora',
    name: 'Zuora',
    color: '#0A6FB4',
    website: 'zuora.com',
    methods: ['visa', 'mastercard', 'amex', 'discover', 'echeck'],
    signals: {
      scripts:      [/static\.zuora\.com/, /rest\.zuora\.com/,
                     /apisandbox\.zuora\.com/],
      globals:      ['Z'],
      domSelectors: ['[class*="zuora"]', '#zuora_payment', '[data-zuora]'],
      formFields:   ['token', 'zuoraPaymentToken'],
      iframeSrc:    [/zuora\.com/],
      inlineScript: [/Z\.render\s*\(/, /Z\.sendErrorMessageToHpm\s*\(/]
    }
  }
];

// ─────────────────────────────────────────────────────────────────────────────
// PAYMENT METHOD DEFINITIONS
// ─────────────────────────────────────────────────────────────────────────────
const PAYMENT_METHOD_DEFINITIONS = [
  {
    id: 'visa',
    name: 'Visa',
    category: 'card',
    patterns: [/\bvisa\b/i]
  },
  {
    id: 'mastercard',
    name: 'Mastercard',
    category: 'card',
    patterns: [/\bmastercard\b/i, /\bmaster\s*card\b/i]
  },
  {
    id: 'amex',
    name: 'American Express',
    category: 'card',
    patterns: [/\bamex\b/i, /\bamerican\s*express\b/i, /\bamericanexpress\b/i]
  },
  {
    id: 'discover',
    name: 'Discover',
    category: 'card',
    patterns: [/\bdiscover\b/i]
  },
  {
    id: 'jcb',
    name: 'JCB',
    category: 'card',
    patterns: [/\bjcb\b/i]
  },
  {
    id: 'dinersclub',
    name: 'Diners Club',
    category: 'card',
    patterns: [/\bdiners\s*club\b/i, /\bdiners\b/i]
  },
  {
    id: 'unionpay',
    name: 'UnionPay',
    category: 'card',
    patterns: [/\bunionpay\b/i, /\bcup\b/i, /\bunion\s*pay\b/i]
  },
  {
    id: 'paypal',
    name: 'PayPal',
    category: 'wallet',
    patterns: [/\bpaypal\b/i]
  },
  {
    id: 'applepay',
    name: 'Apple Pay',
    category: 'wallet',
    patterns: [/\bapple\s*pay\b/i, /\bapplepay\b/i]
  },
  {
    id: 'googlepay',
    name: 'Google Pay',
    category: 'wallet',
    patterns: [/\bgoogle\s*pay\b/i, /\bgooglepay\b/i, /\bgpay\b/i]
  },
  {
    id: 'amazonpay',
    name: 'Amazon Pay',
    category: 'wallet',
    patterns: [/\bamazon\s*pay\b/i, /\bamazonpay\b/i]
  },
  {
    id: 'klarna',
    name: 'Klarna',
    category: 'bnpl',
    patterns: [/\bklarna\b/i]
  },
  {
    id: 'afterpay',
    name: 'Afterpay / Clearpay',
    category: 'bnpl',
    patterns: [/\bafterpay\b/i, /\bclearpay\b/i]
  },
  {
    id: 'affirm',
    name: 'Affirm',
    category: 'bnpl',
    patterns: [/\baffirm\b/i]
  },
  {
    id: 'upi',
    name: 'UPI',
    category: 'bank',
    patterns: [/\bupi\b/i, /\bunified\s*payment\s*interface\b/i]
  },
  {
    id: 'netbanking',
    name: 'Net Banking',
    category: 'bank',
    patterns: [/\bnet\s*banking\b/i, /\bnetbanking\b/i, /\binternational\s*banking\b/i]
  },
  {
    id: 'banktransfer',
    name: 'Bank Transfer / ACH',
    category: 'bank',
    patterns: [/\bach\b/i, /\bsepa\b/i, /\bbank\s*transfer\b/i, /\bwire\s*transfer\b/i,
               /\bdirect\s*debit\b/i, /\bbacs\b/i]
  },
  {
    id: 'echeck',
    name: 'eCheck',
    category: 'bank',
    patterns: [/\becheck\b/i, /\be-check\b/i, /\belectronic\s*check\b/i]
  },
  {
    id: 'crypto',
    name: 'Cryptocurrency',
    category: 'crypto',
    patterns: [/\bbitcoin\b/i, /\bethereum\b/i, /\bcrypto\b/i, /\btether\b/i,
               /\busdc\b/i, /\bcryptocurrency\b/i, /\bbtc\b/i, /\beth\b/i]
  },
  {
    id: 'wallet',
    name: 'Digital Wallet',
    category: 'wallet',
    patterns: [/\bdigital\s*wallet\b/i, /\bmobile\s*wallet\b/i]
  }
];

// ─────────────────────────────────────────────────────────────────────────────
// DETECTOR ENGINE
// ─────────────────────────────────────────────────────────────────────────────
class DetectorEngine {
  constructor(doc) {
    this.doc = doc;
    this.detectedGateways = [];
    this.detectedMethods = new Set();
    this.pageGlobals = [];
  }

  // Inject a tiny script into the page world to probe window globals
  // (content scripts run in isolated world and can't read page JS vars)
  probePageGlobals() {
    return new Promise((resolve) => {
      const globalNames = GATEWAY_DEFINITIONS.flatMap(g => g.signals.globals);
      const uniqueGlobals = [...new Set(globalNames)];

      const handler = (e) => {
        this.pageGlobals = e.detail || [];
        resolve();
      };
      this.doc.addEventListener('__pgd_probe_result', handler, { once: true });

      try {
        const probe = this.doc.createElement('script');
        probe.textContent = `(function(){
          var names = ${JSON.stringify(uniqueGlobals)};
          var found = names.filter(function(n){ return typeof window[n] !== 'undefined'; });
          document.dispatchEvent(new CustomEvent('__pgd_probe_result', { detail: found }));
        })();`;
        (this.doc.head || this.doc.documentElement).appendChild(probe);
        probe.remove();
      } catch (e) {
        // CSP may block inline script injection — gracefully skip
        resolve();
      }

      // Timeout fallback: don't block detection if event never fires
      setTimeout(resolve, 300);
    });
  }

  // Collect script URLs from <script src>, <link rel=preload as=script>,
  // <link rel=prefetch>, and performance API
  getScriptUrls() {
    const urls = [];
    this.doc.querySelectorAll('script[src]').forEach(s => urls.push(s.src));
    this.doc.querySelectorAll('link[rel="preload"][as="script"], link[rel="prefetch"]')
      .forEach(l => l.href && urls.push(l.href));
    try {
      performance.getEntriesByType('resource')
        .filter(e => e.initiatorType === 'script')
        .forEach(e => urls.push(e.name));
    } catch (e) { /* not available in all contexts */ }
    return urls;
  }

  // Collect all link hrefs for preconnect/dns-prefetch detection
  getLinkUrls() {
    const urls = [];
    this.doc.querySelectorAll('link[href]').forEach(l => l.href && urls.push(l.href));
    return urls;
  }

  // Collect inline script text content
  getInlineScripts() {
    const texts = [];
    this.doc.querySelectorAll('script:not([src])').forEach(s => {
      if (s.textContent) texts.push(s.textContent);
    });
    return texts;
  }

  // Collect iframe src attributes
  getIframeSrcs() {
    const srcs = [];
    this.doc.querySelectorAll('iframe[src]').forEach(f => f.src && srcs.push(f.src));
    // Also check data-src (lazy loading pattern)
    this.doc.querySelectorAll('iframe[data-src]').forEach(f =>
      f.dataset.src && srcs.push(f.dataset.src)
    );
    return srcs;
  }

  // Walk shadow DOM to collect elements for DOM selector matching
  *walkDOM(root) {
    const walker = this.doc.createTreeWalker(root, NodeFilter.SHOW_ELEMENT);
    let node = walker.currentNode;
    while (node) {
      yield node;
      if (node.shadowRoot) {
        yield* this.walkDOM(node.shadowRoot);
      }
      node = walker.nextNode();
    }
  }

  // Check if any DOM element matches any of the selectors
  domMatches(selectors) {
    for (const selector of selectors) {
      try {
        // Standard querySelector (fast path)
        if (this.doc.querySelector(selector)) return true;
        // Also check tag name selectors (custom elements)
        if (!selector.startsWith('.') && !selector.startsWith('#') &&
            !selector.startsWith('[') && !selector.includes(' ')) {
          if (this.doc.querySelector(selector)) return true;
        }
      } catch (e) { /* invalid selector */ }
    }
    return false;
  }

  // Check form fields
  formFieldMatches(fieldNames) {
    for (const name of fieldNames) {
      try {
        if (this.doc.querySelector(`input[name="${name}"], input[name*="${name}"]`)) return true;
        if (this.doc.querySelector(`[name="${name}"]`)) return true;
      } catch (e) { /* */ }
    }
    return false;
  }

  // Score a gateway based on how many signal categories matched
  scoreGateway(gateway, scriptUrls, linkUrls, inlineScripts, iframeSrcs) {
    let score = 0;
    const matchedSignals = [];

    // 1. Script URL matching
    const allUrls = [...scriptUrls, ...linkUrls];
    for (const pattern of gateway.signals.scripts) {
      if (allUrls.some(u => pattern.test(u))) {
        score += 3; // Script URLs are high-confidence
        matchedSignals.push('script');
        break;
      }
    }

    // 2. Global variable matching (via probed page globals)
    for (const g of gateway.signals.globals) {
      if (this.pageGlobals.includes(g)) {
        score += 3;
        matchedSignals.push('global');
        break;
      }
    }

    // 3. DOM selector matching
    if (this.domMatches(gateway.signals.domSelectors)) {
      score += 2;
      matchedSignals.push('dom');
    }

    // 4. Form field matching
    if (gateway.signals.formFields && this.formFieldMatches(gateway.signals.formFields)) {
      score += 2;
      matchedSignals.push('form');
    }

    // 5. Iframe src matching
    if (gateway.signals.iframeSrc) {
      for (const pattern of gateway.signals.iframeSrc) {
        if (iframeSrcs.some(s => pattern.test(s))) {
          score += 2;
          matchedSignals.push('iframe');
          break;
        }
      }
    }

    // 6. Inline script pattern matching
    if (gateway.signals.inlineScript) {
      for (const pattern of gateway.signals.inlineScript) {
        if (inlineScripts.some(s => pattern.test(s))) {
          score += 2;
          matchedSignals.push('inline');
          break;
        }
      }
    }

    return { score, matchedSignals };
  }

  // Detect payment methods from page content
  detectPaymentMethods(html) {
    const lowerHtml = html.toLowerCase();
    const found = [];
    for (const method of PAYMENT_METHOD_DEFINITIONS) {
      for (const pattern of method.patterns) {
        if (pattern.test(lowerHtml)) {
          found.push(method);
          break;
        }
      }
    }
    return found;
  }

  // Aggregate methods from detected gateways
  methodsFromGateways(gateways) {
    const methodIds = new Set();
    for (const gw of gateways) {
      const def = GATEWAY_DEFINITIONS.find(g => g.id === gw.id);
      if (def && def.methods) {
        def.methods.forEach(m => methodIds.add(m));
      }
    }
    return [...methodIds].map(id =>
      PAYMENT_METHOD_DEFINITIONS.find(m => m.id === id)
    ).filter(Boolean);
  }

  async runAll() {
    // Probe page globals first (async)
    await this.probePageGlobals();

    const scriptUrls  = this.getScriptUrls();
    const linkUrls    = this.getLinkUrls();
    const inlineScripts = this.getInlineScripts();
    const iframeSrcs  = this.getIframeSrcs();
    const pageHtml    = this.doc.documentElement.innerHTML;

    // Detect gateways
    const gateways = [];
    for (const def of GATEWAY_DEFINITIONS) {
      const { score, matchedSignals } = this.scoreGateway(
        def, scriptUrls, linkUrls, inlineScripts, iframeSrcs
      );
      if (score > 0) {
        gateways.push({
          id: def.id,
          name: def.name,
          color: def.color,
          website: def.website,
          score,
          signals: matchedSignals,
          confidence: score >= 5 ? 'high' : score >= 3 ? 'medium' : 'low'
        });
      }
    }

    // Sort gateways by confidence score (highest first)
    gateways.sort((a, b) => b.score - a.score);

    // Detect payment methods: from page content + from gateways
    const methodsFromPage     = this.detectPaymentMethods(pageHtml);
    const methodsFromGateways = this.methodsFromGateways(gateways);

    // Merge and deduplicate methods
    const allMethodIds = new Set();
    const allMethods = [];
    for (const m of [...methodsFromPage, ...methodsFromGateways]) {
      if (!allMethodIds.has(m.id)) {
        allMethodIds.add(m.id);
        allMethods.push({ id: m.id, name: m.name, category: m.category });
      }
    }

    return {
      url: location.href,
      hostname: location.hostname,
      gateways,
      methods: allMethods,
      timestamp: Date.now(),
      frameId: window === window.top ? 'top' : 'sub'
    };
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-RUN ON PAGE LOAD
// Run detection once the DOM is idle, cache results, and push to background.
// ─────────────────────────────────────────────────────────────────────────────
let cachedResults = null;
let debounceTimer = null;

async function runDetection() {
  const engine = new DetectorEngine(document);
  cachedResults = await engine.runAll();

  // Push results to background service worker for aggregation
  try {
    chrome.runtime.sendMessage({
      type: 'FRAME_RESULTS',
      data: cachedResults
    });
  } catch (e) {
    // Extension context may be invalidated on SPA navigation — silently ignore
  }

  return cachedResults;
}

// Initial run
runDetection();

// Watch for dynamically added payment scripts (SPA support)
try {
  const observer = new MutationObserver((mutations) => {
    const hasNewScripts = mutations.some(m =>
      [...m.addedNodes].some(n =>
        n.nodeName === 'SCRIPT' || n.nodeName === 'LINK' || n.nodeName === 'IFRAME'
      )
    );
    if (hasNewScripts) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => runDetection(), 800);
    }
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });
} catch (e) { /* MutationObserver not available */ }

// ─────────────────────────────────────────────────────────────────────────────
// MESSAGE LISTENER
// Popup or background can request detection results on demand.
// ─────────────────────────────────────────────────────────────────────────────
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg.type === 'DETECT_PAYMENTS') {
    if (cachedResults) {
      sendResponse(cachedResults);
    } else {
      runDetection().then(results => sendResponse(results));
    }
    return true; // Keep channel open for async response
  }

  if (msg.type === 'RESCAN') {
    runDetection().then(results => sendResponse(results));
    return true;
  }
});
