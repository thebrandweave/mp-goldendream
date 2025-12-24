document.addEventListener("DOMContentLoaded", () => {
  const signupForm = document.getElementById("signupForm");
  const fullNameInput = document.getElementById("fullName");
  const phoneInput = document.getElementById("phone");
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const termsCheckbox = document.getElementById("terms");

  // Verify all elements are found
  if (!signupForm || !fullNameInput || !phoneInput || !passwordInput || !confirmPasswordInput || !termsCheckbox) {
    console.error("One or more form elements not found!");
    return;
  }

  // Password validation function
  function validatePassword(password) {
    const minLength = 8;
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    return password.length >= minLength && hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar;
  }

  // Show password requirements
  passwordInput.addEventListener("focus", () => {
    const requirements = document.createElement("div");
    requirements.className = "password-requirements";
    requirements.innerHTML = `
                <p>Password must contain:</p>
                <ul>
                    <li>At least 8 characters</li>
                    <li>One uppercase letter</li>
                    <li>One lowercase letter</li>
                    <li>One number</li>
                    <li>One special character</li>
                </ul>
            `;
    passwordInput.parentElement.appendChild(requirements);
  });

  passwordInput.addEventListener("blur", () => {
    const requirements = document.querySelector(".password-requirements");
    if (requirements) {
      requirements.remove();
    }
  });

  // Phone number validation
  phoneInput.addEventListener("input", (e) => {
    e.target.value = e.target.value.replace(/\D/g, "").substring(0, 10);
  });

  signupForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const fullName = fullNameInput.value.trim();
    const phone = phoneInput.value.trim();
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    // Basic validation
    if (!fullName || !phone || !password || !confirmPassword) {
      showError("Please fill in all fields");
      return;
    }

    // Phone validation
    if (phone.length !== 10) {
      showError("Please enter a valid 10-digit phone number");
      return;
    }

    // Password validation
    if (!validatePassword(password)) {
      showError("Password does not meet the requirements");
      return;
    }

    // Confirm password validation
    if (password !== confirmPassword) {
      showError("Passwords do not match");
      return;
    }

    // Terms checkbox validation
    if (!termsCheckbox.checked) {
      showError("Please agree to the Terms & Conditions");
      return;
    }

    // Add loading animation to button
    const signupBtn = signupForm.querySelector(".login-btn");
    signupBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
    signupBtn.disabled = true;

    try {
      const response = await fetch(window.location.href, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          fullName,
          phone,
          password,
          confirmPassword,
        }),
      });

      const data = await response.json();

      if (data.success) {
        showSuccess(data.message);
        // Store phone in localStorage for remember me functionality
        localStorage.setItem("rememberedPhone", phone);
        // Redirect after a short delay
        setTimeout(() => {
          window.location.href = data.redirect;
        }, 1500);
      } else {
        showError(data.message);
        signupBtn.innerHTML = "Create Account";
        signupBtn.disabled = false;
      }
    } catch (error) {
      console.error("Error:", error);
      showError("An error occurred during registration");
      signupBtn.innerHTML = "Create Account";
      signupBtn.disabled = false;
    }
  });

  // Add input focus effects
  const inputFields = document.querySelectorAll(".input-field input");
  inputFields.forEach((input) => {
    input.addEventListener("focus", () => {
      input.parentElement.classList.add("focused");
    });

    input.addEventListener("blur", () => {
      if (!input.value) {
        input.parentElement.classList.remove("focused");
      }
    });
  });

  // Helper function to show error messages
  function showError(message) {
    const errorDiv = document.createElement("div");
    errorDiv.className = "error-message";
    errorDiv.textContent = message;

    const existingError = document.querySelector(".error-message");
    if (existingError) {
      existingError.remove();
    }

    signupForm.insertBefore(errorDiv, signupForm.firstChild);

    setTimeout(() => {
      errorDiv.remove();
    }, 3000);
  }

  // Helper function to show success messages
  function showSuccess(message) {
    const successDiv = document.createElement("div");
    successDiv.className = "success-message";
    successDiv.textContent = message;

    const existingSuccess = document.querySelector(".success-message");
    if (existingSuccess) {
      existingSuccess.remove();
    }

    signupForm.insertBefore(successDiv, signupForm.firstChild);
  }
});
