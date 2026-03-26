const form = document.getElementById("admissionForm");
const formMessage = document.getElementById("formMessage");
const otpSection = document.getElementById("otpSection");
const verifyOtpBtn = document.getElementById("verifyOtpBtn");
const otpCodeInput = document.getElementById("otpCode");
const resendOtpBtn = document.getElementById("resendOtpBtn");
const resendHint = document.getElementById("resendHint");
const dobInput = document.getElementById("dobInput");
const paymentSection = document.getElementById("paymentSection");
const payNowBtn = document.getElementById("payNowBtn");
const paymentMessage = document.getElementById("paymentMessage");
const paymentCourseName = document.getElementById("paymentCourseName");
const paymentFeeAmount = document.getElementById("paymentFeeAmount");
const paymentQrImage = document.getElementById("paymentQrImage");
const upiQrSection = document.getElementById("upiQrSection");
const paymentMethodName = document.getElementById("paymentMethodName");
const paymentQrCopy = document.getElementById("paymentQrCopy");
const paymentApplicationId = document.getElementById("paymentApplicationId");
const transactionIdInput = document.getElementById("transactionIdInput");
const paymentScreenshotInput = document.getElementById("paymentScreenshotInput");
const clearDraftBtn = document.getElementById("clearDraftBtn");
const formProgressFill = document.getElementById("formProgressFill");
const stepCounter = document.getElementById("stepCounter");
const stepPanels = Array.from(document.querySelectorAll(".form-step-panel"));
const stepTabs = Array.from(document.querySelectorAll(".form-step-tab"));
const maxUploadSize = 2 * 1024 * 1024;
const apiBase = typeof window.COLLEGE_ADMISSION_API_BASE === "string" && window.COLLEGE_ADMISSION_API_BASE.trim() !== ""
    ? window.COLLEGE_ADMISSION_API_BASE.trim()
    : "./api/";
const courseFees = typeof window.COLLEGE_ADMISSION_COURSE_FEES === "object" && window.COLLEGE_ADMISSION_COURSE_FEES !== null
    ? window.COLLEGE_ADMISSION_COURSE_FEES
    : {};
const upiId = typeof window.COLLEGE_ADMISSION_UPI_ID === "string" ? window.COLLEGE_ADMISSION_UPI_ID : "";
const upiName = typeof window.COLLEGE_ADMISSION_UPI_NAME === "string" ? window.COLLEGE_ADMISSION_UPI_NAME : "Online College Admission System";
const draftDbEnabled = Boolean(window.COLLEGE_ADMISSION_DRAFT_DB_ENABLED);
const draftStorageKey = "collegeAdmissionFormDraft:v2";
let currentStep = 1;
let resendSecondsLeft = 0;
let resendTimer = null;
let draftSaveTimer = null;
let lastSubmittedCourse = "";
let lastSubmittedFee = 0;

