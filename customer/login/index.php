<?php
require_once '../config/session_check.php';
require_once '../c_includes/loader.php';


// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ../dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1A1D21;
            --card-bg: #222529;
            --accent-green: #2F9B7F;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(255, 255, 255, 0.05);
        }

        body {
            background: linear-gradient(135deg, #1A1D21 0%, #222529 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .login-container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-green), #1e6e59);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: var(--accent-green);
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            box-shadow: 0 0 0 0.2rem rgba(47, 155, 127, 0.25);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--accent-green) 0%, #1e6e59 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(47, 155, 127, 0.3);
            background: linear-gradient(135deg, #248c6f 0%, #1e6e59 100%);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }

        .form-check-input {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: var(--border-color);
        }

        .form-check-input:checked {
            background-color: var(--accent-green);
            border-color: var(--accent-green);
        }

        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
        }

        .signup-link a {
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .signup-link a:hover {
            color: #248c6f;
            text-decoration: none;
        }

        .error-message {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .position-relative i {
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .position-relative i:hover {
            color: var(--accent-green);
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }

            .logo h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <h1>Golden Dream</h1>
            <p class="text-muted">Welcome back! Please login to your account.</p>
        </div>

        <div class="error-message" id="errorMessage"></div>

        <form id="loginForm" action="process_login.php" method="POST">
            <div class="mb-3">
                <label for="customerId" class="form-label">Customer ID</label>
                <input type="text" class="form-control" id="customerId" name="customerId"
                    placeholder="Enter your customer ID" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="password" name="password"
                        placeholder="Enter your password" required>
                    <i class="fas fa-eye position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer;"
                        onclick="togglePassword()"></i>
                </div>
            </div>

            <div class="mb-3 remember-me">
                <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember me for 30 days</label>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
        </form>

        <!-- <div class="signup-link">
            <p class="mb-0">Don't have an account? <a href="https://goldendream.in//refer?id=GDP0001&ref=NTAw">Sign up here</a></p>
        </div> -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.fa-eye');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Check for error message in URL
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        if (error) {
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = decodeURIComponent(error);
            errorMessage.style.display = 'block';
        }
    </script>
</body>

</html>