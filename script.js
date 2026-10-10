// ================================
// SUPABASE CONFIGURATION
// ================================

const SUPABASE_URL = "PASTE_YOUR_SUPABASE_URL_HERE";
const SUPABASE_ANON_KEY = "PASTE_YOUR_SUPABASE_ANON_KEY_HERE";

const supabaseClient = supabase.createClient(
    SUPABASE_URL,
    SUPABASE_ANON_KEY
);


// ================================
// COMPLAINT FORM
// ================================

const complaintForm = document.querySelector("#complaint-form");
const formMessage = document.querySelector("#form-message");

complaintForm.addEventListener("submit", async (event) => {

    event.preventDefault();

    if (!complaintForm.reportValidity()) {
        return;
    }

    const formData = new FormData(complaintForm);

    const studentName = formData.get("name").trim();
    const studentId = formData.get("studentId").trim();
    const className = formData.get("className").trim();
    const section = formData.get("section").trim();
    const email = formData.get("email").trim();
    const category = formData.get("category");
    const subject = formData.get("subject").trim();
    const description = formData.get("description").trim();

    formMessage.textContent = "Submitting complaint...";

    // Generate tracking code
    const trackingCode =
        "SA-" +
        Math.random().toString(36).substring(2, 8).toUpperCase();

    try {

        const { data, error } = await supabaseClient
            .from("complaints")
            .insert([
                {
                    tracking_code: trackingCode,
                    student_name: studentName,
                    student_id: studentId,
                    class_name: className,
                    section: section,
                    email: email || null,
                    category: category,
                    subject: subject,
                    description: description,
                    status: "Pending"
                }
            ])
            .select();

        if (error) {
            console.error(error);
            throw error;
        }

        formMessage.innerHTML = `
            <strong>✓ Complaint submitted successfully!</strong><br><br>
            Your tracking code is:
            <strong>${trackingCode}</strong><br><br>
            Please save this code so you can check your complaint status later.
        `;

        complaintForm.reset();

    } catch (error) {

        console.error(error);

        formMessage.textContent =
            "Something went wrong while submitting your complaint. Please try again.";

    }

});


// ================================
// TRACK COMPLAINT
// ================================

const trackForm = document.querySelector("#track-form");
const trackMessage = document.querySelector("#track-message");

trackForm.addEventListener("submit", async (event) => {

    event.preventDefault();

    if (!trackForm.reportValidity()) {
        return;
    }

    const formData = new FormData(trackForm);

    const code = formData
        .get("code")
        .trim()
        .toUpperCase();

    trackMessage.textContent = "Checking complaint...";

    try {

        const { data, error } = await supabaseClient
            .from("complaints")
            .select("tracking_code, category, subject, status, created_at, updated_at")
            .eq("tracking_code", code)
            .single();

        if (error) {
            throw error;
        }

        trackMessage.innerHTML = `
            <strong>Complaint found!</strong><br><br>

            <strong>Tracking ID:</strong>
            ${data.tracking_code}<br>

            <strong>Category:</strong>
            ${data.category}<br>

            <strong>Subject:</strong>
            ${data.subject}<br>

            <strong>Status:</strong>
            ${data.status}<br>

            <strong>Submitted:</strong>
            ${new Date(data.created_at).toLocaleString()}
        `;

    } catch (error) {

        console.error(error);

        trackMessage.textContent =
            "No complaint was found with that tracking code.";

    }

});