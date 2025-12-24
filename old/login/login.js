document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");
  const loginIdInput = document.getElementById("login_id");
  const passwordInput = document.getElementById("password");
  const rememberCheckbox = document.getElementById("remember");

  // Check for saved credentials
  const savedLoginId = localStorage.getItem("rememberedLoginId");
  if (savedLoginId) {
    loginIdInput.value = savedLoginId;
    rememberCheckbox.checked = true;
  }

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const login_id = loginIdInput.value;
    const password = passwordInput.value;

    // Basic validation
    if (!login_id || !password) {
      showError("Please fill in all fields");
      return;
    }

    // Handle remember me
    if (rememberCheckbox.checked) {
      localStorage.setItem("rememberedLoginId", login_id);
    } else {
      localStorage.removeItem("rememberedLoginId");
    }

    // Add loading animation to button
    const loginBtn = loginForm.querySelector(".login-btn");
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    loginBtn.disabled = true;

    try {
      const response = await fetch(window.location.href, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          login_id,
          password,
        }),
      });

      const data = await response.json();

      if (data.success) {
        showSuccess(data.message);
        setTimeout(() => {
          window.location.href = data.redirect;
        }, 1500);
      } else {
        showError(data.message);
        loginBtn.innerHTML = "Login";
        loginBtn.disabled = false;
      }
    } catch (error) {
      console.error("Error:", error);
      showError("An error occurred during login");
      loginBtn.innerHTML = "Login";
      loginBtn.disabled = false;
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

    loginForm.insertBefore(errorDiv, loginForm.firstChild);

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

    loginForm.insertBefore(successDiv, loginForm.firstChild);
  }
});
