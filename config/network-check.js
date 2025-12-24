// Network status check and redirection
(function () {
  // Get base URL from PHP variable
  const baseUrl = "<?php echo Database::$baseUrl; ?>";

  // Function to check network status
  function checkNetworkStatus() {
    if (!navigator.onLine) {
      // If offline, redirect to noInternet page
      window.location.href = baseUrl + "noInternet/";
    }
  }

  // Function to register service worker for offline caching
  function registerServiceWorker() {
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker
        .register(baseUrl + "noInternet/sw.js")
        .then((registration) => {
          console.log("ServiceWorker registration successful");
        })
        .catch((err) => {
          console.log("ServiceWorker registration failed: ", err);
        });
    }
  }

  // Initial check
  checkNetworkStatus();

  // Listen for online/offline events
  window.addEventListener("online", () => {
    // If we're on the noInternet page and connection is restored, redirect back
    if (window.location.pathname.includes("noInternet")) {
      window.location.href = baseUrl;
    }
  });

  window.addEventListener("offline", () => {
    checkNetworkStatus();
  });

  // Register service worker
  registerServiceWorker();
})();
