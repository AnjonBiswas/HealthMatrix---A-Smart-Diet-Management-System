(function (window, document) {
    "use strict";

    function $(selector) {
        return document.querySelector(selector);
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
    }

    function validatePhone(phone) {
        return /^\+?[0-9]{7,15}$/.test(String(phone).trim());
    }

    function passwordStrengthScore(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        return score;
    }

    function validatePassword(password) {
        return passwordStrengthScore(String(password)) >= 4;
    }

    function setFieldError(fieldName, message) {
        const node = document.querySelector('[data-error-for="' + fieldName + '"]');
        if (!node) return;
        node.textContent = message || "";
    }

    function clearFieldError(fieldName) {
        setFieldError(fieldName, "");
    }

    function updatePasswordStrength(inputSelector, barSelector, textSelector) {
        const input = $(inputSelector);
        const bar = $(barSelector);
        const text = $(textSelector);
        if (!input || !bar || !text) return;

        const score = passwordStrengthScore(input.value);
        // Fill to 100% once policy requirements are met (score 4+).
        const percent = (Math.min(score, 4) / 4) * 100;
        bar.style.width = percent + "%";

        let label = "Very Weak";
        let cls = "bg-danger";
        if (score >= 2) {
            label = "Weak";
            cls = "bg-warning";
        }
        if (score >= 3) {
            label = "Medium";
            cls = "bg-info";
        }
        if (score >= 4) {
            label = "Strong";
            cls = "bg-success";
        }
        if (score === 5) {
            label = "Very Strong";
            cls = "bg-success";
        }

        bar.className = "progress-bar " + cls;
        text.textContent = "Password strength: " + label;
    }

    function calculateBMI(weight, heightCm) {
        const w = parseFloat(weight);
        const h = parseFloat(heightCm);
        if (!w || !h || h <= 0) return 0;
        const hm = h / 100;
        return +(w / (hm * hm)).toFixed(2);
    }

    function calculateCalories(age, weight, height, gender, activity, goal) {
        age = parseInt(age, 10);
        weight = parseFloat(weight);
        height = parseFloat(height);
        if (!age || !weight || !height) return 0;

        let bmr = 0;
        if (gender === "male") {
            bmr = 88.362 + (13.397 * weight) + (4.799 * height) - (5.677 * age);
        } else {
            bmr = 447.593 + (9.247 * weight) + (3.098 * height) - (4.330 * age);
        }

        const multipliers = {
            sedentary: 1.2,
            lightly_active: 1.375,
            moderately_active: 1.55,
            very_active: 1.725,
            extra_active: 1.9
        };

        const adjustments = {
            weight_loss: -500,
            maintain: 0,
            gain: 400
        };

        const maintenance = bmr * (multipliers[activity] || 1.2);
        const total = maintenance + (adjustments[goal] || 0);
        return Math.max(1200, Math.round(total));
    }

    async function checkEmailAvailability(email, url) {
        if (!validateEmail(email)) {
            return { available: false, message: "Invalid email format" };
        }

        try {
            const endpoint = url + "&email=" + encodeURIComponent(email);
            const res = await fetch(endpoint, { headers: { "X-Requested-With": "XMLHttpRequest" } });
            return await res.json();
        } catch (err) {
            return { available: false, message: "Unable to verify email now" };
        }
    }

    function validateStep(step, form) {
        let valid = true;

        function fail(field, message) {
            setFieldError(field, message);
            valid = false;
        }

        if (step === 1) {
            const fullName = form.full_name.value.trim();
            const email = form.email.value.trim();
            const password = form.password.value;
            const confirmPassword = form.confirm_password.value;
            const phone = form.phone.value.trim();

            clearFieldError("full_name");
            clearFieldError("email");
            clearFieldError("password");
            clearFieldError("confirm_password");
            clearFieldError("phone");

            if (fullName.length < 3) fail("full_name", "Full name must be at least 3 characters.");
            if (!validateEmail(email)) fail("email", "Please provide a valid email.");
            if (!validatePassword(password)) fail("password", "Use 8+ chars with upper, lower, number and symbol.");
            if (password !== confirmPassword) fail("confirm_password", "Passwords do not match.");
            if (!validatePhone(phone)) fail("phone", "Phone must be 7-15 digits (optional +).");
        }

        if (step === 2) {
            clearFieldError("age");
            clearFieldError("gender");
            clearFieldError("weight");
            clearFieldError("height");
            clearFieldError("activity_level");

            const age = parseInt(form.age.value, 10);
            const gender = form.gender.value;
            const weight = parseFloat(form.weight.value);
            const height = parseFloat(form.height.value);
            const activity = form.activity_level.value;

            if (!age || age < 13 || age > 100) fail("age", "Age must be between 13 and 100.");
            if (!gender) fail("gender", "Please select gender.");
            if (!weight || weight < 20 || weight > 350) fail("weight", "Weight must be between 20 and 350 kg.");
            if (!height || height < 90 || height > 250) fail("height", "Height must be between 90 and 250 cm.");
            if (!activity) fail("activity_level", "Please select activity level.");
        }

        if (step === 3) {
            clearFieldError("goal");
            clearFieldError("terms");
            if (!form.goal.value) fail("goal", "Please select a goal.");
            if (!form.terms.checked) fail("terms", "You must agree to terms and conditions.");
        }

        return valid;
    }

    function initRegistrationForm(options) {
        const form = $(options.formSelector);
        if (!form) return;

        const steps = Array.from(document.querySelectorAll(options.stepSelector));
        const nextBtn = $(options.nextBtnSelector);
        const prevBtn = $(options.prevBtnSelector);
        const submitBtn = $(options.submitBtnSelector);
        const progressBar = $(options.progressBarSelector);
        const stepLabel = $(options.stepLabelSelector);
        const emailInput = $(options.emailInputSelector);
        const emailResult = $(options.emailResultSelector);
        const pwdInput = $(options.passwordInputSelector);
        const bmiNode = $(options.bmiSelector);
        const calNode = $(options.calorieSelector);

        let currentStep = 1;

        function renderStep() {
            steps.forEach((pane) => {
                pane.classList.toggle("d-none", Number(pane.dataset.step) !== currentStep);
            });
            prevBtn.disabled = currentStep === 1;
            nextBtn.classList.toggle("d-none", currentStep === steps.length);
            submitBtn.classList.toggle("d-none", currentStep !== steps.length);
            progressBar.style.width = ((currentStep / steps.length) * 100) + "%";
            stepLabel.textContent = String(currentStep);
            form.current_step.value = String(currentStep);
        }

        function updatePreview() {
            const bmi = calculateBMI(form.weight.value, form.height.value);
            const calories = calculateCalories(
                form.age.value,
                form.weight.value,
                form.height.value,
                form.gender.value || "female",
                form.activity_level.value,
                form.goal.value
            );
            bmiNode.textContent = bmi > 0 ? bmi.toFixed(2) : "--";
            calNode.textContent = calories > 0 ? String(calories) : "--";
        }

        nextBtn.addEventListener("click", function () {
            if (!validateStep(currentStep, form)) return;
            if (currentStep < steps.length) {
                currentStep++;
                renderStep();
                updatePreview();
            }
        });

        prevBtn.addEventListener("click", function () {
            if (currentStep > 1) {
                currentStep--;
                renderStep();
            }
        });

        form.addEventListener("submit", function (e) {
            if (!validateStep(1, form) || !validateStep(2, form) || !validateStep(3, form)) {
                e.preventDefault();
                return;
            }
        });

        if (pwdInput) {
            pwdInput.addEventListener("input", function () {
                updatePasswordStrength(
                    options.passwordInputSelector,
                    options.passwordStrengthBarSelector,
                    options.passwordStrengthTextSelector
                );
            });
        }

        ["age", "weight", "height", "activity_level", "goal"].forEach((field) => {
            if (form[field]) {
                form[field].addEventListener("input", updatePreview);
                form[field].addEventListener("change", updatePreview);
            }
        });
        form.querySelectorAll('input[name="gender"]').forEach((radio) => {
            radio.addEventListener("change", updatePreview);
        });

        form.querySelectorAll(".goal-card").forEach((card) => {
            card.addEventListener("click", function () {
                const goalInput = card.querySelector('input[name="goal"]');
                if (goalInput) {
                    goalInput.checked = true;
                }
                form.querySelectorAll(".goal-card").forEach((c) => c.classList.remove("border-success", "shadow"));
                card.classList.add("border-success", "shadow");
                clearFieldError("goal");
                updatePreview();
            });

            const checkedInput = card.querySelector('input[name="goal"]:checked');
            if (checkedInput) {
                card.classList.add("border-success", "shadow");
            }
        });

        let emailTimer = null;
        if (emailInput && emailResult) {
            emailInput.addEventListener("input", function () {
                clearTimeout(emailTimer);
                emailResult.textContent = "";

                emailTimer = setTimeout(async function () {
                    if (!validateEmail(emailInput.value)) return;
                    emailResult.textContent = "Checking email...";
                    emailResult.className = "text-muted d-block mt-1";
                    const result = await checkEmailAvailability(emailInput.value, options.emailCheckUrl);
                    emailResult.textContent = result.message || "";
                    emailResult.className = (result.available ? "text-success" : "text-danger") + " d-block mt-1";
                }, 350);
            });
        }

        renderStep();
        updatePreview();
    }

    function initRealtimeValidation(formSelector) {
        const form = $(formSelector);
        if (!form) return;

        form.querySelectorAll("input, select, textarea").forEach((field) => {
            field.addEventListener("blur", function () {
                const name = field.name || field.id;
                if (!name) return;

                if (field.required && !field.value.trim()) {
                    setFieldError(name, "This field is required.");
                    return;
                }

                if (name.toLowerCase().includes("email") && field.value && !validateEmail(field.value)) {
                    setFieldError(name, "Invalid email format.");
                    return;
                }

                if (name.toLowerCase().includes("phone") && field.value && !validatePhone(field.value)) {
                    setFieldError(name, "Invalid phone format.");
                    return;
                }

                clearFieldError(name);
            });
        });
    }

    window.HMValidation = {
        validateEmail: validateEmail,
        validatePassword: validatePassword,
        validatePhone: validatePhone,
        initRegistrationForm: initRegistrationForm,
        initRealtimeValidation: initRealtimeValidation,
        updatePasswordStrength: updatePasswordStrength,
        checkEmailAvailability: checkEmailAvailability
    };
})(window, document);
