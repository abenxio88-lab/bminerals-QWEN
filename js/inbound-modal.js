/**
 * Balochistan Minerals - Smart Inbound Email Modal
 * Intercepts mailto: clicks and presents an executive direct message popup,
 * ensuring all email inquiries land directly in the Marketing & Export Department portal.
 */

(function() {
  // Inject Modal HTML into DOM
  const modalHTML = `
    <div id="bm-email-modal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:1rem; opacity:0; transition:opacity 0.25s ease;">
      <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:14px; width:100%; max-width:520px; box-shadow:0 25px 50px -12px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(15, 23, 42, 0.05); overflow:hidden; transform:translateY(15px); transition:transform 0.25s ease; color:#0F172A; font-family:'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;" id="bm-email-modal-card">
        
        <!-- Header -->
        <div style="padding:18px 24px; border-bottom:1px solid #E2E8F0; display:flex; align-items:center; justify-content:space-between; background:#F8FAFC;">
          <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(230,92,26,0.1); border:1px solid rgba(230,92,26,0.25); display:flex; align-items:center; justify-content:center; color:#E65C1A; font-size:17px;">
              ✉
            </div>
            <div>
              <div style="font-weight:700; font-size:0.95rem; color:#0F172A; letter-spacing:-0.01em;">Direct Message to Marketing Department</div>
              <div style="font-size:0.75rem; color:#64748B; font-weight:500;" id="bm-modal-recipient-display">sales@balochistanminerals.com</div>
            </div>
          </div>
          <button type="button" id="bm-email-modal-close" style="background:none; border:none; color:#94A3B8; font-size:24px; line-height:1; cursor:pointer; padding:4px 8px; border-radius:6px; transition:color 0.15s ease;" onmouseover="this.style.color='#0F172A'" onmouseout="this.style.color='#94A3B8'">&times;</button>
        </div>

        <!-- Body Form -->
        <form id="bm-email-inbound-form" style="padding:22px 24px; margin:0; background:#FFFFFF;">
          <input type="hidden" name="source" value="Direct Email Modal">
          <input type="hidden" name="inquiry_type" value="Marketing Department Inquiry">
          <input type="hidden" id="bm-modal-recipient-input" name="recipient" value="sales@balochistanminerals.com">

          <div style="margin-bottom:14px;">
            <label style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#475569; margin-bottom:5px;">Your Full Name *</label>
            <input type="text" name="name" required placeholder="e.g. John Doe" style="width:100%; padding:10px 12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:6px; color:#0F172A; font-size:0.88rem; box-sizing:border-box; outline:none; transition:border-color 0.15s ease, box-shadow 0.15s ease;" onfocus="this.style.borderColor='#E65C1A'; this.style.boxShadow='0 0 0 3px rgba(230,92,26,0.1)';" onblur="this.style.borderColor='#CBD5E1'; this.style.boxShadow='none';">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div>
              <label style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#475569; margin-bottom:5px;">Corporate Email *</label>
              <input type="email" name="email" required placeholder="name@company.com" style="width:100%; padding:10px 12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:6px; color:#0F172A; font-size:0.88rem; box-sizing:border-box; outline:none; transition:border-color 0.15s ease, box-shadow 0.15s ease;" onfocus="this.style.borderColor='#E65C1A'; this.style.boxShadow='0 0 0 3px rgba(230,92,26,0.1)';" onblur="this.style.borderColor='#CBD5E1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#475569; margin-bottom:5px;">WhatsApp / Phone</label>
              <input type="text" name="phone" placeholder="+1 xxx xxxxxxx" style="width:100%; padding:10px 12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:6px; color:#0F172A; font-size:0.88rem; box-sizing:border-box; outline:none; transition:border-color 0.15s ease, box-shadow 0.15s ease;" onfocus="this.style.borderColor='#E65C1A'; this.style.boxShadow='0 0 0 3px rgba(230,92,26,0.1)';" onblur="this.style.borderColor='#CBD5E1'; this.style.boxShadow='none';">
            </div>
          </div>

          <div style="margin-bottom:14px;">
            <label style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#475569; margin-bottom:5px;">Company & Country</label>
            <input type="text" name="company" placeholder="e.g. Acme Industrial Ltd (Singapore)" style="width:100%; padding:10px 12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:6px; color:#0F172A; font-size:0.88rem; box-sizing:border-box; outline:none; transition:border-color 0.15s ease, box-shadow 0.15s ease;" onfocus="this.style.borderColor='#E65C1A'; this.style.boxShadow='0 0 0 3px rgba(230,92,26,0.1)';" onblur="this.style.borderColor='#CBD5E1'; this.style.boxShadow='none';">
          </div>

          <div style="margin-bottom:16px;">
            <label style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#475569; margin-bottom:5px;">Inquiry / Specifications *</label>
            <textarea name="message" required rows="3" placeholder="Tell us your required mineral, tonnage, target destination, or assay questions..." style="width:100%; padding:10px 12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:6px; color:#0F172A; font-size:0.88rem; box-sizing:border-box; resize:vertical; outline:none; transition:border-color 0.15s ease, box-shadow 0.15s ease; font-family:inherit;" onfocus="this.style.borderColor='#E65C1A'; this.style.boxShadow='0 0 0 3px rgba(230,92,26,0.1)';" onblur="this.style.borderColor='#CBD5E1'; this.style.boxShadow='none';"></textarea>
          </div>

          <button type="submit" id="bm-modal-submit-btn" style="width:100%; padding:12px; background:#E65C1A; color:#FFFFFF; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; letter-spacing:0.04em; text-transform:uppercase; box-shadow:0 4px 14px rgba(230,92,26,0.3); display:flex; align-items:center; justify-content:center; gap:8px; transition:background-color 0.15s ease, transform 0.15s ease;" onmouseover="this.style.backgroundColor='#C2410C'; this.style.transform='translateY(-1px)';" onmouseout="this.style.backgroundColor='#E65C1A'; this.style.transform='none';">
            Submit to Marketing Department &rarr;
          </button>

          <!-- Fallback direct mailto -->
          <div style="margin-top:14px; text-align:center; font-size:0.75rem; color:#64748B;">
            Prefer default mail program? <a href="#" id="bm-raw-mailto-link" style="color:#2563EB; text-decoration:underline; font-weight:500;">Click here to open in Outlook / Apple Mail</a>
          </div>
        </form>

        <!-- Success View -->
        <div id="bm-modal-success" style="display:none; padding:36px 24px; text-align:center; background:#FFFFFF;">
          <div style="width:52px; height:52px; border-radius:50%; background:rgba(34,197,94,0.12); border:2px solid #22C55E; color:#16A34A; font-size:24px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
            ✓
          </div>
          <h4 style="color:#0F172A; font-weight:700; margin:0 0 8px; font-size:1.1rem;">Message Received by Marketing Department</h4>
          <p style="color:#475569; font-size:0.88rem; line-height:1.55; margin:0 0 20px;">
            Your inquiry has been logged into our executive portal. Our marketing & export department will review your specifications and reply promptly.
          </p>
          <div style="display:flex; justify-content:center; gap:10px;">
            <a href="https://wa.me/923348888104" target="_blank" style="background:#15803D; color:#FFFFFF; padding:10px 18px; border-radius:6px; font-weight:700; font-size:0.82rem; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
              Chat on WhatsApp Directly
            </a>
            <button type="button" onclick="document.getElementById('bm-email-modal').style.display='none'" style="background:#F1F5F9; color:#334155; border:1px solid #CBD5E1; padding:10px 18px; border-radius:6px; font-weight:600; font-size:0.82rem; cursor:pointer;" onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='#F1F5F9'">
              Close
            </button>
          </div>
        </div>

      </div>
    </div>
  `;

  document.addEventListener('DOMContentLoaded', function() {
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('bm-email-modal');
    const modalContent = document.getElementById('bm-email-modal-card');
    const closeBtn = document.getElementById('bm-email-modal-close');
    const form = document.getElementById('bm-email-inbound-form');
    const successView = document.getElementById('bm-modal-success');
    const recipientDisplay = document.getElementById('bm-modal-recipient-display');
    const recipientInput = document.getElementById('bm-modal-recipient-input');
    const rawMailtoLink = document.getElementById('bm-raw-mailto-link');

    function openModal(emailAddress) {
      recipientDisplay.textContent = emailAddress;
      recipientInput.value = emailAddress;
      rawMailtoLink.href = 'mailto:' + emailAddress;

      form.style.display = 'block';
      successView.style.display = 'none';

      modal.style.display = 'flex';
      setTimeout(() => {
        modal.style.opacity = '1';
        modalContent.style.transform = 'translateY(0)';
      }, 10);
    }

    function closeModal() {
      modal.style.opacity = '0';
      modalContent.style.transform = 'translateY(15px)';
      setTimeout(() => {
        modal.style.display = 'none';
      }, 250);
    }

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeModal();
    });

    // Intercept all mailto links
    document.addEventListener('click', function(e) {
      const link = e.target.closest('a[href^="mailto:"]');
      if (link && link.id !== 'bm-raw-mailto-link') {
        e.preventDefault();
        const mailtoHref = link.getAttribute('href');
        const email = mailtoHref.replace(/^mailto:/i, '').split('?')[0].trim();
        openModal(email || 'sales@balochistanminerals.com');
      }
    });

    // Handle Form Submit
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const submitBtn = document.getElementById('bm-modal-submit-btn');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Submitting to Marketing Department...';

      const formData = new FormData(form);

      try {
        const res = await fetch('api/submit-inquiry.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          form.style.display = 'none';
          successView.style.display = 'block';
        } else {
          alert(data.error || 'Unable to submit inquiry. Please try again.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      } catch (err) {
        alert('Network error. Please try again or use direct WhatsApp.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  });
})();
