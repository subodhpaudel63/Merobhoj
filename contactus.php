<?php
ob_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';

// Check if user is logged in
$user = getUserFromCookie();

// If user is not logged in, redirect to login page
if (!$user) {
    $_SESSION['msg'] = [
        'type' => 'error',
        'text' => 'You must be logged in to send feedback.'
    ];
    header('Location: ./login.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mero Bhoj | Contact</title>
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
      integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css"/>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <?php require_once __DIR__ . '/config/bootstrap.php'; ?>
    <link rel="stylesheet" href="./assets/css/style.css" />
    
    <style>
      /* Toast styling */
      .toast-success {
        background-color: #0f5132 !important;
        border-color: #0f5132 !important;
        color: white !important;
      }
      .toast-error {
        background-color: #842029 !important;
        border-color: #842029 !important;
        color: white !important;
      }

      /* Nav buttons */
      .nav-button {
        background-color: #ff0000;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
      }
      .nav-button:hover { background-color: #cc0000; color: white; text-decoration: none; }
      .nav-button-outline {
        background-color: transparent;
        color: #ff0000;
        border: 2px solid #ff0000;
        padding: 6px 18px;
        border-radius: 30px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
      }
      .nav-button-outline:hover { background-color: #ff0000; color: white; text-decoration: none; }

      /* Login modal */
      .login-modal .modal-content { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
      .login-modal .modal-header { background: linear-gradient(135deg, #ff6a00, #d32f2f); color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; border: none; }
      .login-modal .btn-login { background: linear-gradient(135deg, #ff6a00, #d32f2f); border: none; color: white; padding: 10px; font-weight: 600; }
      .login-modal .btn-login:hover { background: linear-gradient(135deg, #e65f00, #c62828); color: white; }

      /* =========================================================
         FEEDBACK FORM — SMOOTH POLISH
      ========================================================= */

      /* ── Card ── */
      .feedback-form-card {
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.04), 0 16px 48px rgba(0,0,0,0.09);
        padding: 48px 44px 40px;
        position: relative;
        overflow: hidden;
      }
      /* animated top accent */
      .feedback-form-card::before {
        content: '';
        position: absolute;
        top: 0; left: -100%; right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, #ff6a00, #ffb347, #ff6a00, transparent);
        background-size: 300% auto;
        animation: accentSlide 3s ease-in-out infinite;
      }
      @keyframes accentSlide {
        0%   { background-position: 0% center;   left: -100%; }
        50%  { background-position: 100% center; left: 0;     }
        100% { background-position: 200% center; left: 100%;  }
      }

      /* ── Title ── */
      .feedback-title {
        font-size: 1.9rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 0.3rem;
        letter-spacing: -0.02em;
      }
      .feedback-title-line {
        width: 0;
        height: 3px;
        border-radius: 4px;
        background: linear-gradient(90deg, #ff6a00, #ff9800);
        margin-bottom: 2rem;
        animation: lineGrow 0.8s cubic-bezier(.22,1,.36,1) 0.3s forwards;
      }
      @keyframes lineGrow {
        to { width: 52px; }
      }

      /* ── Star rating ── */
      .star-rating-wrap {
        background: #fafafa;
        border: 1.5px solid #f0f0f0;
        border-radius: 14px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 1.4rem;
        transition: border-color 0.3s, box-shadow 0.3s;
      }
      .star-rating-wrap:hover {
        border-color: #ffd59e;
        box-shadow: 0 2px 12px rgba(255,152,0,0.10);
      }
      .star-rating-wrap .sr-label {
        font-size: 0.82rem;
        color: #999;
        font-weight: 500;
        white-space: nowrap;
        letter-spacing: 0.03em;
        flex-shrink: 0;
      }
      .star-rating {
        display: flex;
        flex-direction: row-reverse;
        gap: 2px;
      }
      .star-rating input { display: none; }
      .star-rating .star {
        font-size: 1.65rem;
        color: #e0e0e0;
        cursor: pointer;
        transition: color 0.18s cubic-bezier(.4,0,.2,1),
                    transform 0.18s cubic-bezier(.34,1.56,.64,1),
                    text-shadow 0.18s ease;
        line-height: 1;
        user-select: none;
      }
      .star-rating input:checked ~ .star,
      .star-rating .star:hover,
      .star-rating .star:hover ~ .star {
        color: #ff9800;
        text-shadow: 0 2px 8px rgba(255,152,0,0.35);
      }
      .star-rating .star:hover {
        transform: scale(1.3) translateY(-2px);
      }
      .star-rating input:checked + .star {
        animation: starPop 0.3s cubic-bezier(.34,1.56,.64,1);
      }
      @keyframes starPop {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.45) translateY(-3px); }
        100% { transform: scale(1); }
      }
      .sr-hint {
        font-size: 0.75rem;
        color: #ffb347;
        font-weight: 600;
        margin-left: auto;
        opacity: 0;
        transform: translateX(-6px);
        transition: opacity 0.25s, transform 0.25s;
      }
      .sr-hint.visible { opacity: 1; transform: translateX(0); }

      /* ── Chips ── */
      .feedback-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 1.6rem;
      }
      .feedback-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 15px;
        border-radius: 50px;
        border: 1.5px solid #ebebeb;
        font-size: 0.8rem;
        font-weight: 500;
        color: #666;
        cursor: pointer;
        background: #fdfdfd;
        transition: border-color 0.22s ease,
                    color 0.22s ease,
                    background 0.22s ease,
                    transform 0.22s cubic-bezier(.34,1.56,.64,1),
                    box-shadow 0.22s ease;
        user-select: none;
      }
      .feedback-chip i { transition: transform 0.22s cubic-bezier(.34,1.56,.64,1); }
      .feedback-chip:hover {
        border-color: #ffb347;
        color: #ff6a00;
        background: #fffbf5;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,152,0,0.12);
      }
      .feedback-chip:hover i { transform: rotate(-10deg) scale(1.15); }
      .feedback-chip.active {
        border-color: transparent;
        background: linear-gradient(135deg, #ff6a00, #ff9800);
        color: #fff;
        box-shadow: 0 4px 16px rgba(255,106,0,0.28);
        transform: translateY(-1px);
      }
      .feedback-chip.active i { transform: scale(1.1); }

      /* ── Floating-label fields ── */
      .ff-group {
        position: relative;
        margin-bottom: 0;
      }
      /* The wrapper that animates in */
      .ff-group {
        opacity: 0;
        transform: translateY(12px);
        animation: fieldIn 0.45s cubic-bezier(.22,1,.36,1) forwards;
      }
      .ff-group:nth-child(1) { animation-delay: 0.05s; }
      .ff-group:nth-child(2) { animation-delay: 0.12s; }
      .ff-group:nth-child(3) { animation-delay: 0.19s; }
      .ff-group:nth-child(4) { animation-delay: 0.26s; }
      @keyframes fieldIn {
        to { opacity: 1; transform: translateY(0); }
      }

      /* Floating label */
      .ff-group .ff-label {
        position: absolute;
        left: 42px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.9rem;
        color: #bbb;
        pointer-events: none;
        transition: top 0.22s cubic-bezier(.22,1,.36,1),
                    transform 0.22s cubic-bezier(.22,1,.36,1),
                    font-size 0.22s cubic-bezier(.22,1,.36,1),
                    color 0.22s ease,
                    left 0.22s cubic-bezier(.22,1,.36,1);
        background: transparent;
        padding: 0 4px;
        z-index: 2;
        line-height: 1;
      }
      .ff-group.textarea-group .ff-label {
        top: 18px;
        transform: none;
      }
      /* floated state */
      .ff-group .ff-field:focus ~ .ff-label,
      .ff-group .ff-field:not(:placeholder-shown) ~ .ff-label {
        top: 0;
        transform: translateY(-50%);
        font-size: 0.72rem;
        color: #ff6a00;
        left: 36px;
        background: #fff;
      }
      .ff-group.textarea-group .ff-field:focus ~ .ff-label,
      .ff-group.textarea-group .ff-field:not(:placeholder-shown) ~ .ff-label {
        top: 0;
        transform: translateY(-50%);
        font-size: 0.72rem;
        color: #ff6a00;
        left: 36px;
        background: #fff;
      }

      .ff-group .ff-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #ccc;
        font-size: 0.85rem;
        pointer-events: none;
        transition: color 0.25s ease, transform 0.25s cubic-bezier(.34,1.56,.64,1);
        z-index: 2;
      }
      .ff-group.textarea-group .ff-icon {
        top: 19px;
        transform: none;
      }
      .ff-group:focus-within .ff-icon {
        color: #ff6a00;
        transform: translateY(-50%) scale(1.1);
      }
      .ff-group.textarea-group:focus-within .ff-icon {
        transform: scale(1.1);
      }

      .ff-group .ff-field {
        width: 100%;
        border: 1.5px solid #ebebeb;
        border-radius: 14px;
        padding: 18px 40px 6px 42px;
        font-size: 0.92rem;
        color: #222;
        background: #fdfdfd;
        outline: none;
        transition: border-color 0.25s ease,
                    box-shadow 0.3s ease,
                    background 0.25s ease;
        resize: none;
        font-family: inherit;
        line-height: 1.4;
      }
      .ff-group .ff-field::placeholder { color: transparent; }
      .ff-group .ff-field:focus {
        border-color: #ff6a00;
        box-shadow: 0 0 0 4px rgba(255,106,0,0.09);
        background: #fff;
      }
      .ff-group .ff-field.is-valid   {
        border-color: #34c759;
        box-shadow: 0 0 0 3px rgba(52,199,89,0.08);
      }
      .ff-group .ff-field.is-invalid {
        border-color: #ff3b30;
        box-shadow: 0 0 0 3px rgba(255,59,48,0.08);
        animation: shake 0.35s ease;
      }
      @keyframes shake {
        0%,100% { transform: translateX(0); }
        20%     { transform: translateX(-5px); }
        40%     { transform: translateX(5px); }
        60%     { transform: translateX(-4px); }
        80%     { transform: translateX(3px); }
      }

      /* Tick / cross badge */
      .ff-check {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%) scale(0);
        font-size: 0.82rem;
        transition: transform 0.25s cubic-bezier(.34,1.56,.64,1), opacity 0.2s;
        opacity: 0;
        pointer-events: none;
      }
      .ff-group.textarea-group .ff-check { top: 19px; transform: scale(0); }
      .ff-field.is-valid   ~ .ff-check { transform: translateY(-50%) scale(1); opacity: 1; color: #34c759; }
      .ff-field.is-invalid ~ .ff-check { transform: translateY(-50%) scale(1); opacity: 1; color: #ff3b30; }
      .ff-group.textarea-group .ff-field.is-valid   ~ .ff-check { transform: scale(1); }
      .ff-group.textarea-group .ff-field.is-invalid ~ .ff-check { transform: scale(1); }

      /* Error message */
      .ff-error-msg {
        font-size: 0.75rem;
        color: #ff3b30;
        margin-top: 5px;
        padding-left: 6px;
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.28s ease, opacity 0.28s ease, margin-top 0.28s ease;
      }
      .ff-group.has-error .ff-error-msg {
        max-height: 40px;
        opacity: 1;
        margin-top: 5px;
      }

      /* Char counter */
      .ff-char-count {
        font-size: 0.72rem;
        color: #ccc;
        text-align: right;
        margin-top: 5px;
        transition: color 0.2s;
      }
      .ff-char-count.warn { color: #ff9800; }
      .ff-char-count.limit { color: #ff3b30; font-weight: 600; }

      /* ── Row gaps ── */
      .ff-row { display: flex; flex-direction: column; gap: 18px; margin-bottom: 18px; }

      /* ── Send button ── */
      .btn-send-feedback {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: linear-gradient(135deg, #ff6a00 0%, #ff9f43 100%);
        color: #fff;
        border: none;
        border-radius: 50px;
        padding: 15px 42px;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: transform 0.25s cubic-bezier(.34,1.56,.64,1),
                    box-shadow 0.25s ease,
                    background 0.3s ease;
        box-shadow: 0 6px 22px rgba(255,106,0,0.32);
        text-transform: uppercase;
        outline: none;
        min-width: 180px;
      }
      /* shine sweep */
      .btn-send-feedback::after {
        content: '';
        position: absolute;
        top: 0; left: -70%;
        width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.28), transparent);
        transform: skewX(-20deg);
        transition: left 0s;
      }
      .btn-send-feedback:hover::after {
        left: 130%;
        transition: left 0.55s ease;
      }
      .btn-send-feedback:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 32px rgba(255,106,0,0.42);
      }
      .btn-send-feedback:active {
        transform: translateY(-1px) scale(0.99);
        box-shadow: 0 4px 14px rgba(255,106,0,0.30);
        transition-duration: 0.1s;
      }
      /* spinner */
      .btn-spinner {
        display: none;
        width: 17px; height: 17px;
        border: 2.5px solid rgba(255,255,255,0.35);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.65s linear infinite;
        flex-shrink: 0;
      }
      .btn-send-feedback.loading .btn-spinner { display: inline-block; }
      .btn-send-feedback.loading .btn-label   { opacity: 0.8; }
      .btn-send-feedback.loading {
        pointer-events: none;
        transform: none;
        box-shadow: 0 6px 22px rgba(255,106,0,0.22);
      }
      @keyframes spin { to { transform: rotate(360deg); } }

      /* sent state */
      .btn-send-feedback.sent {
        background: linear-gradient(135deg, #34c759, #30d158);
        box-shadow: 0 6px 22px rgba(52,199,89,0.30);
        pointer-events: none;
        transform: scale(1.02);
      }

      /* ── Success overlay ── */
      .form-success-overlay {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 30px 20px 10px;
        min-height: 260px;
      }
      .form-success-overlay.visible { display: flex; }
      .success-icon-circle {
        width: 80px; height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ff6a00, #ff9800);
        display: flex; align-items: center; justify-content: center;
        font-size: 2.1rem; color: #fff;
        margin-bottom: 20px;
        box-shadow: 0 8px 30px rgba(255,106,0,0.30);
        opacity: 0;
        transform: scale(0.4);
        transition: opacity 0.4s ease, transform 0.5s cubic-bezier(.34,1.56,.64,1);
      }
      .form-success-overlay.visible .success-icon-circle {
        opacity: 1; transform: scale(1);
        transition-delay: 0.1s;
      }
      /* Pulse ring */
      .success-icon-circle::after {
        content: '';
        position: absolute;
        width: 80px; height: 80px;
        border-radius: 50%;
        border: 2px solid rgba(255,152,0,0.4);
        animation: pulseRing 1.5s ease-out 0.6s infinite;
      }
      @keyframes pulseRing {
        0%   { transform: scale(1);   opacity: 1; }
        100% { transform: scale(1.8); opacity: 0; }
      }
      .success-text {
        opacity: 0;
        transform: translateY(14px);
        transition: opacity 0.4s ease, transform 0.4s ease;
      }
      .form-success-overlay.visible .success-text {
        opacity: 1; transform: translateY(0);
        transition-delay: 0.3s;
      }
      .form-success-overlay h4 { font-weight: 700; font-size: 1.25rem; color: #1a1a1a; margin-bottom: 6px; }
      .form-success-overlay p  { color: #999; font-size: 0.88rem; line-height: 1.6; }
      .btn-send-another {
        margin-top: 20px;
        background: transparent;
        border: 1.5px solid #ff6a00;
        color: #ff6a00;
        border-radius: 50px;
        padding: 9px 28px;
        font-size: 0.83rem;
        font-weight: 600;
        cursor: pointer;
        letter-spacing: 0.04em;
        transition: background 0.22s ease, color 0.22s ease, transform 0.2s ease, box-shadow 0.22s ease;
        opacity: 0;
        transform: translateY(8px);
      }
      .form-success-overlay.visible .btn-send-another {
        opacity: 1; transform: translateY(0);
        transition-delay: 0.5s;
        transition: background 0.22s ease, color 0.22s ease,
                    transform 0.2s ease, box-shadow 0.22s ease,
                    opacity 0.3s 0.5s ease;
      }
      .btn-send-another:hover {
        background: #ff6a00; color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(255,106,0,0.25);
      }

      /* ── Contact info card ── */
      .contact-info-card {
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.04), 0 16px 48px rgba(0,0,0,0.08);
        padding: 36px 30px;
        height: 100%;
      }
      .contact-info-item {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 15px 0;
        border-bottom: 1px solid #f5f5f5;
        transition: transform 0.22s ease;
      }
      .contact-info-item:last-child { border-bottom: none; padding-bottom: 0; }
      .contact-info-item:hover { transform: translateX(4px); }
      .contact-icon-pill {
        width: 44px; height: 44px;
        border-radius: 12px;
        background: #fff5eb;
        display: flex; align-items: center; justify-content: center;
        color: #ff6a00;
        font-size: 0.95rem;
        flex-shrink: 0;
        transition: background 0.25s ease, transform 0.25s cubic-bezier(.34,1.56,.64,1), box-shadow 0.25s ease;
      }
      .contact-info-item:hover .contact-icon-pill {
        background: linear-gradient(135deg, #ff6a00, #ff9800);
        color: #fff;
        transform: scale(1.1) rotate(-4deg);
        box-shadow: 0 4px 12px rgba(255,106,0,0.25);
      }
      .contact-info-text strong {
        display: block;
        font-size: 0.75rem;
        color: #bbb;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 3px;
      }
      .contact-info-text p { margin: 0; font-size: 0.88rem; color: #444; line-height: 1.6; }

      /* ── Form note ── */
      #formNote {
        font-size: 0.78rem;
        color: #ff3b30;
        transition: opacity 0.3s;
      }

      @media (max-width: 991px) {
        .feedback-form-card { padding: 30px 20px 26px; }
        .contact-info-card  { margin-top: 28px; padding: 28px 20px; }
      }
      @media (max-width: 575px) {
        .btn-send-feedback { width: 100%; justify-content: center; }
        .ff-row { gap: 14px; }
      }
    </style>
  </head>
  <body>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000;">
      <?php if (isset($_SESSION['msg'])): $m=$_SESSION['msg']; unset($_SESSION['msg']); ?>
        <div class="toast show align-items-center border-0 <?php echo $m['type']==='success'?'toast-success':'toast-error'; ?>" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
          <div class="d-flex">
            <div class="toast-body small fw-semibold"><?php echo htmlspecialchars($m['text']); ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="loader">
      <i class="fas fa-utensils loader-icone"></i>
      <p>Mero Bhoj</p>
      <div class="loader-ellipses">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
    
    <header class="bg-white">
      <div class="container header my-3 d-none d-lg-flex">
        <div class="logo">
          <a href="./index.php">
            <i class="fa fa-utensils me-3 text-dark"></i>
            <h1 class="mb-0 text-dark">Mero Bhoj</h1>
          </a>
        </div>
        <div class="menus">
          <ul class="d-flex mb-0">
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4 text-dark" href="./index.php"
                >Home</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4 text-dark" href="./aboutus.php"
                >About</a
              >
            </li>
            
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4 text-dark" href="./menu.php"
                >Menu</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4 text-dark" href="./contactus.php"
                >Contact</a
              >
            </li>
          </ul>
        </div>
        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#">
            <i class="fa fa-search me-3 text-dark"></i>
          </a>
          <a class="text-decoration-none" id="shoppingbutton" href="#">
            <i class="fa fa-shopping-bag me-3 text-dark"></i>
          </a>
          <!-- Login and Signup buttons -->
          <div class="d-flex align-items-center">
            <a href="./login.php" class="nav-button">Login</a>
            <a href="./register.php" class="nav-button-outline">Sign Up</a>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-around py-3 align-items-center d-lg-none">
        <div id="hamburger">
          <i class="fa fa-2x fa-bars me-3 text-dark"></i>
        </div>
        <div class="mobile-nav-logo">
          <div class="logo">
            <a href="./index.php">
              <i class="fa fa-utensils me-3 text-dark"></i>
              <h1 class="mb-0 text-dark">Mero Bhoj</h1>
            </a>
          </div>
        </div>
        <div class="mobile-nav-icons">
          <div class="icons">
            <a class="text-decoration-none" id="searchBtnMobile" href="#">
              <i class="fa fa-search me-3 text-dark"></i>
            </a>
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="">
              <i class="fa fa-shopping-bag me-3 text-dark"></i>
            </a>
          </div>
        </div>
        <div class="position-fixed w-75 bg-white h-100 top-0 start-0" id="mobile-menu">
          <div id="hamburger-cross" class="d-flex justify-content-end align-items-center py-2">
            <i class="fa fa-2x fa-times me-3"></i>
          </div>
          <div class="menus">
            <ul class="d-flex flex-column ps-2 mb-0 mt-4">
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php"
                  >Home</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php"
                  >About</a
                >
              </li>
              
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                  >Menu</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php"
                  >Contact</a
                >
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>

   

    <main class="contact-page">
        <section class="page-banner d-flex align-items-center">
          <div class="container"> 
            <div class="row">
              <div class="banner-content">
                <h2 class="text-white display-3 text-center" data-aos="fade-right" data-aos-delay="3000">Contact Us</h2>
                <div class="divider" data-aos="fade-up-right" data-aos-delay="3000">
                    <div class="dot mb-2"></div>
                </div>
                <p class="text-white mb-0 text-center" data-aos="fade-left" data-aos-delay="3000">Let us know if you have any concern about our menu, service or other information you want to have</p>
            </div>
            </div>
          </div>
        </section>

 

<section class="contact-us my-5 py-5">
  <div class="container">
    <div class="row g-4 align-items-start">


      <!-- ══════════════════════════════════════════════════
           FEEDBACK FORM (left / wide column)
      ═══════════════════════════════════════════════════ -->
      <div class="col-lg-8" data-aos="fade-right">
        <div class="feedback-form-card">

          <h2 class="feedback-title">We Value Your Feedback</h2>
          <div class="feedback-title-line"></div>

          <!-- ── Form body (hidden after successful submit) ── -->
          <div id="feedbackFormWrap">

            <!-- 1. Star rating row -->
            <div class="star-rating-wrap">
              <span class="sr-label">Rate your experience</span>

              <!--
                Stars are laid out RTL so that the CSS sibling selector
                ( input:checked ~ .star ) fills all stars to the left of
                the selected one. Visual order is still left-to-right
                because flex-direction: row-reverse is on the parent.
              -->
              <div class="star-rating" id="starRating">
                <input type="radio" name="rating" id="s5" value="5">
                <label class="star" for="s5">&#9733;</label>

                <input type="radio" name="rating" id="s4" value="4">
                <label class="star" for="s4">&#9733;</label>

                <input type="radio" name="rating" id="s3" value="3">
                <label class="star" for="s3">&#9733;</label>

                <input type="radio" name="rating" id="s2" value="2">
                <label class="star" for="s2">&#9733;</label>

                <input type="radio" name="rating" id="s1" value="1">
                <label class="star" for="s1">&#9733;</label>
              </div>

              <!-- Emoji hint that slides in after a star is selected -->
              <span class="sr-hint" id="srHint"></span>
            </div><!-- /.star-rating-wrap -->


            <!-- 2. Category chips -->
            <div class="feedback-chips" id="feedbackChips">
              <span class="feedback-chip" data-val="Food Quality">
                <i class="fa fa-utensils fa-xs"></i> Food Quality
              </span>
              <span class="feedback-chip" data-val="Service">
                <i class="fa fa-concierge-bell fa-xs"></i> Service
              </span>
              <span class="feedback-chip" data-val="Ambience">
                <i class="fa fa-music fa-xs"></i> Ambience
              </span>
              <span class="feedback-chip" data-val="Delivery">
                <i class="fa fa-motorcycle fa-xs"></i> Delivery
              </span>
              <span class="feedback-chip" data-val="Pricing">
                <i class="fa fa-tag fa-xs"></i> Pricing
              </span>
              <span class="feedback-chip" data-val="Other">
                <i class="fa fa-ellipsis-h fa-xs"></i> Other
              </span>
            </div>

            <!-- Hidden field — JS writes the selected chip value here -->
            <input type="hidden" id="selectedCategory" value="">


            <!-- 3. Input fields -->
            <div class="row g-0">
              <div class="ff-row">

                <!-- Name + Email side by side on ≥sm -->
                <div class="row g-3">

                  <!-- Name -->
                  <div class="col-sm-6">
                    <div class="ff-group" id="grp-name">
                      <!--
                        placeholder=" " (a single space) is required so that
                        :placeholder-shown triggers correctly for the floating label.
                      -->
                      <input class="ff-field" id="ff-name"
                             type="text" placeholder=" "
                             maxlength="60" autocomplete="off">
                      <i class="ff-icon fa fa-user"></i>
                      <label class="ff-label" for="ff-name">Your Name</label>
                      <i class="ff-check fa fa-check-circle"></i>
                      <div class="ff-error-msg">Please enter your name.</div>
                    </div>
                  </div>

                  <!-- Email -->
                  <div class="col-sm-6">
                    <div class="ff-group" id="grp-email">
                      <input class="ff-field" id="ff-email"
                             type="email" placeholder=" "
                             maxlength="100" autocomplete="off">
                      <i class="ff-icon fa fa-envelope"></i>
                      <label class="ff-label" for="ff-email">Email Address</label>
                      <i class="ff-check fa fa-check-circle"></i>
                      <div class="ff-error-msg">Please enter a valid email.</div>
                    </div>
                  </div>

                </div><!-- /.row -->

                <!-- Phone (optional — full width) -->
                <div class="ff-group" id="grp-phone">
                  <input class="ff-field" id="ff-phone"
                         type="tel" placeholder=" "
                         maxlength="20" autocomplete="off">
                  <i class="ff-icon fa fa-phone"></i>
                  <label class="ff-label" for="ff-phone">
                    Phone Number
                    <span style="font-size:.7rem; color:#ccc;">(optional)</span>
                  </label>
                  <i class="ff-check fa fa-check-circle"></i>
                </div>

                <!-- Message textarea -->
                <div class="ff-group textarea-group" id="grp-msg">
                  <textarea class="ff-field" id="ff-msg"
                            rows="5" placeholder=" "
                            maxlength="500"></textarea>
                  <i class="ff-icon fa fa-comment-alt"></i>
                  <label class="ff-label" for="ff-msg">Share your thoughts with us…</label>
                  <i class="ff-check fa fa-check-circle"></i>
                  <!-- Character counter — JS updates the <span> -->
                  <div class="ff-char-count"><span id="charCount">0</span> / 500</div>
                  <div class="ff-error-msg">Please write a short message.</div>
                </div>

              </div><!-- /.ff-row -->
            </div><!-- /.row g-0 -->


            <!-- 4. Submit button + error note -->
            <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
              <button class="btn-send-feedback" id="sendFeedbackBtn" type="button">
                <span class="btn-spinner"></span>
                <span class="btn-label">
                  Send Feedback &nbsp;<i class="fa fa-paper-plane"></i>
                </span>
              </button>
              <!-- JS writes validation errors here -->
              <span id="formNote"></span>
            </div>

          </div><!-- /#feedbackFormWrap -->


          <!-- ── Success state (shown after submit, hidden by default) ── -->
          <div class="form-success-overlay" id="formSuccessOverlay">
            <div class="success-icon-circle" style="position: relative;">
              <i class="fa fa-check"></i>
            </div>
            <div class="success-text">
              <h4>Thank you for your feedback!</h4>
              <p>
                We appreciate you taking the time to share your experience.<br>
                Our team will get back to you soon.
              </p>
              <button class="btn-send-another" id="sendAnotherBtn"> Send Another</button>
            </div>
          </div>

        </div><!-- /.feedback-form-card -->
      </div><!-- /.col-lg-8 -->


      <!-- ══════════════════════════════════════════════════
           CONTACT INFO (right / narrow column)
      ═══════════════════════════════════════════════════ -->
      <div class="col-lg-4" data-aos="fade-left">
        <div class="contact-info-card">

          <h2 class="feedback-title" style="font-size: 1.6rem;">Contact Info</h2>
          <div class="feedback-title-line"></div>

          <div class="contact-info-item">
            <div class="contact-icon-pill">
              <i class="fa-solid fa-location-dot"></i>
            </div>
            <div class="contact-info-text">
              <strong>Restaurant 1</strong>
              <p>Pokhara-18, Lakeside</p>
            </div>
          </div>

          

          <div class="contact-info-item">
            <div class="contact-icon-pill">
              <i class="fa-solid fa-phone"></i>
            </div>
            <div class="contact-info-text">
              <strong>Phone</strong>
              <p>(012) 978 645 312</p>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-icon-pill">
              <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="contact-info-text">
              <strong>Email</strong>
              <p>Merobhoj@gmail.com</p>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-icon-pill">
              <i class="fa-solid fa-clock"></i>
            </div>
            <div class="contact-info-text">
              <strong>Opening Hours</strong>
              <p>Mon – Fri: 10am – 10pm<br>Sat – Sun: 9am – 11pm</p>
            </div>
          </div>

        </div><!-- /.contact-info-card -->
      </div><!-- /.col-lg-4 -->


    </div><!-- /.row -->
  </div><!-- /.container -->
</section>


      
        </script>


        <section class="map pb-0 pb-lg-5 ">
          <div class="container pb-5" data-aos="fade-right">
            <div class="row">
              <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3535.368578218674!2d83.95543131518434!3d28.216966389587442!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3995953911581a9f%3A0x3432207c7af3d35e!2sHotel%20Middle%20Star!5e0!3m2!1sen!2s!4v1707582903261!5m2!1sen!2s" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
          </div>
        </section>

        <section class="subscribe-us pb-5 mb-5">
          <img class="d-none d-lg-block" src="./assets/images/subscribe-us.png" alt="" data-aos="fade-down-right">
          <div class="container">
            <div class="row">
              <div class="col-lg-2">
              </div>
              <div class="col-lg-8 d-flex flex-column flex-md-row align-items-lg-center">
                <div class="content" data-aos="fade-right">
                  <h5 class="display-6 text-black">Subcribe Us Now</h5>
                  <p>
                    Get more news and delicious dishes everyday from us
                  </p>
                </div>
                <div class="subscribe-form d-flex ps-0 ms-0 ps-lg-5 ms-lg-5" data-aos="fade-left">
                  <div class="input-form w-100">
                    <input class="border-0 px-3 w-100" type="email" placeholder="Email">
                  </div>
                  <div class="input-button">
                    <a class="text-decoration-none" href="#">
                      <i class="fa fa-paper-plane"></i>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
    </main>

    <a href="#" id="back-to-top">
      <i class="fa-solid fa-angles-up"></i>
    </a>
     <?php include_once __DIR__ . '/footer.php'; ?>
   
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
      crossorigin="anonymous"
    ></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="./assets/js/script.js"></script>
  </body>
</html>