/**
 * Balochistan Minerals - Gated Lab Assay Certificate (COA) Vault & Beacon
 * Captures buyer details before unlocking verified SGS / PCSIR chemical certificates.
 * Also tracks visitor telemetry asynchronously to api/track-visitor.php.
 */

(function() {
  const vaultModalHTML = `
    <div id="bm-assay-modal" style="display:none; position:fixed; inset:0; z-index:999999; background:rgba(10, 16, 28, 0.88); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:1rem; opacity:0; transition:opacity 0.25s ease;">
      <div style="background:#0F172A; border:1px solid rgba(255,255,255,0.15); border-radius:14px; width:100%; max-width:540px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.8); overflow:hidden; transform:translateY(15px); transition:transform 0.25s ease; color:#F8FAFC; font-family:'Montserrat', -apple-system, sans-serif;" id="bm-assay-modal-card">
        
        <!-- Header -->
        <div style="padding:18px 24px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:space-between; background:#0B1320;">
          <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(230,92,26,0.15); border:1px solid rgba(230,92,26,0.3); display:flex; align-items:center; justify-content:center; color:#E65C1A; font-size:18px;">
              📄
            </div>
            <div>
              <div style="font-weight:800; font-size:0.96rem; color:#FFFFFF;">Unlock Official Chemical Assay (COA)</div>
              <div style="font-size:0.75rem; color:#94A3B8;">SGS Pakistan & PCSIR Certified Laboratory Analysis</div>
            </div>
          </div>
          <button type="button" id="bm-assay-modal-close" style="background:none; border:none; color:#94A3B8; font-size:24px; line-height:1; cursor:pointer; padding:4px 8px;">&times;</button>
        </div>

        <!-- Form -->
        <form id="bm-assay-vault-form" style="padding:22px 24px; margin:0;">
          <input type="hidden" name="source" value="COA Gated Vault">

          <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.78rem; color:#CBD5E1; line-height:1.4;">
            <span style="color:#F59E0B; font-weight:700;">★ Instant Access:</span> Complete your procurement verification to view composite assays for Chromite (47%+ Cr₂O₃), Barite (4.2+ SG), and Antimony (48%+ Sb).
          </div>

          <div style="margin-bottom:12px;">
            <label style="display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#CBD5E1; margin-bottom:4px;">Mineral Target Required *</label>
            <select name="mineral" id="bm-assay-mineral-select" style="width:100%; padding:9px 12px; background:#1E293B; border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:#FFFFFF; font-size:0.86rem; box-sizing:border-box;">
              <option value="Chromite Ore (46-48% Cr2O3)">Chromite Ore (46-48% Cr2O3 Lumps)</option>
              <option value="Barite API Spec 13A (4.2+ SG)">Barite (Oilfield API 13A / 4.2+ SG)</option>
              <option value="Antimony Ore (45-50% Sb)">Antimony Ore (45-50% High Purity Stibnite)</option>
              <option value="Copper Ore (20%+ Cu)">Copper Ore (Chagai Chalcopyrite 20%+)</option>
              <option value="Fluorite (Fluorspar CaF2)">Fluorite (Met Grade 85% / Acid 97%)</option>
              <option value="Iron Ore (62-64% Fe)">Iron Ore (Magnetite / Hematite 62-64% Fe)</option>
              <option value="All Minerals Comprehensive Assay Summary">All Minerals Comprehensive Summary</option>
            </select>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
            <div>
              <label style="display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#CBD5E1; margin-bottom:4px;">Full Name *</label>
              <input type="text" name="name" required placeholder="e.g. Dr. Pierre Dubois" style="width:100%; padding:9px 12px; background:#1E293B; border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:#FFFFFF; font-size:0.86rem; box-sizing:border-box;">
            </div>
            <div>
              <label style="display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#CBD5E1; margin-bottom:4px;">Company Name *</label>
              <input type="text" name="company" required placeholder="e.g. Ruhr Rohstoff GmbH" style="width:100%; padding:9px 12px; background:#1E293B; border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:#FFFFFF; font-size:0.86rem; box-sizing:border-box;">
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
            <div>
              <label style="display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#CBD5E1; margin-bottom:4px;">Work Email *</label>
              <input type="email" name="email" required placeholder="buyer@smelter.com" style="width:100%; padding:9px 12px; background:#1E293B; border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:#FFFFFF; font-size:0.86rem; box-sizing:border-box;">
            </div>
            <div>
              <label style="display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#CBD5E1; margin-bottom:4px;">WhatsApp Number *</label>
              <input type="text" name="whatsapp" required placeholder="+49 171 xxxxxxx" style="width:100%; padding:9px 12px; background:#1E293B; border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:#FFFFFF; font-size:0.86rem; box-sizing:border-box;">
            </div>
          </div>

          <button type="submit" id="bm-assay-submit-btn" style="width:100%; padding:12px; background:#E65C1A; color:#FFFFFF; border:none; border-radius:8px; font-weight:700; font-size:0.88rem; cursor:pointer; letter-spacing:0.04em; text-transform:uppercase; box-shadow:0 4px 14px rgba(230,92,26,0.4); display:flex; align-items:center; justify-content:center; gap:8px;">
            Unlock & View Lab Assay &rarr;
          </button>
        </form>

        <!-- Success & Download Screen -->
        <div id="bm-assay-success" style="display:none; padding:32px 24px; text-align:center;">
          <div style="width:50px; height:50px; border-radius:50%; background:rgba(34,197,94,0.15); border:2px solid #22C55E; color:#22C55E; font-size:24px; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
            ✓
          </div>
          <h4 style="color:#FFFFFF; font-weight:700; margin:0 0 6px; font-size:1.1rem;">Assay Unlocked Successfully</h4>
          <p style="color:#94A3B8; font-size:0.84rem; line-height:1.5; margin:0 0 18px;">
            The official Certificate of Analysis has been opened in a new tab. A copy has also been routed to your email.
          </p>
          <div style="display:flex; flex-direction:column; gap:8px; max-width:320px; margin:0 auto;">
            <a id="bm-assay-open-link" href="documents/Balochistan_Minerals_Assay_Summary.html" target="_blank" style="background:#0F172A; border:1px solid #E65C1A; color:#FFFFFF; padding:10px 18px; border-radius:6px; font-weight:700; font-size:0.82rem; text-decoration:none;">
              📄 Re-Open Assay Certificate (COA)
            </a>
            <a id="bm-assay-wa-link" href="https://wa.me/923348888104" target="_blank" style="background:#15803D; color:#FFFFFF; padding:10px 18px; border-radius:6px; font-weight:700; font-size:0.82rem; text-decoration:none;">
              💬 Discuss Stockpiles on WhatsApp
            </a>
          </div>
        </div>

      </div>
    </div>
  `;

  document.addEventListener('DOMContentLoaded', function() {
    // 1. Inject Assay Vault Modal
    document.body.insertAdjacentHTML('beforeend', vaultModalHTML);

    const modal = document.getElementById('bm-assay-modal');
    const modalContent = document.getElementById('bm-assay-modal-card');
    const closeBtn = document.getElementById('bm-assay-modal-close');
    const form = document.getElementById('bm-assay-vault-form');
    const successView = document.getElementById('bm-assay-success');
    const mineralSelect = document.getElementById('bm-assay-mineral-select');
    const openLink = document.getElementById('bm-assay-open-link');
    const waLink = document.getElementById('bm-assay-wa-link');

    function openAssayModal(mineralName) {
      if (mineralName && mineralSelect) {
        for (let i = 0; i < mineralSelect.options.length; i++) {
          if (mineralSelect.options[i].value.toLowerCase().includes(mineralName.toLowerCase())) {
            mineralSelect.selectedIndex = i;
            break;
          }
        }
      }
      form.style.display = 'block';
      successView.style.display = 'none';
      modal.style.display = 'flex';
      setTimeout(() => {
        modal.style.opacity = '1';
        modalContent.style.transform = 'translateY(0)';
      }, 10);
    }

    function closeAssayModal() {
      modal.style.opacity = '0';
      modalContent.style.transform = 'translateY(15px)';
      setTimeout(() => {
        modal.style.display = 'none';
      }, 250);
    }

    if (closeBtn) closeBtn.addEventListener('click', closeAssayModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeAssayModal();
    });

    // Attach click handlers to any .btn-open-assay-vault or [data-assay-mineral]
    document.addEventListener('click', function(e) {
      const trigger = e.target.closest('.btn-open-assay-vault, [data-assay-mineral]');
      if (trigger) {
        e.preventDefault();
        const min = trigger.getAttribute('data-assay-mineral') || '';
        openAssayModal(min);
      }
    });

    // Handle Form Submit
    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('bm-assay-submit-btn');
        btn.disabled = true;
        btn.textContent = 'Verifying & Unlocking...';

        const formData = new FormData(form);

        fetch('api/download-assay.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          btn.disabled = false;
          btn.textContent = 'Unlock & View Lab Assay →';

          if (data.success) {
            form.style.display = 'none';
            successView.style.display = 'block';
            if (data.download_url) openLink.href = data.download_url;
            if (data.whatsapp_url) waLink.href = data.whatsapp_url;
            window.open(data.download_url || 'documents/Balochistan_Minerals_Assay_Summary.html', '_blank');
          } else {
            alert(data.error || 'Verification failed. Please try again.');
          }
        })
        .catch(err => {
          btn.disabled = false;
          btn.textContent = 'Unlock & View Lab Assay →';
          // Fallback to opening assay directly
          window.open('documents/Balochistan_Minerals_Assay_Summary.html', '_blank');
          form.style.display = 'none';
          successView.style.display = 'block';
        });
      });
    }

    // 2. Asynchronous Live Visitor Telemetry Ping
    try {
      const hash = window.location.hash || '';
      const payload = {
        page: window.location.pathname + window.location.search,
        mineral: hash.replace('#', '') || '',
        referrer: document.referrer || 'Direct'
      };
      if (navigator.sendBeacon) {
        navigator.sendBeacon('api/track-visitor.php', JSON.stringify(payload));
      } else {
        fetch('api/track-visitor.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        }).catch(() => {});
      }
    } catch (e) {}
  });
})();