function apiUrl(endpoint) {
    return apiBase + String(endpoint || "").replace(/^\//, "");
}

function getDashboardPageUrl() {
    return new URL("dashboard.php", window.location.href.split("#")[0]).toString();
}

function getSelectedCourse() {
    const courseInput = form ? form.querySelector('select[name="course"]') : null;
    return courseInput ? courseInput.value.trim() : "";
}

function getSelectedCourseFee() {
    const course = getSelectedCourse();
    const fee = Number(courseFees[course] || 0);
    return Number.isFinite(fee) ? fee : 0;
}

function getSelectedPaymentMethod() {
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    return selectedMethod ? selectedMethod.value : "Google Pay";
}

function formatCurrency(amount) {
    return `Rs ${Number(amount || 0).toLocaleString("en-IN")}`;
}

function buildUpiPaymentLink(course, amount) {
    const params = new URLSearchParams({
        pa: upiId,
        pn: upiName,
        am: String(amount),
        cu: "INR",
        tn: `${course} Admission Fee`
    });

    return `upi://pay?${params.toString()}`;
}

function getDraftFieldNames() {
    return [
        "full_name",
        "email",
        "phone",
        "otp_delivery",
        "dob",
        "gender",
        "course",
        "city",
        "state",
        "zip_code",
        "previous_marks",
        "address",
        "payment_method",
        "transaction_id"
    ];
}

function getDraftPayload() {
    const draft = {};

    getDraftFieldNames().forEach((fieldName) => {
        if (fieldName === "payment_method") {
            draft[fieldName] = getSelectedPaymentMethod();
            return;
        }

        if (fieldName === "transaction_id") {
            draft[fieldName] = transactionIdInput ? transactionIdInput.value.trim() : "";
            return;
        }

        const field = form ? form.querySelector(`[name="${fieldName}"]`) : null;
        draft[fieldName] = field ? String(field.value || "") : "";
    });

    return draft;
}

function applyDraft(draft) {
    if (!draft || typeof draft !== "object" || !form) {
        return false;
    }

    getDraftFieldNames().forEach((fieldName) => {
        const value = typeof draft[fieldName] === "string" ? draft[fieldName] : "";
        if (value === "") {
            return;
        }

        if (fieldName === "payment_method") {
            const radios = document.querySelectorAll(`input[name="${fieldName}"]`);
            radios.forEach((radio) => {
                radio.checked = radio.value === value;
            });
            return;
        }

        if (fieldName === "transaction_id") {
            if (transactionIdInput) {
                transactionIdInput.value = value;
            }
            return;
        }

        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.value = value;
        }
    });

    return true;
}

function saveDraftLocally() {
    if (!window.localStorage) {
        return;
    }

    try {
        window.localStorage.setItem(draftStorageKey, JSON.stringify(getDraftPayload()));
    } catch (error) {
        // Ignore browser storage failures.
    }
}

function clearDraftLocally() {
    if (!window.localStorage) {
        return;
    }

    try {
        window.localStorage.removeItem(draftStorageKey);
    } catch (error) {
        // Ignore browser storage failures.
    }
}

function restoreLocalDraft() {
    if (!window.localStorage) {
        return false;
    }

    try {
        const rawDraft = window.localStorage.getItem(draftStorageKey);
        if (!rawDraft) {
            return false;
        }

        return applyDraft(JSON.parse(rawDraft));
    } catch (error) {
        return false;
    }
}

async function saveDraftToDatabase() {
    if (!draftDbEnabled) {
        return;
    }

    try {
        await fetch(apiUrl("save-draft.php"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                action: "save",
                draft: getDraftPayload()
            })
        });
    } catch (error) {
        // Ignore draft sync failures during typing.
    }
}

function scheduleDraftSave() {
    saveDraftLocally();

    if (!draftDbEnabled) {
        return;
    }

    if (draftSaveTimer) {
        clearTimeout(draftSaveTimer);
    }

    draftSaveTimer = setTimeout(() => {
        saveDraftToDatabase();
    }, 600);
}

async function clearDraftEverywhere() {
    clearDraftLocally();

    if (!draftDbEnabled) {
        return;
    }

    try {
        await fetch(apiUrl("save-draft.php"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ action: "clear" })
        });
    } catch (error) {
        // Ignore clear failures.
    }
}

async function restoreDatabaseDraft() {
    if (!draftDbEnabled) {
        return false;
    }

    try {
        const response = await fetch(apiUrl("load-draft.php"), { method: "GET" });
        const result = await response.json();

        if (!response.ok || !result.ok || !result.draft) {
            return false;
        }

        return applyDraft(result.draft);
    } catch (error) {
        return false;
    }
}

