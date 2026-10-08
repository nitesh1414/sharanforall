/**
 * Sharan Foundation — PWA Bootstrapper
 *
 * Responsibilities:
 *   1. Register the service worker
 *   2. Show a custom "Install App" banner when the browser fires beforeinstallprompt
 *   3. Show iOS users a separate guide (Safari blocks beforeinstallprompt)
 *   4. Detect online/offline state and show a small toast
 *   5. Update users when a new SW version is ready
 *
 * Add to any page with: <script src="/js/pwa.js" defer></script>
 */

(function() {
  'use strict';

  if (!('serviceWorker' in navigator)) return;

  /* =====================================================================
     1. REGISTER SERVICE WORKER
     ===================================================================== */
  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      console.log('[PWA] Service Worker registered:', reg.scope);

      // Watch for an updated worker
      reg.addEventListener('updatefound', () => {
        const newSW = reg.installing;
        if (!newSW) return;
        newSW.addEventListener('statechange', () => {
          if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
            // A new version is ready — show update banner
            showUpdateBanner(newSW);
          }
        });
      });
    } catch (err) {
      console.warn('[PWA] SW registration failed:', err);
    }
  });

  // When a new SW takes control, reload once so the page uses fresh assets
  let refreshing = false;
  navigator.serviceWorker?.addEventListener('controllerchange', () => {
    if (refreshing) return;
    refreshing = true;
    window.location.reload();
  });

  /* =====================================================================
     2. INSTALL PROMPT (Chrome, Edge, Android)
     ===================================================================== */
  let deferredPrompt = null;
  const INSTALL_DISMISSED_KEY = 'sharan_install_dismissed_at';
  const RE_PROMPT_DAYS = 14;  // re-show after this many days

  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    // Only show if user hasn't recently dismissed
    const lastDismissed = +(localStorage.getItem(INSTALL_DISMISSED_KEY) || 0);
    const daysSince = (Date.now() - lastDismissed) / (1000 * 60 * 60 * 24);
    if (lastDismissed && daysSince < RE_PROMPT_DAYS) return;
    // Wait 3 seconds after page load so we don't interrupt first impression
    setTimeout(() => showInstallBanner(), 3000);
  });

  window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed!');
    hideInstallBanner();
    showToast('🎉 Sharan Foundation app installed!', 'success');
    deferredPrompt = null;
  });

  /* =====================================================================
     3. iOS SAFARI HANDLER (no beforeinstallprompt support)
     ===================================================================== */
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  if (isIOS && !isStandalone) {
    const lastDismissed = +(localStorage.getItem(INSTALL_DISMISSED_KEY) || 0);
    const daysSince = (Date.now() - lastDismissed) / (1000 * 60 * 60 * 24);
    if (!lastDismissed || daysSince >= RE_PROMPT_DAYS) {
      setTimeout(() => showIOSInstallGuide(), 5000);
    }
  }

  /* =====================================================================
     4. ONLINE/OFFLINE TOAST
     ===================================================================== */
  let wasOffline = !navigator.onLine;
  window.addEventListener('online',  () => {
    if (wasOffline) showToast('✓ Back online', 'success');
    wasOffline = false;
  });
  window.addEventListener('offline', () => {
    wasOffline = true;
    showToast('📡 You are offline — form submissions will sync when you reconnect.', 'warning', 6000);
  });

  /* =====================================================================
     UI HELPERS
     ===================================================================== */

  function showInstallBanner() {
    if (document.getElementById('pwa-install-banner') || isStandalone) return;
    const html = `
      <div id="pwa-install-banner" role="dialog" aria-label="Install Sharan Foundation App">
        <div class="pwa-banner-inner">
          <img src="/icons/icon-192.png" alt="" class="pwa-banner-icon" onerror="this.style.display='none'">
          <div class="pwa-banner-text">
            <strong>Install Sharan Foundation</strong>
            <small>Add to your home screen for quick access and offline support.</small>
          </div>
          <div class="pwa-banner-actions">
            <button class="pwa-btn pwa-btn-primary" id="pwa-install-yes">Install</button>
            <button class="pwa-btn pwa-btn-ghost"   id="pwa-install-no" aria-label="Dismiss">✕</button>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    document.getElementById('pwa-install-yes').addEventListener('click', triggerInstall);
    document.getElementById('pwa-install-no').addEventListener('click', () => {
      localStorage.setItem(INSTALL_DISMISSED_KEY, Date.now());
      hideInstallBanner();
    });
  }

  async function triggerInstall() {
    if (!deferredPrompt) { hideInstallBanner(); return; }
    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    if (choice.outcome === 'dismissed') {
      localStorage.setItem(INSTALL_DISMISSED_KEY, Date.now());
    }
    hideInstallBanner();
    deferredPrompt = null;
  }

  function hideInstallBanner() {
    const el = document.getElementById('pwa-install-banner');
    if (!el) return;
    el.classList.add('pwa-leaving');
    setTimeout(() => el.remove(), 350);
  }

  function showIOSInstallGuide() {
    if (document.getElementById('pwa-ios-banner')) return;
    const html = `
      <div id="pwa-ios-banner" role="dialog" aria-label="Install on iPhone/iPad">
        <div class="pwa-banner-inner">
          <img src="/icons/icon-192.png" alt="" class="pwa-banner-icon" onerror="this.style.display='none'">
          <div class="pwa-banner-text">
            <strong>Install on iPhone/iPad</strong>
            <small>Tap <span class="ios-icon">⎙</span> then <strong>"Add to Home Screen"</strong></small>
          </div>
          <div class="pwa-banner-actions">
            <button class="pwa-btn pwa-btn-ghost" id="pwa-ios-close" aria-label="Dismiss">✕</button>
          </div>
        </div>
        <div class="pwa-banner-arrow"></div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    document.getElementById('pwa-ios-close').addEventListener('click', () => {
      localStorage.setItem(INSTALL_DISMISSED_KEY, Date.now());
      const el = document.getElementById('pwa-ios-banner');
      if (el) { el.classList.add('pwa-leaving'); setTimeout(() => el.remove(), 350); }
    });
  }

  function showUpdateBanner(newSW) {
    if (document.getElementById('pwa-update-banner')) return;
    const html = `
      <div id="pwa-update-banner" role="alert">
        <div class="pwa-banner-inner">
          <span style="font-size:1.4rem">🆕</span>
          <div class="pwa-banner-text">
            <strong>New version available</strong>
            <small>Refresh to get the latest content.</small>
          </div>
          <div class="pwa-banner-actions">
            <button class="pwa-btn pwa-btn-primary" id="pwa-update-yes">Update</button>
            <button class="pwa-btn pwa-btn-ghost"   id="pwa-update-no">Later</button>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    document.getElementById('pwa-update-yes').addEventListener('click', () => {
      newSW.postMessage('SKIP_WAITING');
    });
    document.getElementById('pwa-update-no').addEventListener('click', () => {
      document.getElementById('pwa-update-banner')?.remove();
    });
  }

  function showToast(message, type = 'info', duration = 3500) {
    const toast = document.createElement('div');
    toast.className = `pwa-toast pwa-toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('pwa-toast-show'));
    setTimeout(() => {
      toast.classList.remove('pwa-toast-show');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  /* =====================================================================
     INJECT STYLES — keeps this file self-contained, no extra CSS file needed
     ===================================================================== */
  const css = `
    #pwa-install-banner, #pwa-ios-banner, #pwa-update-banner {
      position:fixed;left:50%;bottom:1rem;transform:translateX(-50%) translateY(0);
      width:min(96vw,520px);background:#fff;border-radius:16px;
      box-shadow:0 14px 50px rgba(13,41,64,.35);z-index:99999;
      animation:pwa-slide-up .4s cubic-bezier(.22,1,.36,1);
      border:1px solid rgba(37,99,235,.15);
    }
    .pwa-leaving{animation:pwa-slide-down .35s ease forwards !important}
    @keyframes pwa-slide-up{from{opacity:0;transform:translateX(-50%) translateY(40px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
    @keyframes pwa-slide-down{to{opacity:0;transform:translateX(-50%) translateY(40px)}}
    .pwa-banner-inner{display:flex;align-items:center;gap:.85rem;padding:.9rem 1rem}
    .pwa-banner-icon{width:44px;height:44px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#2563eb,#f4a261)}
    .pwa-banner-text{flex:1;min-width:0;line-height:1.35}
    .pwa-banner-text strong{display:block;color:#0d2940;font-size:.94rem;font-weight:700}
    .pwa-banner-text small{display:block;color:#6c757d;font-size:.82rem;margin-top:.15rem}
    .pwa-banner-actions{display:flex;gap:.4rem;flex-shrink:0}
    .pwa-btn{border:none;padding:.55rem 1rem;border-radius:8px;font-weight:600;font-size:.85rem;cursor:pointer;font-family:inherit;transition:.2s}
    .pwa-btn-primary{background:#2563eb;color:#fff}
    .pwa-btn-primary:hover{background:#1d4ed8;transform:translateY(-1px)}
    .pwa-btn-ghost{background:transparent;color:#6c757d;padding:.55rem .8rem}
    .pwa-btn-ghost:hover{background:#f1f4f6;color:#0d2940}
    .ios-icon{display:inline-block;background:#e8f1ff;color:#2563eb;padding:.05rem .35rem;border-radius:4px;font-weight:700}
    #pwa-ios-banner .pwa-banner-arrow{position:absolute;bottom:-10px;left:50%;transform:translateX(-50%);width:0;height:0;border:10px solid transparent;border-top-color:#fff;display:none}
    @media (max-width:520px){
      #pwa-install-banner,#pwa-ios-banner,#pwa-update-banner{bottom:.6rem;width:96vw}
      .pwa-banner-text small{font-size:.78rem}
    }

    .pwa-toast{
      position:fixed;left:50%;bottom:1.5rem;transform:translateX(-50%) translateY(60px);
      background:#0d2940;color:#fff;padding:.85rem 1.4rem;border-radius:50px;
      font-size:.9rem;font-weight:500;box-shadow:0 10px 30px rgba(0,0,0,.3);
      z-index:99998;opacity:0;transition:.35s cubic-bezier(.22,1,.36,1);
      max-width:90vw;text-align:center;
    }
    .pwa-toast-show{opacity:1;transform:translateX(-50%) translateY(0)}
    .pwa-toast-success{background:#10b981}
    .pwa-toast-warning{background:#f4a261;color:#0d2940}
    .pwa-toast-error  {background:#ef4444}
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);
})();
