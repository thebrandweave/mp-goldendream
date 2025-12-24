<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Signup - Golden Dream</title>
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
            padding: 2rem;
        }

        .signup-wrapper {
            display: flex;
            align-items: stretch;
            gap: 2rem;
            max-width: 1200px;
            width: 100%;
        }

        .info-panel {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border-color);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .info-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-green), #1e6e59);
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .info-icon {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .info-content h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .info-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        .info-panel h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
        }

        .signup-container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .signup-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-green), #1e6e59);
        }

        .signup-container h2 {
            color: var(--text-primary);
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 2rem;
        }

        .form-floating {
            margin-bottom: 1.25rem;
        }

        .form-floating label {
            color: var(--text-secondary);
            padding: 1rem 0.75rem;
        }

        .form-floating>.form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem 0.75rem;
            color: var(--text-primary);
            height: calc(3.5rem + 2px);
            transition: all 0.3s ease;
        }

        .form-floating>.form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            box-shadow: 0 0 0 0.2rem rgba(47, 155, 127, 0.25);
            color: var(--text-primary);
        }

        .form-floating>.form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-floating>.form-control:focus~label,
        .form-floating>.form-control:not(:placeholder-shown)~label {
            color: var(--accent-green);
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }

        .btn-signup {
            background: linear-gradient(135deg, var(--accent-green) 0%, #1e6e59 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            width: 100%;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(47, 155, 127, 0.3);
            background: linear-gradient(135deg, #248c6f 0%, #1e6e59 100%);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--accent-green);
        }

        .text-center {
            color: var(--text-secondary);
            margin-top: 1.5rem;
        }

        .text-center a {
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .text-center a:hover {
            color: #248c6f;
            text-decoration: none;
        }

        @media (max-width: 992px) {
            .signup-wrapper {
                flex-direction: column;
                align-items: center;
            }

            .info-panel,
            .signup-container {
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }

            .signup-container,
            .info-panel {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="signup-wrapper">
        <div class="signup-container">
            <h2>Create Account</h2>
            <form id="signupForm" action="process_signup.php" method="POST" enctype="multipart/form-data">
                <div class="form-floating">
                    <input type="text" class="form-control" id="fullName" name="fullName" placeholder="Full Name" required>
                    <label for="fullName">Full Name</label>
                </div>

                <div class="form-floating">
                    <input type="tel" class="form-control" id="phoneNumber" name="phoneNumber" placeholder="Phone Number" required>
                    <label for="phoneNumber">Phone Number</label>
                </div>

                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                    <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                </div>

                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required>
                    <label for="confirmPassword">Confirm Password</label>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-signup">Create Account</button>
                </div>

                <div class="text-center mt-3">
                    <p class="mb-0">Already have an account? <a href="../login/" class="text-decoration-none">Login here</a></p>
                </div>
            </form>
        </div>

        <div class="info-panel">
            <h2>Why Join Golden Dream?</h2>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="info-content">
                    <h3>Exciting Prizes</h3>
                    <p>Win amazing rewards and cash prizes through our monthly draws</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="info-content">
                    <h3>Secure & Reliable</h3>
                    <p>Your data and transactions are protected with advanced security measures</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="info-content">
                    <h3>Easy Withdrawals</h3>
                    <p>Quick and hassle-free withdrawal process for your winnings</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="info-content">
                    <h3>24/7 Support</h3>
                    <p>Our dedicated support team is always ready to help you</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const toggleIcon = document.querySelector('.password-toggle');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                confirmPasswordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                confirmPasswordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>

</html>