function updatePaymentSummary() {
    const course = getSelectedCourse() || lastSubmittedCourse;
    const fee = getSelectedCourse() ? getSelectedCourseFee() : lastSubmittedFee;
    const paymentMethod = getSelectedPaymentMethod();

    if (paymentCourseName) {
        paymentCourseName.textContent = course || "Not selected";
    }

    if (paymentFeeAmount) {
        paymentFeeAmount.textContent = formatCurrency(fee);
    }

    if (paymentMethodName) {
        paymentMethodName.textContent = paymentMethod;
    }

    if (paymentQrCopy) {
        paymentQrCopy.textContent = `Scan this QR in ${paymentMethod} and pay the exact yearly admission fee for the selected course.`;
    }

    if (paymentQrImage && course && fee > 0 && upiId) {
        const qrPayload = buildUpiPaymentLink(course, fee);
        paymentQrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(qrPayload)}`;
        paymentQrImage.style.display = "block";
    } else if (paymentQrImage) {
        paymentQrImage.removeAttribute("src");
        paymentQrImage.style.display = "none";
    }
}

function validateSelectedFiles() {
    const requiredFiles = [
        { name: "marksheet_file", label: "Marksheet" },
        { name: "id_proof_file", label: "ID Proof" },
        { name: "photo_file", label: "Photo" }
    ];

    for (const item of requiredFiles) {
        const input = form ? form.querySelector(`input[name="${item.name}"]`) : null;
        if (!input || !input.files || input.files.length === 0) {
            return `${item.label} file is required.`;
        }

        const file = input.files[0];
        if (file.size > maxUploadSize) {
            return `${item.label} must be under 2 MB.`;
        }
    }

    return "";
}

function getStepFields(stepNumber) {
    const panel = stepPanels.find((item) => Number(item.dataset.step) === stepNumber);
    if (!panel) {
        return [];
    }

    return Array.from(panel.querySelectorAll("input, select, textarea")).filter((field) => field.type !== "button" && field.type !== "submit" && field.name !== "otp_code");
}

function validateStep(stepNumber) {
    const fields = getStepFields(stepNumber);
    for (const field of fields) {
        if (field.type === "file") {
            continue;
        }

        if (!field.checkValidity()) {
            field.reportValidity();
            return false;
        }
    }

    if (stepNumber === 3) {
        const fileValidationError = validateSelectedFiles();
        if (fileValidationError) {
            if (formMessage) {
                formMessage.className = "message error";
                formMessage.textContent = fileValidationError;
            }
            return false;
        }
    }

    return true;
}

function renderStep() {
    stepPanels.forEach((panel, index) => {
        panel.classList.toggle("is-active", index + 1 === currentStep);
    });

    stepTabs.forEach((tab, index) => {
        tab.classList.toggle("is-active", index + 1 === currentStep);
    });

    if (stepCounter) {
        stepCounter.textContent = `Step ${currentStep} of ${stepPanels.length}`;
    }

    if (formProgressFill) {
        formProgressFill.style.width = `${Math.round((currentStep / stepPanels.length) * 100)}%`;
    }
}

function moveToStep(nextStep) {
    if (nextStep < 1 || nextStep > stepPanels.length) {
        return;
    }

    currentStep = nextStep;
    renderStep();
}

function setResendState(seconds) {
    resendSecondsLeft = Number.isFinite(seconds) ? Math.max(0, Math.floor(seconds)) : 30;
    if (resendTimer) {
        clearInterval(resendTimer);
        resendTimer = null;
    }

    const render = () => {
        if (!resendOtpBtn) {
            return;
        }

        if (resendSecondsLeft > 0) {
            resendOtpBtn.disabled = true;
            resendOtpBtn.textContent = `Resend OTP in ${resendSecondsLeft}s`;
            if (resendHint) {
                resendHint.textContent = `You can resend OTP after ${resendSecondsLeft} second(s).`;
            }
        } else {
            resendOtpBtn.disabled = false;
            resendOtpBtn.textContent = "Resend OTP";
            if (resendHint) {
                resendHint.textContent = "Didn't receive OTP? Click resend.";
            }
        }
    };

    render();
    if (resendSecondsLeft <= 0) {
        return;
    }

    resendTimer = setInterval(() => {
        resendSecondsLeft -= 1;
        if (resendSecondsLeft <= 0) {
            resendSecondsLeft = 0;
            clearInterval(resendTimer);
            resendTimer = null;
        }
        render();
    }, 1000);
}

if (form) {
    document.querySelectorAll(".js-step-next").forEach((button) => {
        button.addEventListener("click", () => {
            if (!validateStep(currentStep)) {
                return;
            }
            moveToStep(currentStep + 1);
        });
    });

    document.querySelectorAll(".js-step-prev").forEach((button) => {
        button.addEventListener("click", () => {
            moveToStep(currentStep - 1);
        });
    });

    const courseInput = form.querySelector('select[name="course"]');
    if (courseInput) {
        courseInput.addEventListener("change", () => {
            updatePaymentSummary();
            scheduleDraftSave();
        });
    }

    document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
        input.addEventListener("change", () => {
            updatePaymentSummary();
            scheduleDraftSave();
        });
    });

    const persistableFields = form.querySelectorAll("input, select, textarea");
    persistableFields.forEach((field) => {
        if (field.type === "file" || field.name === "otp_code") {
            return;
        }

        field.addEventListener("input", scheduleDraftSave);
        field.addEventListener("change", scheduleDraftSave);
    });

    if (transactionIdInput) {
        transactionIdInput.addEventListener("input", scheduleDraftSave);
    }

    Promise.resolve()
        .then(async () => {
            const databaseRestored = await restoreDatabaseDraft();
            const localRestored = databaseRestored ? false : restoreLocalDraft();
            updatePaymentSummary();
            renderStep();

            if ((databaseRestored || localRestored) && formMessage) {
                formMessage.className = "message success";
                formMessage.textContent = databaseRestored
                    ? "Database draft restored for this account."
                    : "Saved draft restored after refresh.";
            }
        });

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (!validateStep(1) || !validateStep(2) || !validateStep(3)) {
            return;
        }

        formMessage.className = "message";
        formMessage.textContent = "Sending OTP...";

        const formData = new FormData(form);
        formData.delete("otp_code");

        try {
            const response = await fetch(apiUrl("request-otp.php"), {
                method: "POST",
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                const fieldErrors = result.errors ? Object.values(result.errors).join(" ") : "";
                throw new Error(fieldErrors || result.message || "Submission failed.");
            }

            formMessage.className = "message success";
            formMessage.textContent = result.message || "OTP sent.";
            if (result.dev_otp) {
                formMessage.textContent += ` (Test OTP: ${result.dev_otp})`;
            }

            if (otpSection) {
                otpSection.style.display = "grid";
            }
            setResendState(Number(result.retry_after || 30));
        } catch (error) {
            formMessage.className = "message error";
            formMessage.textContent = error.message || "Server error while sending OTP.";
        }
    });
}

if (resendOtpBtn) {
    resendOtpBtn.addEventListener("click", async () => {
        resendOtpBtn.disabled = true;
        formMessage.className = "message";
        formMessage.textContent = "Resending OTP...";

        try {
            const response = await fetch(apiUrl("resend-otp.php"), {
                method: "POST"
            });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                if (result.retry_after) {
                    setResendState(Number(result.retry_after));
                } else {
                    resendOtpBtn.disabled = false;
                }
                throw new Error(result.message || "Resend failed.");
            }

            formMessage.className = "message success";
            formMessage.textContent = result.message || "OTP resent.";
            if (result.dev_otp) {
                formMessage.textContent += ` (Test OTP: ${result.dev_otp})`;
            }
            setResendState(Number(result.retry_after || 30));
        } catch (error) {
            formMessage.className = "message error";
            formMessage.textContent = error.message || "Server error while resending OTP.";
        }
    });
}

if (dobInput) {
    dobInput.addEventListener("change", () => {
        if (dobInput.value === "") {
            dobInput.focus();
        }
    });
}

if (verifyOtpBtn) {
    verifyOtpBtn.addEventListener("click", async () => {
        formMessage.className = "message";
        formMessage.textContent = "Verifying OTP...";

        const fileValidationError = validateSelectedFiles();
        if (fileValidationError) {
            formMessage.className = "message error";
            formMessage.textContent = fileValidationError;
            return;
        }

        const verifyData = new FormData(form);
        if (otpCodeInput) {
            verifyData.set("otp_code", otpCodeInput.value.trim());
        }

        try {
            const response = await fetch(apiUrl("verify-otp.php"), {
                method: "POST",
                body: verifyData
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.message || "OTP verification failed.");
            }

            await clearDraftEverywhere();

            if (paymentApplicationId) {
                paymentApplicationId.value = String(result.application_id || "");
            }
            lastSubmittedCourse = String(result.course || "");
            lastSubmittedFee = Number(result.fee_amount || 0);

            form.reset();
            moveToStep(1);
            updatePaymentSummary();

            if (otpSection) {
                otpSection.style.display = "none";
            }

            formMessage.className = "message success";
            formMessage.textContent = result.message || "Application submitted.";

            const dashboardWindow = window.open(getDashboardPageUrl(), "_blank", "noopener");
            if (!dashboardWindow) {
                formMessage.textContent += " Student dashboard could not open automatically. Please allow pop-ups.";
            }

            if (paymentSection) {
                paymentSection.style.display = "block";
                updatePaymentSummary();
                paymentSection.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        } catch (error) {
            formMessage.className = "message error";
            formMessage.textContent = error.message || "Server error while verifying OTP.";
        }
    });
}

if (payNowBtn) {
    payNowBtn.addEventListener("click", async () => {
        const applicationId = paymentApplicationId ? paymentApplicationId.value.trim() : "";
        const method = getSelectedPaymentMethod();
        const selectedCourse = getSelectedCourse() || lastSubmittedCourse;
        const fee = getSelectedCourse() ? getSelectedCourseFee() : lastSubmittedFee;
        const transactionId = transactionIdInput ? transactionIdInput.value.trim() : "";

        if (applicationId === "") {
            paymentMessage.className = "message error";
            paymentMessage.textContent = "Submit the application first, then save payment details.";
            return;
        }

        if (transactionId.length < 6) {
            paymentMessage.className = "message error";
            paymentMessage.textContent = "Enter a valid payment transaction ID.";
            return;
        }

        if (upiQrSection) {
            upiQrSection.style.display = "grid";
        }
        updatePaymentSummary();

        const paymentData = new FormData();
        paymentData.append("application_id", applicationId);
        paymentData.append("payment_method", method);
        paymentData.append("transaction_id", transactionId);
        if (paymentScreenshotInput && paymentScreenshotInput.files && paymentScreenshotInput.files[0]) {
            paymentData.append("payment_screenshot", paymentScreenshotInput.files[0]);
        }

        paymentMessage.className = "message";
        paymentMessage.textContent = "Saving payment details...";

        try {
            const response = await fetch(apiUrl("save-payment.php"), {
                method: "POST",
                body: paymentData
            });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.message || "Unable to save payment details.");
            }

            paymentMessage.className = "message success";
            paymentMessage.textContent = `${method} payment recorded for ${selectedCourse || "the selected course"} at ${formatCurrency(fee)}. Admin verification is pending.`;
            scheduleDraftSave();
        } catch (error) {
            paymentMessage.className = "message error";
            paymentMessage.textContent = error.message || "Unable to save payment details.";
        }
    });
}

if (clearDraftBtn) {
    clearDraftBtn.addEventListener("click", async () => {
        await clearDraftEverywhere();

        if (form) {
            form.reset();
        }
        if (transactionIdInput) {
            transactionIdInput.value = "";
        }
        if (paymentApplicationId) {
            paymentApplicationId.value = "";
        }
        lastSubmittedCourse = "";
        lastSubmittedFee = 0;
        if (otpSection) {
            otpSection.style.display = "none";
        }
        if (paymentSection) {
            paymentSection.style.display = "none";
        }

        moveToStep(1);
        updatePaymentSummary();

        if (formMessage) {
            formMessage.className = "message success";
            formMessage.textContent = "Saved draft cleared.";
        }
    });
}
