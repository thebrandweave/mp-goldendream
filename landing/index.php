<?php include("../admin/components/loader.php");
require_once("../config/config.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Dream - Your Financial Future</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #ffffff;
            --accent-color: #a36d16;
            --text-color: #111111;
            --subtext-color: #555555;
            --border-color: #e5e5e5;
            --btn-bg: #000000;
            --btn-text: #ffffff;
            --btn-hover-bg: #222222;
            --success: #00b67a;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--secondary-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        .container {
            max-width: 100vw !important;
            margin: 0 auto;
            padding: 0 24px;
            overflow-x: hidden;
        }

        header {
            background: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            background: transparent;
            backdrop-filter: blur(10px);
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;

        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .logo {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav-links a {
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 8px 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent-color);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--accent-color);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        @media (max-width: 1024px) {
            .nav-links {
                display: none;
            }
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .country-flag {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-color);
            font-weight: 500;
        }

        .country-flag img {
            width: 24px;
            height: 16px;
            border-radius: 2px;
        }

        .login-dropdown {
            position: relative;
        }

        .login-btn {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 6px;
            padding: 10px 24px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-btn:hover {
            background: var(--btn-hover-bg);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .login-btn i {
            font-size: 1.1rem;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
        }

        .login-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            background: var(--secondary-color);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            width: 90%;
            max-width: 400px;
            opacity: 0;
            transition: all 0.3s ease;
            max-height: 90vh;
            overflow-x: hidden;
        }

        .login-modal.active {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-color);
            cursor: pointer;
            padding: 8px;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--accent-color);
        }

        .login-options {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .login-option {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-color);
        }

        .login-option:hover {
            background: #f0f2f5;
            transform: translateX(5px);
        }

        .login-option i {
            font-size: 1.5rem;
            color: var(--accent-color);
            transition: transform 0.3s ease;
        }

        .login-option:hover i {
            transform: scale(1.1);
        }

        .option-content {
            flex: 1;
        }

        .option-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .option-description {
            font-size: 0.85rem;
            color: #666;
        }

        .btn {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 6px;
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: var(--btn-hover-bg);
        }

        /* Hero Section */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 80px 0 60px 0;
            min-height: 80vh;
            gap: 40px;
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        .hero-text {
            flex: 1.2;
        }

        .hero-text h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 18px;
            color: var(--primary-color);
        }

        .hero-text h2 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 18px;
            color: var(--accent-color);
        }

        .hero-text ul {
            list-style: none;
            padding: 0;
            margin: 0 0 28px 0;
        }

        .hero-text ul li {
            font-size: 1.1rem;
            color: var(--success);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-text ul li span {
            color: var(--subtext-color);
            font-size: 1rem;
            font-weight: 400;
        }

        .hero-text .price {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .hero-text .sub-price {
            color: var(--subtext-color);
            font-size: 1rem;
            margin-bottom: 24px;
        }

        .hero-image {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-image img {
            max-width: 100%;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .hero {
                flex-direction: column;
                padding: 40px 0 20px 0;
            }

            .hero-image {
                margin-top: 32px;
            }
        }

        @media (max-width: 600px) {
            .navbar {
                flex-direction: column;
                gap: 18px;
            }

            .hero-text h1 {
                font-size: 2rem;
            }

            .hero-text h2 {
                font-size: 1.2rem;
            }
        }

        /* Features Section as Cards */
        .features {
            background: #fff;
            padding: 80px 0;
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 36px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .feature-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            padding: 38px 28px 32px 28px;
            text-align: center;
            border: 2px solid transparent;
            transition: box-shadow 0.2s, border-color 0.2s, transform 0.2s;
            position: relative;
        }

        .feature-card:hover {
            box-shadow: 0 8px 32px rgba(163, 109, 22, 0.10);
            border-color: #a36d16;
            transform: translateY(-6px) scale(1.03);
        }

        .feature-icon {
            font-size: 2.7rem;
            color: #a36d16;
            margin-bottom: 18px;
            display: inline-block;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #a36d16;
            margin-bottom: 14px;
        }

        .feature-card p {
            color: #555;
            font-size: 1.05rem;
            line-height: 1.7;
        }

        @media (max-width: 800px) {
            .features-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        /* Footer Styles */
        footer {
            background: #111;
            color: #fff;
            padding: 80px 0 0;
            position: relative;
            overflow: hidden;
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), #fff);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .footer-section {
            margin-bottom: 40px;
        }

        .footer-section h3 {
            color: var(--accent-color);
            font-size: 1.3rem;
            margin-bottom: 24px;
            position: relative;
            padding-bottom: 12px;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: var(--accent-color);
        }

        .footer-section p {
            color: #aaa;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-section ul li {
            margin-bottom: 12px;
        }

        .footer-section ul li a {
            color: #aaa;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-section ul li a:hover {
            color: var(--accent-color);
            transform: translateX(5px);
        }

        .footer-section ul li a i {
            font-size: 0.9rem;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #aaa;
        }

        .contact-item i {
            color: var(--accent-color);
            font-size: 1.2rem;
        }

        .social-links {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #fff;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--accent-color);
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding: 24px;
            background: rgba(0, 0, 0, 0.2);
            margin-top: 60px;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .copyright p {
            color: #aaa;
            margin: 0;
        }

        .developer-credit {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #aaa;
            font-size: 0.9rem;
        }

        .developer-logo {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            background: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .last-updated {
            color: #666;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .last-updated i {
            color: var(--accent-color);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .copyright {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .developer-credit,
            .last-updated {
                justify-content: center;
            }
        }

        /* FAQ Section Styles */
        .faq-section {
            background: #fafbfc;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        .faq-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(163, 109, 22, 0.03) 0%, rgba(163, 109, 22, 0.1) 100%);
            z-index: 0;
        }

        .faq-container {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .faq-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .faq-title h2 {
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 16px;
            position: relative;
            display: inline-block;
        }

        .faq-title h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: var(--accent-color);
            border-radius: 2px;
        }

        .faq-title p {
            color: var(--subtext-color);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .faq-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(163, 109, 22, 0.1);
            position: relative;
            overflow: hidden;
        }

        .faq-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 0;
            background: var(--accent-color);
            transition: height 0.3s ease;
        }

        .faq-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .faq-card:hover::before {
            height: 100%;
        }

        .faq-question {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-right: 24px;
            position: relative;
        }

        .faq-question i {
            color: var(--accent-color);
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .faq-card.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            color: var(--subtext-color);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .faq-card.active .faq-answer {
            display: block;
        }

        @media (max-width: 768px) {
            .faq-grid {
                grid-template-columns: 1fr;
            }

            .faq-title h2 {
                font-size: 2rem;
            }
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
                padding: 0 40px;
            }

            .hero-text h1 {
                font-size: 2.4rem;
            }

            .hero-text h2 {
                font-size: 1.8rem;
            }

            .features-grid {
                gap: 24px;
            }
        }

        @media (max-width: 992px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding: 60px 0;
            }

            .hero-text {
                margin-bottom: 40px;
            }

            .hero-image {
                max-width: 80%;
                margin: 0 auto;
            }

            .nav-links {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .faq-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 24px;
            }

            .hero-text h1 {
                font-size: 1.8rem;
            }

            .hero-text h2 {
                font-size: 1.4rem;
            }

            .hero-text ul li {
                font-size: 0.9rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .feature-card {
                padding: 24px;
            }

            .footer-content {
                grid-template-columns: 1fr;
            }

            .copyright {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .login-modal {
                width: 95%;
                padding: 20px;
                max-height: 85vh;
            }

            .modal-header {
                margin-bottom: 16px;
            }

            .modal-header h2 {
                font-size: 1.2rem;
            }

            .login-options {
                gap: 12px;
            }

            .login-option {
                padding: 12px;
            }

            .login-option i {
                font-size: 1.2rem;
            }

            .option-title {
                font-size: 0.9rem;
            }

            .option-description {
                font-size: 0.8rem;
            }

            .close-modal {
                padding: 4px;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 16px;
            }

            .hero-text,
            .hero-image {
                max-width: 100% !important;
            }

            .features-grid {
                max-width: 100% !important;
            }

            .faq-grid {
                max-width: 100% !important;
            }

            .footer-content {
                max-width: 100% !important;
            }

            /* Make flex containers stack vertically */
            .hero .container {
                flex-direction: column !important;
            }

            .registration-process .container {
                flex-direction: column !important;
            }

            .meet-md .container {
                flex-direction: column !important;
            }

            .support-section .container {
                flex-direction: column !important;
            }

            .nav-right {
                flex-direction: column !important;
                align-items: center !important;
                gap: 12px !important;
            }

            .nav-left {
                flex-direction: column !important;
                align-items: center !important;
                gap: 12px !important;
            }

            .login-option {
                flex-direction: column !important;
                text-align: center !important;
            }

            .login-option i {
                margin-bottom: 8px !important;
            }

            .contact-item {
                flex-direction: column !important;
                text-align: center !important;
                gap: 8px !important;
            }

            .social-links {
                justify-content: center !important;
            }

            .developer-credit {
                flex-direction: column !important;
                align-items: center !important;
                gap: 8px !important;
            }

            .copyright {
                flex-direction: column !important;
                gap: 12px !important;
                text-align: center !important;
            }

            .last-updated {
                justify-content: center !important;
            }
        }

        @media (max-width: 400px) {
            .hero-text h1 {
                font-size: 1.3rem;
            }

            .hero-text h2 {
                font-size: 1.1rem;
            }

            .hero-text ul li {
                font-size: 0.8rem;
            }

            .btn {
                font-size: 0.85rem;
                padding: 8px 16px;
            }

            .feature-card {
                padding: 16px;
            }

            .feature-card h3 {
                font-size: 1rem;
            }

            .feature-card p {
                font-size: 0.85rem;
            }

            .login-modal {
                padding: 16px;
                width: 92%;
            }

            .modal-header h2 {
                font-size: 1rem;
            }

            .login-option {
                padding: 10px;
            }

            .option-title {
                font-size: 0.85rem;
            }

            .option-description {
                font-size: 0.75rem;
            }

            /* Additional mobile-specific flex adjustments */
            .hero-image img {
                max-width: 100% !important;
                height: auto !important;
            }

            .registration-process img {
                max-width: 100% !important;
                height: auto !important;
            }

            .meet-md img {
                max-width: 100% !important;
                height: auto !important;
            }
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            padding: 80px 24px 24px;
            max-width: 100vw !important;
            overflow-x: hidden;
        }

        .mobile-menu.active {
            display: block;
        }

        .mobile-menu-close {
            position: absolute;
            top: 24px;
            right: 24px;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .mobile-nav-links a {
            color: #fff;
            text-decoration: none;
            font-size: 1.2rem;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mobile-nav-links a i {
            color: var(--accent-color);
        }

        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <div class="nav-left">
                    <a href="#" class="logo">
                        <img src="./landing_assets/images/gdLogo.png" alt="Golden Dream Logo" style="height:32px;"> Golden Dream
                    </a>
                    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="nav-links">
                        <a href="./">Home</a>
                        <!-- <a href="./AboutUs">About Us</a>
                        <a href="./Gallery">Gallery</a>
                        <a href="./Certificates">Certificates</a>
                        <a href="./SavingsPlan">Savings Plan</a>
                        <a href="./Blog">Blog</a>
                        <a href="./ContactUs">Contact Us</a> -->
                    </div>
                </div>
                <div class="nav-right">
                    <div class="country-flag">
                        <img src="./landing_assets/images/india.png" alt="India Flag">
                        <span>India</span>
                    </div>
                    <div class="login-dropdown">
                        <button class="login-btn" onclick="openLoginModal()">
                            <i class="fas fa-user"></i>
                            Login
                        </button>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal">
        <div class="login-modal">
            <div class="modal-header">
                <h2>Choose Login Type</h2>
                <button class="close-modal" onclick="closeLoginModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="login-options">
                <a href="../customer" class="login-option">
                    <i class="fas fa-user-circle"></i>
                    <div class="option-content">
                        <div class="option-title">Login as Customer</div>
                        <div class="option-description">Access your investment dashboard</div>
                    </div>
                </a>
                <a href="../promoter/" class="login-option">
                    <i class="fas fa-user-tie"></i>
                    <div class="option-content">
                        <div class="option-title">Login as Promoter</div>
                        <div class="option-description">Manage your promoter account</div>
                    </div>
                </a>
                <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                    <!-- <p style="color: var(--subtext-color); margin-bottom: 15px;">Don't have an account? <a href="https://goldendream.in//refer?id=GDP0001&ref=NTAw" style="color: var(--accent-color); text-decoration: none; font-weight: 600;">Register Now</a></p> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:40px;">
            <div class="hero-text">
                <h2>Empowering Your Financial Future</h2>
                <h1>Grow Your Wealth with Golden Dream</h1>
                <ul>
                    <li>✔ <span>Secure, trusted investment schemes</span></li>
                    <li>✔ <span>Flexible payment options</span></li>
                    <li>✔ <span>Expert financial guidance</span></li>
                    <li>✔ <span>24/7 customer support</span></li>
                </ul>
                <div class="price">Start from just <span style="color:#000;font-weight:700;">₹1000</span> <span class="sub-price">minimum investment</span></div>
                <!-- <a href="https://goldendream.in//refer?id=GDP0001&ref=NTAw" class="btn">Start Your Journey</a> -->
            </div>
            <div class="hero-image">
                <img src="./landing_assets/images/hero.gif" alt="Golden Dream Platform">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Golden Dream?</h2>
                <p>Discover the benefits of joining our platform and how we can help you achieve your financial goals.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure Investments</h3>
                    <p>Your investments are protected with our state-of-the-art security measures and transparent processes.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Growth Opportunities</h3>
                    <p>Access a variety of investment schemes designed to maximize your returns and grow your wealth.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h3>Flexible Payments</h3>
                    <p>Choose from multiple payment options and manage your investments with ease through our platform.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Start Your Investment Journey?</h2>
            <p>Join thousands of satisfied investors who have already discovered the benefits of Golden Dream.</p>
            <!-- <a href="https://goldendream.in//refer?id=GDP0001&ref=NTAw" class="btn">Sign Up Now</a> -->
        </div>
    </section>

    <!-- Registration Process Section -->
    <section class="registration-process" style="background:#f7f7fa; padding: 80px 0;">
        <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:40px;">
            <div style="flex:1.2;">
                <h2 style="font-size:2.3rem;font-weight:700;margin-bottom:32px;color:#111;">Register with Golden Dream in 3 easy steps</h2>
                <div style="border-left:3px solid #111;padding-left:18px;margin-bottom:32px;">
                    <div style="margin-bottom:32px;">
                        <span style="font-size:1.3rem;font-weight:600;color:#111;">1. Create your account</span>
                    </div>
                    <div style="margin-bottom:32px;">
                        <span style="font-size:1.3rem;font-weight:600;color:#a36d16;">2. Complete your profile</span>
                        <div style="color:#555;font-size:1rem;margin-top:8px;max-width:420px;">Fill in your personal and financial details to unlock personalized investment opportunities. Your information is always secure with us.</div>
                    </div>
                    <div style="margin-bottom:32px;">
                        <span style="font-size:1.3rem;font-weight:600;color:#111;">3. Start investing</span>
                    </div>
                </div>
                <!-- <a href="https://goldendream.in//refer?id=GDP0001&ref=NTAw" class="btn" style="margin-top:12px;">Get started</a> -->
            </div>
            <div style="flex:1;display:flex;align-items:center;justify-content:center;">
                <div style="background:#fff;border-radius:24px;padding:32px 24px;box-shadow:0 8px 32px rgba(0,0,0,0.08);display:flex;align-items:center;justify-content:center;min-width:320px;min-height:320px;">
                    <img src="./landing_assets/images/register.png" alt="Register with Golden Dream" style="max-width:260px;border-radius:16px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Meet Our MD Section -->
    <section class="meet-md" style="background:#fff; padding: 80px 0;">
        <div class="container" style="display:flex;align-items:center;gap:48px;">
            <div style="flex:1;position:relative;min-width:320px;max-width:400px;">
                <div style="position:relative;z-index:2;">
                    <a href="https://www.linkedin.com/in/sameer-akbar-27966033b/" target="_blank" style="display: block; cursor: pointer;">
                        <img src="./landing_assets/images/sameer.png" alt="Sameer Akbar, MD" style="width:80%;border-radius:18px;filter:grayscale(1);transition: filter 0.3s ease;" onmouseover="this.style.filter='grayscale(0)'" onmouseout="this.style.filter='grayscale(1)'">
                    </a>
                </div>
                <!-- Geometric overlays -->
                <div style="position:absolute;top:30px;left:30px;width:60px;height:60px;background:#e6ff00;z-index:1;opacity:0.7;"></div>
                <div style="position:absolute;bottom:40px;right:20px;width:40px;height:40px;background:#111;z-index:1;opacity:0.7;"></div>
                <div style="position:absolute;bottom:10px;left:60px;width:30px;height:30px;background:#e6ff00;z-index:1;opacity:0.7;"></div>
            </div>
            <div style="flex:2;">
                <div style="color:#a36d16;font-size:2.5rem;line-height:1;">&#10077;</div>
                <div style="font-size:2rem;font-weight:500;color:#222;margin-bottom:32px;max-width:700px;">
                    At <span style="color:#a36d16;font-weight:600;">Golden Dream</span>, our mission is to <span style="color:#a36d16;font-weight:600;">empower every individual</span> to achieve financial freedom. We believe in <span style="color:#a36d16;font-weight:600;">trust, transparency,</span> and <span style="color:#a36d16;font-weight:600;">growth for all</span>.
                </div>
                <div style="margin-top:32px;border-top:1px solid #eee;padding-top:18px;">
                    <div style="font-weight:700;font-size:1.1rem;color:#222;">Sameer Akbar</div>
                    <div style="color:#a36d16;font-weight:600;">Managing Director</div>
                    <div style="color:#555;font-size:1rem;">Visionary leader dedicated to helping you build a brighter financial future with Golden Dream.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Section -->
    <section class="support-section" style="background:#fff; padding: 80px 0;">
        <div class="container" style="display:flex;align-items:center;gap:48px;">
            <div style="flex:1.2;">
                <h2 style="font-size:2.3rem;font-weight:700;margin-bottom:32px;color:#222;">We're here for you 24/7</h2>
                <ul style="list-style:none;padding:0;margin:0;">
                    <li style="margin-bottom:22px;display:flex;align-items:flex-start;gap:12px;">
                        <span style="color:#00b67a;font-size:1.3rem;">✔</span>
                        <span style="font-size:1.1rem;color:#222;">Reach us on live chat, phone, or email—whenever you need help.</span>
                    </li>
                    <li style="margin-bottom:22px;display:flex;align-items:flex-start;gap:12px;">
                        <span style="color:#00b67a;font-size:1.3rem;">✔</span>
                        <span style="font-size:1.1rem;color:#222;">No long waits—our team resolves most issues within minutes.</span>
                    </li>
                    <li style="margin-bottom:22px;display:flex;align-items:flex-start;gap:12px;">
                        <span style="color:#00b67a;font-size:1.3rem;">✔</span>
                        <span style="font-size:1.1rem;color:#222;">Support in 10+ languages for a smooth experience, wherever you are.</span>
                    </li>
                </ul>
            </div>
            <div style="flex:1;display:flex;align-items:center;justify-content:center;position:relative;min-width:380px;">
                <!-- Geometric accent -->
                <div style="position:absolute;top:0;left:30px;width:260px;height:320px;background:#e6ff00;border-radius:24px;z-index:0;"></div>
                <!-- Chat UI -->
                <div style="position:relative;z-index:2;">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px;">
                        <img src="https://cdn.dribbble.com/userupload/41685542/file/original-4b859c8a666e90fc4624a44648c1f886.gif" alt="Support Agent" style="width:56px;height:56px;border-radius:50%;border:4px solid #a36d16;object-fit:cover;">
                        <div style="background:#f7f7fa;padding:16px 22px;border-radius:14px;font-size:1.08rem;color:#222;box-shadow:0 2px 8px rgba(0,0,0,0.04);max-width:260px;">Hey, can you tell me how to start investing?</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:16px;">
                        <div style="background:#111;padding:16px 22px;border-radius:14px;font-size:1.08rem;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.08);max-width:260px;">
                            Absolutely! Our team will guide you through every step. If you need help, just ask anytime.
                        </div>
                        <img src="https://cdn.dribbble.com/userupload/41685542/file/original-4b859c8a666e90fc4624a44648c1f886.gif" alt="Customer" style="width:56px;height:56px;border-radius:50%;border:4px solid #a36d16;object-fit:cover;">
                    </div>
                </div>
                <!-- Decorative squares -->
                <div style="position:absolute;top:30px;right:0;width:16px;height:16px;background:#a36d16;z-index:3;"></div>
                <div style="position:absolute;bottom:30px;right:40px;width:16px;height:16px;background:#a36d16;z-index:3;"></div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="faq-container">
            <div class="faq-title">
                <h2>Golden Dream FAQs</h2>
                <p>Find answers to frequently asked questions about Golden Dream and our investment platform.</p>
            </div>
            <div class="faq-grid">
                <!-- Column 1 -->
                <div class="faq-card">
                    <div class="faq-question">
                        <span>What is Golden Dream?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Golden Dream is a secure investment platform designed to help you grow your wealth through trusted schemes and expert guidance. We provide a range of investment options tailored to your financial goals.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>How do I register on Golden Dream?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Click the 'Start Your Journey' button and follow the simple steps to create your account. You'll need to provide basic information and complete your profile to start investing.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>Is my investment safe?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, we use advanced security measures and only offer thoroughly vetted investment schemes. Your funds are protected with state-of-the-art encryption and security protocols.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>What is the minimum investment amount?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        You can start investing with as little as ₹1000 on Golden Dream. We offer flexible investment options to suit various budgets and financial goals.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>How do I withdraw my earnings?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        You can request a withdrawal anytime from your dashboard. Funds are typically processed within 1-2 business days and transferred to your registered bank account.
                    </div>
                </div>
                <!-- Column 2 -->
                <div class="faq-card">
                    <div class="faq-question">
                        <span>Can I invest in multiple schemes?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, you can diversify your portfolio by investing in multiple schemes at once. This helps spread risk and maximize potential returns.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>Is there any lock-in period?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Some schemes may have a lock-in period. Please check the details of each scheme before investing. We offer both short-term and long-term investment options.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>How do I contact support?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        You can reach our support team 24/7 via live chat, phone, or email. We're committed to providing prompt and helpful assistance for all your queries.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>Are there any hidden charges?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        No, Golden Dream is fully transparent. All fees are clearly mentioned before you invest. We believe in complete transparency with our investors.
                    </div>
                </div>
                <div class="faq-card">
                    <div class="faq-question">
                        <span>Can I track my investments online?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, you can monitor all your investments and returns in real-time from your Golden Dream dashboard. We provide detailed analytics and reports for better tracking.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Golden Dream</h3>
                <p>Empowering your financial future with secure investment opportunities and expert guidance. Join us in building a prosperous tomorrow.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                    <li><a href="#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                    <li><a onclick="openLoginModal()"><i class="fas fa-chevron-right"></i> Login</a></li>
                    <!-- <li><a href="https://goldendream.in//refer?id=GDP0001&ref=NTAw"><i class="fas fa-chevron-right"></i> Register</a></li> -->
                    <li><a href="#contact"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Investment Plans</h3>
                <ul>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Short Term Plans</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Long Term Plans</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> High Return Plans</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Fixed Deposit Plans</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Custom Plans</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Contact Info</h3>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>2-108/C-7, Ground Floor, Sri Mantame Complex, Near Soorya Infotech Park, Kurnadu Post, Mudipu Road, Bantwal- 574153</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>+91 99951 94472</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>goldendream175@gmail.com</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <span>Mon - Sun: 9:30 AM - 6:00 PM</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="copyright">
            <div class="developer-credit">

                <span>Developed by <a style="text-decoration: none;color:teal;" href="https://intelexsolutions.in/"> <img src="./landing_assets/images/intelex.png" style="height: 20px;margin-bottom:-5px;" alt=""> Intelex Solutions</a></span>
            </div>
            <p>&copy; 2025 Golden Dream. All rights reserved.</p>
            <div class="last-updated">
                <i class="fas fa-clock"></i>
                <span>Last updated: <?php echo date('F d, Y'); ?></span>
            </div>
        </div>
    </footer>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="mobile-menu-close" onclick="toggleMobileMenu()">
            <i class="fas fa-times"></i>
        </button>
        <div class="mobile-nav-links">
            <a href="./"><i class="fas fa-home"></i> Home</a>
            <!-- <a href="./AboutUs"><i class="fas fa-info-circle"></i> About Us</a>
            <a href="./Gallery"><i class="fas fa-images"></i> Gallery</a>
            <a href="./Certificates"><i class="fas fa-certificate"></i> Certificates</a>
            <a href="./SavingsPlan"><i class="fas fa-piggy-bank"></i> Savings Plan</a>
            <a href="./Blog"><i class="fas fa-blog"></i> Blog</a>
            <a href="./ContactUs"><i class="fas fa-envelope"></i> Contact Us</a> -->
        </div>
    </div>

    <script>
        // Auto show login modal after 2 seconds
        setTimeout(() => {
            openLoginModal();
        }, 2000);

        function openLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.style.display = 'block';
            setTimeout(() => {
                modal.classList.add('active');
                document.querySelector('.login-modal').classList.add('active');
            }, 10);
        }

        function closeLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.classList.remove('active');
            document.querySelector('.login-modal').classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Close modal when clicking outside
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLoginModal();
            }
        });

        // FAQ Accordion
        const faqCards = document.querySelectorAll('.faq-card');
        faqCards.forEach(card => {
            const question = card.querySelector('.faq-question');
            question.addEventListener('click', () => {
                const isActive = card.classList.contains('active');
                faqCards.forEach(c => c.classList.remove('active'));
                if (!isActive) {
                    card.classList.add('active');
                }
            });
        });

        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('active');
            document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        }

        // Close mobile menu when clicking outside
        document.getElementById('mobileMenu').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleMobileMenu();
            }
        });
    </script>
</body>

</html>