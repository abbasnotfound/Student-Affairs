
"use strict";

// ========================================
// SUPABASE CONFIGURATION
// ========================================

const SUPABASE_URL =
    "https://fuglhqavojfwxggsesam.supabase.co";

const SUPABASE_ANON_KEY =
    "sb_publishable_Wm5Ff2M5oceS1fOZmoArbQ_St1gLjWE";

const supabaseClient = supabase.createClient(
    SUPABASE_URL,
    SUPABASE_ANON_KEY
);


// ========================================
// HELPER FUNCTIONS
// ========================================

// Display messages safely without inserting HTML from user input.
function showMessage(element, message, type = "info") {
    element.replaceChildren();
    element.textContent = message;
    element.dataset.type = type;
}

// Display multiple lines of complaint details safely.
function showDetails(element, details) {
    element.replaceChildren();

    details.forEach(([label, value]) => {
        const line = document.createElement("p");
        const heading = document.createElement("strong");

        heading.textContent = label + ": ";
        line.append(heading, document.createTextNode(String(value ?? "—")));
        element.appendChild(line);
    });
}

// Generate a random tracking code.
function generateTrackingCode() {
    const bytes = new Uint8Array(8);
    crypto.getRandomValues(bytes);

    const randomPart = Array.from(bytes)
        .map(byte => byte.toString(16).padStart(2, "0"))
        .join("")
        .toUpperCase();

    return "SA-" + randomPart;
}


// ========================================
// COMPLAINT FORM
// ========================================

const complaintForm = document.querySelector("#complaint-form");
const formMessage = document.querySelector("#form-message");

if (complaintForm && formMessage) {
    complaintForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (!complaintForm.reportValidity()) {
            return;
        }

        const submitButton = complaintForm.querySelector(
            'button[type="submit"]'
        );

        const formData = new FormData(complaintForm);

        const studentName = String(formData.get("name") ?? "").trim();

        // Your HTML field is named "studentId", but its label is "Class".
        // Keep the existing database schema compatible.
        const className = String(
            formData.get("studentId") ?? ""
        ).trim();

        const studentId = className;

        const section = String(
            formData.get("section") ?? ""
        ).trim();

        const email = String(
            formData.get("email") ?? ""
        ).trim();

        const category = String(
            formData.get("category") ?? ""
        ).trim();

        const subject = String(
            formData.get("subject") ?? ""
        ).trim();

        const description = String(
            formData.get("description") ?? ""
        ).trim();

        const trackingCode = generateTrackingCode();

        showMessage(
            formMessage,
            "Submitting your complaint...",
            "info"
        );

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = "Submitting...";
        }

        try {
            const { error } = await supabaseClient
                .from("complaints")
                .insert({
                    tracking_code: trackingCode,
                    student_name: studentName,
                    student_id: studentId,
                    class_name: className,
                    section: section || null,
                    email: email,
                    category: category,
                    subject: subject,
                    description: description,
                    status: "Pending"
                });

            if (error) {
                throw error;
            }

            formMessage.replaceChildren();

            const successHeading = document.createElement("strong");
            successHeading.textContent =
                "Complaint submitted successfully!";

            const explanation = document.createElement("p");
            explanation.textContent =
                "Save your tracking code to check the status later:";

            const code = document.createElement("strong");
            code.textContent = trackingCode;

            const copyButton = document.createElement("button");
            copyButton.type = "button";
            copyButton.className = "btn secondary";
            copyButton.textContent = "Copy tracking code";

            copyButton.addEventListener("click", async () => {
                try {
                    await navigator.clipboard.writeText(trackingCode);
                    copyButton.textContent = "Code copied!";
                } catch {
                    showMessage(
                        formMessage,
                        "Please copy your tracking code manually: " +
                        trackingCode,
                        "info"
                    );
                }
            });

            formMessage.append(
                successHeading,
                explanation,
                code,
                document.createElement("br"),
                copyButton
            );

            complaintForm.reset();

        } catch (error) {
            console.error("Complaint submission error:", error);

            showMessage(
                formMessage,
                "Your complaint could not be submitted. Please try again. " +
                "If the problem continues, check your database settings.",
                "error"
            );

        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML =
                    'Submit complaint <b>→</b>';
            }
        }
    });
}


// ========================================
// TRACK COMPLAINT
// ========================================

const trackForm = document.querySelector("#track-form");
const trackMessage = document.querySelector("#track-message");

if (trackForm && trackMessage) {
    trackForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (!trackForm.reportValidity()) {
            return;
        }

        const trackButton = trackForm.querySelector(
            'button[type="submit"]'
        );

        const formData = new FormData(trackForm);

        const code = String(
            formData.get("code") ?? ""
        ).trim().toUpperCase();

        showMessage(
            trackMessage,
            "Checking your complaint...",
            "info"
        );

        if (trackButton) {
            trackButton.disabled = true;
            trackButton.textContent = "Checking...";
        }

        try {
            // Uses the restricted tracking function created in Step 8.
            const { data, error } = await supabaseClient.rpc(
                "track_complaint",
                { code: code }
            );

            if (error) {
                throw error;
            }

            if (!Array.isArray(data) || data.length === 0) {
                showMessage(
                    trackMessage,
                    "No complaint was found with that tracking code.",
                    "error"
                );
                return;
            }

            const complaint = data[0];

            showDetails(trackMessage, [
                ["Tracking ID", complaint.tracking_code],
                ["Category", complaint.category],
                ["Subject", complaint.subject],
                ["Status", complaint.status],
                [
                    "Submitted",
                    complaint.created_at
                        ? new Date(complaint.created_at).toLocaleString()
                        : "Not available"
                ],
                [
                    "Last updated",
                    complaint.updated_at
                        ? new Date(complaint.updated_at).toLocaleString()
                        : "Not available"
                ]
            ]);

        } catch (error) {
            console.error("Complaint tracking error:", error);

            showMessage(
                trackMessage,
                "Unable to check the complaint right now. Please verify " +
                "that the tracking function and database permissions are configured.",
                "error"
            );

        } finally {
            if (trackButton) {
                trackButton.disabled = false;
                trackButton.innerHTML = "Check status <b>→</b>";
            }
        }
    });
}
