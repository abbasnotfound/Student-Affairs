// Front-end demo only: records stay in this browser and are not sent to a server.
const STORAGE_KEY = 'studentAffairsDemoComplaints';
const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

function readComplaints() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    return Array.isArray(saved) ? saved : [];
  } catch {
    return [];
  }
}

function saveComplaints(complaints) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(complaints));
}

function generateTrackingCode(existingCodes) {
  let code;
  do {
    let suffix = '';
    for (let i = 0; i < 6; i++) {
      suffix += CODE_CHARS[Math.floor(Math.random() * CODE_CHARS.length)];
    }
    code = `SA-${suffix}`;
  } while (existingCodes.has(code));
  return code;
}

const complaintForm = document.querySelector('#complaint-form');
const formMessage = document.querySelector('#form-message');

complaintForm.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!complaintForm.reportValidity()) return;

  const complaints = readComplaints();
  const existingCodes = new Set(complaints.map(item => item.code));
  const code = generateTrackingCode(existingCodes);

  // Demo tracking record only. The complaint details and email are not transmitted.
  complaints.push({
    code,
    status: 'Received (demo)',
    createdAt: new Date().toISOString()
  });

  try {
    saveComplaints(complaints);
    formMessage.textContent = `Demo submission complete. Your tracking code is ${code}. Save this code to check its demo status in this same browser. No email was sent and no complaint was sent to college staff.`;
  } catch {
    formMessage.textContent = 'Could not save the demo tracking code in this browser. Check browser storage settings and try again.';
  }
});

const trackForm = document.querySelector('#track-form');
const trackMessage = document.querySelector('#track-message');
const codeInput = trackForm.querySelector('input[name="code"]');

codeInput.maxLength = 9;
codeInput.placeholder = 'e.g. SA-8K4P2M';
codeInput.autocomplete = 'off';
codeInput.addEventListener('input', () => {
  codeInput.value = codeInput.value.toUpperCase().replace(/\s+/g, '');
});

trackForm.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!trackForm.reportValidity()) return;

  const code = codeInput.value.trim().toUpperCase();
  const complaint = readComplaints().find(item => item.code === code);

  if (!complaint) {
    trackMessage.textContent = 'Tracking code not found in this browser. Make sure you use the exact code generated here, and open the site in the same browser and device where you submitted the demo form.';
    return;
  }

  trackMessage.textContent = `Tracking code: ${complaint.code} — Status: ${complaint.status}. This is demo status only; no college system has received the complaint.`;
});
