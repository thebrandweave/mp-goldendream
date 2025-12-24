// Handle file upload preview
document.querySelector('input[type="file"]').addEventListener("change", function (e) {
  const file = e.target.files[0];
  if (file) {
    // Validate file type
    const allowedTypes = ["image/jpeg", "image/png", "image/jpg"];
    if (!allowedTypes.includes(file.type)) {
      alert("Invalid file type. Only JPG, JPEG, and PNG files are allowed.");
      this.value = "";
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert("File size too large. Maximum size is 5MB.");
      this.value = "";
      return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      const preview = document.createElement("img");
      preview.src = e.target.result;
      preview.className = "screenshot-preview";
      preview.style.marginTop = "1rem";

      const existingPreview = document.querySelector(".screenshot-preview");
      if (existingPreview) {
        existingPreview.remove();
      }

      document.querySelector(".file-upload").appendChild(preview);
    };
    reader.readAsDataURL(file);
  }
});

// Handle form submission
document.getElementById("paymentForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  const formData = new FormData(this);
  const submitBtn = this.querySelector(".submit-btn");

  // Validate amount
  const amount = parseFloat(formData.get("amount"));
  if (amount <= 0) {
    alert("Please enter a valid amount");
    return;
  }

  // Validate payment code
  const paymentCode = parseInt(formData.get("payment_code"));
  if (paymentCode <= 0) {
    alert("Please enter a valid payment code");
    return;
  }

  // Disable submit button and show loading state
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  try {
    const response = await fetch("process_payment.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      // Show success message
      const successMessage = document.createElement("div");
      successMessage.className = "success-message";
      successMessage.textContent = data.message;
      this.insertBefore(successMessage, this.firstChild);

      // Remove success message after 3 seconds
      setTimeout(() => {
        successMessage.remove();
      }, 3000);

      // Reset form
      this.reset();
      const preview = document.querySelector(".screenshot-preview");
      if (preview) {
        preview.remove();
      }

      // Reload page after 2 seconds
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      // Show error message
      const errorMessage = document.createElement("div");
      errorMessage.className = "error-message";
      errorMessage.textContent = data.message || "Error submitting payment";
      this.insertBefore(errorMessage, this.firstChild);

      // Remove error message after 3 seconds
      setTimeout(() => {
        errorMessage.remove();
      }, 3000);
    }
  } catch (error) {
    console.error("Error:", error);
    // Show error message
    const errorMessage = document.createElement("div");
    errorMessage.className = "error-message";
    errorMessage.textContent = "Error submitting payment";
    this.insertBefore(errorMessage, this.firstChild);

    // Remove error message after 3 seconds
    setTimeout(() => {
      errorMessage.remove();
    }, 3000);
  } finally {
    // Reset submit button
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Payment';
  }
});

// Add active class to current nav item
document.querySelectorAll(".nav-link").forEach((link) => {
  if (link.getAttribute("href") === window.location.pathname) {
    link.classList.add("active");
  }
});
