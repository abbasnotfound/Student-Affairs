const SUPABASE_URL = "https://fuglhqavojfwxggsesam.supabase.co";

const SUPABASE_ANON_KEY =
    "sb_publishable_Wm5Ff2M5oceS1fOZmoArbQ_St1gLjWE";

const supabaseClient = supabase.createClient(
    SUPABASE_URL,
    SUPABASE_ANON_KEY
);// Front-end demo only: no data is sent to a server or saved.
const complaintForm = document.querySelector('#complaint-form');
const formMessage = document.querySelector('#form-message');
complaintForm.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!complaintForm.reportValidity()) return;
  formMessage.textContent = 'Demo only: your complaint was not saved. Connect a backend to receive and manage real submissions.';
});
const trackForm = document.querySelector('#track-form');
const trackMessage = document.querySelector('#track-message');
trackForm.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!trackForm.reportValidity()) return;
  trackMessage.textContent = 'Demo only: complaint tracking needs a connected database.';
});

