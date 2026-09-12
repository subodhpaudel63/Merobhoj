<?php
ob_start();
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is logged in
$currentUser = getUserFromCookie();

// If user is not logged in, redirect to appropriate dashboard
if (!$currentUser) {
    header('Location: /Merobhoj/login.php');
    exit;
}

// Logged-in user and profile image (from secure cookies)
$profileImg = 'assets/images/profile.jpg';
if (isset($_COOKIE['user_img'])) {
  $dec = decrypt($_COOKIE['user_img'], SECRET_KEY);
  if ($dec && is_string($dec)) {
    $candidate = ltrim($dec, '/');
    if (file_exists(__DIR__ . '/../' . $candidate)) {
      $profileImg = $candidate;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mero Bhoj - Contact</title>
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
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
   <link rel="stylesheet" href="../assets/css/clientstyles.css" />
    <!-- Include toast styles -->
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
  </head>
  <body>

    <div class="loader">
      <i class="fas fa-utensils loader-icone"></i>
      <p>Mero Bhoj</p>
      <div class="loader-ellipses">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
    
    <header>
      <div class="container header my-3 d-none d-lg-flex">
        <div class="logo">
          <a href="./index.php">
            <i class="fa fa-utensils me-3"></i>
            <h1 class="mb-0">Mero Bhoj</h1>
          </a>
        </div>
        <div class="menus">
          <ul class="d-flex mb-0">
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php"
                >Home</a>
              
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php"
                >About</a>

            </li>
            
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                >Menu</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php">My Order</a>
            </li>

            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./booking.php">My Booking</a>
            </li>

            <?php if (!$currentUser): ?>
              <li class="list-unstyled py-2">
                <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
              </li>
            <?php endif; ?>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a>
             
            </li>
          </ul>
        </div>
        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none" id="shoppingbutton" href="./cart.php"><i class="fa fa-shopping-bag me-3"></i></a>
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.preventDefault(); this.nextElementSibling.classList.toggle('show');">
                <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                
                <li><a class="dropdown-item" href="<?php echo url('includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-around py-3 align-items-center d-lg-none">
        <div id="hamburger">
          <i class="fa fa-2x fa-bars me-3"></i>
        </div>
        <div class="mobile-nav-logo">
          <div class="logo">
            <a href="./index.php">
              <i class="fa fa-utensils me-3"></i>
              <h1 class="mb-0">Mero Bhoj</h1>
            </a>
          </div>
        </div>
        <div class="mobile-nav-icons">
          <div class="icons">
            <a class="text-decoration-none" id="searchBtnMobile" href="#">
              <i class="fa fa-search me-3"></i>
            </a>
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="./cart.php">
              <i class="fa fa-shopping-bag me-3"></i>
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
              
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                  >Menu</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./booking.php"
                  >My Order</a
                >
              </li>
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2">
                  <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
                </li>
              <?php endif; ?>
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

        <!--
  CONTACT FEEDBACK FORM — contact-form.html
  ─────────────────────────────────────────
  Drop this entire <section> block inside your <main>.
  Requires:
    - Bootstrap 5 (grid + utilities)
    - Font Awesome 6
    - contact-form.css  (the form styles)
    - contact-form.js   (the form logic)
  Optional:
    - AOS (data-aos attributes below use it for scroll animations)
-->

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
              <p> Pokhara-18, Lakeside</p>
            </div>
          </div>

          

          <div class="contact-info-item">
            <div class="contact-icon-pill">
              <i class="fa-solid fa-phone"></i>
            </div>
            <div class="contact-info-text">
              <strong>Contact</strong>
              <p>(012) 978 645 312</p>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-icon-pill">
              <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="contact-info-text">
              <strong>Email</strong>
              <p class="contact-email-list">
                <a href="mailto:Merobhoj@gmail.com">Merobhoj@gmail.com</a><br>
                
              </p>
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



    
        


        <section class="map pb-0 pb-lg-5 ">
          <div class="container pb-5" data-aos="fade-right">
            <div class="row">
             <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3535.368578218674!2d83.95543131518434!3d28.216966389587442!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3995953911581a9f%3A0x3432207c7af3d35e!2sHotel%20Middle%20Star!5e0!3m2!1sen!2s!4v1707582903261!5m2!1sen!2s" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
        </section>

        <section class="subscribe-us pb-5 mb-5">
          <img class="d-none d-lg-block" src="<?php echo asset('images/subscribe-us.png'); ?>" alt="" data-aos="fade-down-right">
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
    
    <?php include_once __DIR__ . '/../includes/cart_drawer.php'; ?>
    <?php include_once __DIR__ . '/../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
      crossorigin="anonymous"
    ></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <script src="<?php echo asset('js/script.js'); ?>"></script>
   <script src="<?php echo asset('js/clientscript.js'); ?>"></script>
    <!-- Include toast notifications JS -->
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    
  </body>
</html>
