/**
 * LinkRise Frontend — Pure Vanilla JS (no React, no jQuery, no wp.element)
 * Compatible with every WordPress version and every theme.
 *
 * Exports three global functions that the PHP shortcodes call inline:
 *   LR_Generator(el, cfg)  — single-URL generator
 *   LR_Bulk(el, cfg)       — bulk URL generator
 *   LR_Landing(el, cfg)    — password / countdown landing page
 */
(function(global) {
  'use strict';

  /* ── Tiny DOM helper ────────────────────────────────────────────────── */
  function h(tag, attrs, children) {
    var el = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (k === 'class') { el.className = attrs[k]; }
        else if (k === 'html')  { el.innerHTML = attrs[k]; }
        else if (k.startsWith('on')) { el.addEventListener(k.slice(2), attrs[k]); }
        else { el.setAttribute(k, attrs[k]); }
      }
    }
    if (children) {
      (Array.isArray(children) ? children : [children]).forEach(function(c) {
        if (c == null) return;
        el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
      });
    }
    return el;
  }

  function esc(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function post(url, body, cb) {
    var fd = new FormData();
    for (var k in body) { if (body.hasOwnProperty(k)) fd.append(k, body[k] == null ? '' : body[k]); }
    fetch(url, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(cb)
      .catch(function(e) { cb({ success: false, data: { msg: 'Network error: ' + e.message } }); });
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
    return Promise.resolve();
  }

  /* ── reCAPTCHA helper ───────────────────────────────────────────────── */
  function rcExecute(siteKey, action, cb) {
    if (window.grecaptcha && window.grecaptcha.execute) {
      window.grecaptcha.execute(siteKey, { action: action }).then(cb).catch(function() { cb(''); });
    } else {
      // Wait up to 5s for script to load
      var attempts = 0;
      var t = setInterval(function() {
        attempts++;
        if (window.grecaptcha && window.grecaptcha.execute) {
          clearInterval(t);
          window.grecaptcha.execute(siteKey, { action: action }).then(cb).catch(function() { cb(''); });
        } else if (attempts > 50) { clearInterval(t); cb(''); }
      }, 100);
    }
  }

  /* ── Load external CAPTCHA scripts ─────────────────────────────────── */
  function loadCaptchaScript(cfg) {
    if (cfg.captcha === 'recaptcha' && cfg.rcSite && !window.grecaptcha) {
      var s = document.createElement('script');
      s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(cfg.rcSite);
      s.async = true; document.head.appendChild(s);
    }
    if (cfg.captcha === 'turnstile' && !window.turnstile) {
      var s2 = document.createElement('script');
      s2.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
      s2.async = true; document.head.appendChild(s2);
    }
  }

  /* ══════════════════════════════════════════════════════════════════════
   *  LR_Generator — single-URL shortener form
   * ══════════════════════════════════════════════════════════════════════ */
  function LR_Generator(root, cfg) {
    loadCaptchaScript(cfg);
    var tsWidget = null;

    function render(state) {
      root.innerHTML = '';

      if (state.result) {
        /* ── Success Screen ── */
        var enc = encodeURIComponent(state.result);
        var qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + enc;

        var copyBtn = h('button', { class: 'lr-btn-primary', onclick: function() {
          copyText(state.result).then(function() {
            copyBtn.textContent = '✅ Copied!';
            setTimeout(function() { copyBtn.textContent = '📋 Copy URL'; }, 2500);
          });
        }}, '📋 Copy URL');

        var qrImg = h('img', { src: qrSrc, alt: 'QR Code', class: 'lr-qr-img', onerror: function() { this.style.display='none'; } });

        root.appendChild(h('div', { class: 'lr-card lr-card-success' }, [
          h('div', { class: 'lr-success-ring' }, '✓'),
          h('h2', { class: 'lr-card-h2' }, '⚡ Link Ready!'),
          h('div', { class: 'lr-result-box' },
            h('a', { href: state.result, target: '_blank', rel: 'noopener noreferrer', class: 'lr-result-url' }, state.result)
          ),
          h('div', { class: 'lr-qr-wrap' }, qrImg),
          h('div', { class: 'lr-result-btns' }, [
            copyBtn,
            h('a', { href: qrSrc, download: 'qr.png', class: 'lr-btn-secondary' }, '⬇ QR Code'),
            h('button', { class: 'lr-btn-outline', onclick: function() { render({ result:'', err:'', loading:false, showPw:false }); } }, '+ New Link'),
          ]),
          h('div', { class: 'lr-share-row' }, [
            h('span', { class: 'lr-share-lbl' }, 'Share:'),
            h('a', { href:'https://twitter.com/intent/tweet?text='+encodeURIComponent('Check this out: '+state.result), target:'_blank', rel:'noopener noreferrer', class:'lr-share-x' }, '𝕏'),
            h('a', { href:'https://wa.me/?text='+encodeURIComponent(state.result), target:'_blank', rel:'noopener noreferrer', class:'lr-share-wa' }, '💬'),
            h('a', { href:'https://www.linkedin.com/sharing/share-offsite/?url='+enc, target:'_blank', rel:'noopener noreferrer', class:'lr-share-li' }, 'in'),
          ]),
          h('p', { class: 'lr-card-footer' }, ['Powered by ', h('strong', {}, 'LinkRise')]),
        ]));
        return;
      }

      /* ── Form Screen ── */
      var urlInp    = h('input',  { type:'url',           class:'lr-input', placeholder:'https://example.com/your-long-url', required:'required' });
      var codeInp   = h('input',  { type:'text',          class:'lr-input', placeholder:'my-promo (optional)' });
      var pwInp     = h('input',  { type: state.showPw ? 'text' : 'password', class:'lr-input lr-pw-inp', placeholder:'Optional password protection' });
      var pwToggle  = h('button', { type:'button', class:'lr-pw-tog', onclick: function() { render(Object.assign({}, state, { showPw: !state.showPw })); }}, state.showPw ? 'Hide' : 'Show');
      var expiryInp = h('input',  { type:'datetime-local', class:'lr-input' });
      var catInp    = h('input',  { type:'text',           class:'lr-input', placeholder:'e.g. marketing (optional)' });
      var submitBtn = h('button', { type:'submit', class:'lr-btn-primary lr-btn-full' }, state.loading ? '⏳ Generating…' : '⚡ Shorten URL');
      if (state.loading) { submitBtn.disabled = true; }

      var tsDiv = null;
      if (cfg.captcha === 'turnstile') {
        tsDiv = h('div', { class: 'lr-captcha-wrap' });
      }

      var tosRow = null;
      var tosChk = null;
      if (cfg.tosUrl) {
        tosChk = h('input', { type:'checkbox', id:'lr-tos', required:'required' });
        tosRow = h('div', { class: 'lr-tos-row' }, [
          tosChk,
          h('label', { for:'lr-tos', html: 'I agree to the <a href="'+esc(cfg.tosUrl)+'" target="_blank" rel="noopener noreferrer">Terms of Service</a>' }),
        ]);
      }

      function doSubmit(token) {
        var body = {
          action: 'linkrise_create',
          nonce:  cfg.nonce,
          longUrl:  urlInp.value.trim(),
          custom:   codeInp.value.trim(),
          password: pwInp.value,
          expiry:   expiryInp.value,
          category: catInp.value.trim(),
          captcha_token: token || '',
        };
        post(cfg.ajaxUrl, body, function(res) {
          if (res.success) {
            render({ result: res.data.url });
          } else {
            render(Object.assign({}, state, { loading: false, err: res.data && res.data.msg ? res.data.msg : 'Something went wrong. Please try again.' }));
          }
        });
      }

      var form = h('form', { onsubmit: function(e) {
        e.preventDefault();
        if (!urlInp.value.trim()) return;
        render(Object.assign({}, state, { loading: true, err: '' }));

        if (cfg.captcha === 'recaptcha' && cfg.rcSite) {
          rcExecute(cfg.rcSite, 'create', doSubmit);
        } else if (cfg.captcha === 'turnstile' && window.turnstile && tsWidget !== null) {
          doSubmit(window.turnstile.getResponse(tsWidget) || '');
        } else {
          doSubmit('');
        }
      }}, [
        state.err ? h('div', { class:'lr-error-box' }, '⚠ ' + state.err) : null,
        h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Destination URL *'), urlInp]),
        h('div', { class:'lr-row2' }, [
          h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Custom Code'), codeInp]),
          h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Password'), h('div', { class:'lr-pw-row' }, [pwInp, pwToggle])]),
        ]),
        h('div', { class:'lr-row2' }, [
          h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Expiry Date & Time'), expiryInp]),
          h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Category'), catInp]),
        ]),
        tosRow,
        tsDiv,
        submitBtn,
      ]);

      root.appendChild(h('div', { class:'lr-card' }, [
        h('h2', { class:'lr-card-h2' }, '⚡ LinkRise Generator'),
        h('p', { class:'lr-card-sub' }, 'Transform any URL into a powerful shortlink.'),
        form,
        h('p', { class:'lr-card-footer' }, ['Powered by ', h('strong', {}, 'LinkRise')]),
      ]));

      // Mount Turnstile widget
      if (tsDiv && cfg.captcha === 'turnstile') {
        function tryMountTS() {
          if (window.turnstile) {
            tsWidget = window.turnstile.render(tsDiv, { sitekey: cfg.tsSite });
          } else {
            setTimeout(tryMountTS, 200);
          }
        }
        tryMountTS();
      }
    }

    render({ result:'', err:'', loading:false, showPw:false });
  }

  /* ══════════════════════════════════════════════════════════════════════
   *  LR_Bulk — bulk URL generator
   * ══════════════════════════════════════════════════════════════════════ */
  function LR_Bulk(root, cfg) {
    loadCaptchaScript(cfg);

    function render(state) {
      root.innerHTML = '';

      if (state.results) {
        var rows = state.results.results.map(function(r) {
          return '<tr><td><a href="'+esc(r.short)+'" target="_blank" rel="noopener noreferrer">'+esc(r.short)+'</a></td><td title="'+esc(r.orig)+'">'+esc(r.orig.length>55?r.orig.slice(0,55)+'…':r.orig)+'</td></tr>';
        }).join('');

        var allUrls = state.results.results.map(function(r) { return r.short; }).join('\n');

        root.appendChild(h('div', { class:'lr-card lr-card-success' }, [
          h('div', { class:'lr-success-ring' }, '✓'),
          h('h2', { class:'lr-card-h2' }, state.results.results.length + ' Links Created!'),
          state.results.errors.length ? h('div', { class:'lr-error-box' }, '⚠ Skipped ' + state.results.errors.length + ' invalid URLs.') : null,
          h('div', { class:'lr-result-btns' }, [
            h('button', { class:'lr-btn-primary', onclick:function() { copyText(allUrls).then(function() { this.textContent='✅ Copied!'; setTimeout(function(){ this.textContent='📋 Copy All'; }.bind(this),2000); }.bind(this)); } }, '📋 Copy All'),
            h('button', { class:'lr-btn-secondary', onclick:function() {
              var csv='Short URL,Original URL\n'+state.results.results.map(function(r){return '"'+r.short+'","'+r.orig+'"';}).join('\n');
              var a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download='linkrise-bulk.csv';
              document.body.appendChild(a); a.click(); document.body.removeChild(a);
            }}, '⬇ CSV'),
            h('button', { class:'lr-btn-outline', onclick:function() { render({ results:null, err:'', loading:false }); } }, '+ More'),
          ]),
          h('div', { class:'lr-tbl-wrap', html:'<table class="lr-tbl"><thead><tr><th>Short URL</th><th>Original</th></tr></thead><tbody>'+rows+'</tbody></table>' }),
        ]));
        return;
      }

      var txtArea = h('textarea', { class:'lr-input lr-textarea', rows:'8', placeholder:'https://example.com/page-one\nhttps://example.com/page-two\nhttps://example.com/page-three' });
      var pwInp   = h('input',    { type:'password', class:'lr-input', placeholder:'Apply to all (optional)' });
      var expInp  = h('input',    { type:'datetime-local', class:'lr-input' });
      var btn     = h('button',   { type:'submit', class:'lr-btn-primary lr-btn-full' }, state.loading ? '⏳ Generating…' : '⚡ Generate All Links');
      if (state.loading) btn.disabled = true;

      root.appendChild(h('div', { class:'lr-card' }, [
        h('h2', { class:'lr-card-h2' }, '⚡ Bulk Generator'),
        h('p',  { class:'lr-card-sub' }, 'Paste one URL per line — shorten them all at once.'),
        h('form', { onsubmit: function(e) {
          e.preventDefault();
          var lines = txtArea.value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
          if (!lines.length) return;
          render(Object.assign({}, state, { loading: true, err: '' }));
          var body = {
            action: 'linkrise_bulk_create',
            nonce:  cfg.nonce,
            urls:   JSON.stringify(lines),
            password: pwInp.value,
            expiry: expInp.value,
            captcha_token: '',
          };
          if (cfg.captcha === 'recaptcha' && cfg.rcSite) {
            rcExecute(cfg.rcSite, 'bulk', function(t) { body.captcha_token = t; doPost(body); });
          } else { doPost(body); }
          function doPost(b) {
            post(cfg.ajaxUrl, b, function(res) {
              if (res.success) { render({ results: res.data }); }
              else { render(Object.assign({}, state, { loading:false, err: res.data&&res.data.msg?res.data.msg:'Failed.' })); }
            });
          }
        }}, [
          state.err ? h('div', { class:'lr-error-box' }, '⚠ ' + state.err) : null,
          h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'URLs (one per line) *'), txtArea]),
          h('div', { class:'lr-row2' }, [
            h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Password (optional)'), pwInp]),
            h('div', { class:'lr-field' }, [h('label', { class:'lr-lbl' }, 'Expiry (optional)'), expInp]),
          ]),
          btn,
        ]),
        h('p', { class:'lr-card-footer' }, ['Powered by ', h('strong', {}, 'LinkRise')]),
      ]));
    }

    render({ results:null, err:'', loading:false });
  }

  /* ══════════════════════════════════════════════════════════════════════
   *  LR_Landing — password-protected / countdown landing page
   * ══════════════════════════════════════════════════════════════════════ */
  function LR_Landing(root, cfg) {
    // GTM injection
    if (cfg.gtmId) {
      var s = document.createElement('script');
      s.innerHTML = '(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","'+cfg.gtmId+'");';
      document.head.appendChild(s);
    }

    var timer      = cfg.countdown;
    var intervalId = null;
    var destUrl    = cfg.target || '';

    function startCountdown(url) {
      destUrl = url;
      if (cfg.countdown <= 0) { window.location.href = destUrl; return; }
      render('countdown');
      intervalId = setInterval(function() {
        timer--;
        var bar = root.querySelector('#lr-cd-bar');
        var num = root.querySelector('#lr-cd-num');
        if (bar) bar.style.width = Math.max(0, Math.round((timer / cfg.countdown) * 100)) + '%';
        if (num) num.textContent = timer;
        if (timer <= 0) { clearInterval(intervalId); window.location.href = destUrl; }
      }, 1000);
    }

    function render(phase, state) {
      state = state || {};
      root.innerHTML = '';

      if (phase === 'countdown') {
        var pct = Math.round((timer / (cfg.countdown || 1)) * 100);
        root.appendChild(h('div', { class:'lr-landing-card' }, [
          h('div', { class:'lr-landing-ico' }, '⚡'),
          h('h2', { class:'lr-landing-h2' }, 'Redirecting…'),
          h('p',  { class:'lr-landing-sub' }, 'You will be redirected in'),
          h('div', { id:'lr-cd-num', class:'lr-cd-num' }, String(timer)),
          h('div', { class:'lr-cd-track' }, h('div', { id:'lr-cd-bar', class:'lr-cd-bar', style:'width:'+pct+'%' })),
          destUrl ? h('p', { class:'lr-dest-preview' }, 'To: ' + destUrl.slice(0, 60) + (destUrl.length > 60 ? '…' : '')) : null,
          h('button', { class:'lr-btn-primary', onclick: function() { clearInterval(intervalId); window.location.href = destUrl; } }, 'Go Now →'),
          reportBtn(),
        ]));
        return;
      }

      if (phase === 'password') {
        var pwInp   = h('input',  { type:'password', class:'lr-input lr-pw-inp', placeholder:'Enter password…', id:'lr-pw-inp', autocomplete:'current-password' });
        var showBtn = h('button', { type:'button', class:'lr-pw-tog', onclick:function(){
          pwInp.type = pwInp.type==='password' ? 'text' : 'password';
          showBtn.textContent = pwInp.type==='password' ? 'Show' : 'Hide';
        }}, 'Show');
        var unlockBtn = h('button', { class:'lr-btn-primary lr-btn-full', onclick: function() {
          var pwd = pwInp.value;
          if (!pwd) return;
          unlockBtn.textContent = '⏳ Checking…';
          unlockBtn.disabled = true;
          post(cfg.ajaxUrl, { action:'linkrise_verify_password', nonce:cfg.nonce, sc:cfg.sc, pwd:pwd }, function(res) {
            if (res.success && res.data && res.data.url) {
              startCountdown(res.data.url);
            } else {
              render('password', { err: res.data && res.data.msg ? res.data.msg : 'Incorrect password.' });
            }
          });
        }}, '🔓 Unlock Link');

        // Submit on Enter key
        pwInp.addEventListener('keydown', function(e) { if (e.key==='Enter') unlockBtn.click(); });

        root.appendChild(h('div', { class:'lr-landing-card' }, [
          h('div', { class:'lr-landing-ico' }, '🔒'),
          h('h2', { class:'lr-landing-h2' }, 'Password Protected'),
          h('p',  { class:'lr-landing-sub' }, 'Enter the password to access this link.'),
          state.err ? h('div', { class:'lr-error-box' }, '⚠ ' + state.err) : null,
          h('div', { class:'lr-pw-row' }, [pwInp, showBtn]),
          unlockBtn,
          reportBtn(),
        ]));
        return;
      }
    }

    function reportBtn() {
      var shown   = false;
      var wrap    = h('div', {});
      var link    = h('button', { class:'lr-report-link', onclick: function() {
        shown = !shown;
        if (shown) { showForm(); } else { wrap.innerHTML=''; wrap.appendChild(link); }
      }}, '🚩 Report this link');
      wrap.appendChild(link);

      function showForm() {
        var sel = h('select', { class:'lr-input' }, [
          h('option', { value:'' }, 'Select a reason…'),
          h('option', { value:'spam' }, 'Spam'),
          h('option', { value:'phishing' }, 'Phishing / Scam'),
          h('option', { value:'malware' }, 'Malware'),
          h('option', { value:'inappropriate' }, 'Inappropriate Content'),
          h('option', { value:'other' }, 'Other'),
        ]);
        var det = h('textarea', { class:'lr-input', rows:'3', placeholder:'Additional details (optional)' });
        var sub = h('button', { class:'lr-btn-primary', onclick: function() {
          if (!sel.value) return;
          sub.textContent='Submitting…'; sub.disabled=true;
          post(cfg.ajaxUrl, { action:'linkrise_report', nonce:cfg.nonce, sc:cfg.sc, url:destUrl, reason:sel.value, details:det.value }, function() {
            wrap.innerHTML = '<div class="lr-success-notice">✅ Report submitted. Thank you.</div>';
          });
        }}, 'Submit Report');
        wrap.innerHTML='';
        wrap.appendChild(h('div', { class:'lr-report-form' }, [sel, det, sub]));
      }
      return wrap;
    }

    // Start in correct phase
    if (cfg.hasPw) {
      render('password');
    } else if (cfg.target) {
      startCountdown(cfg.target);
    } else {
      root.innerHTML = '<div class="lr-landing-card"><p class="lr-error">⚠ Invalid link configuration.</p></div>';
    }
  }

  // Expose globals
  global.LR_Generator = LR_Generator;
  global.LR_Bulk      = LR_Bulk;
  global.LR_Landing   = LR_Landing;

})(window);
