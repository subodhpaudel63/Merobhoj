<?php
// Load bootstrap first — defines asset() and starts the session before any output.
require_once __DIR__ . '/config/bootstrap.php';

// Detect direct browser access (this file opened directly instead of being included by a page)
$isStandalone = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
?>
<?php if ($isStandalone): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mero Bhoj</title>
    <!-- Favicons -->
    <link href="assets/img/logo.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
</head>
<body>
<?php endif; ?>
<a href="#" id="back-to-top">
      <i class="fa-solid fa-angles-up"></i>
    </a>

    <footer>
      <div class="container">
        <div class="row">
          <div class="footer-content col-xl-8  px-4">
            <div class="row">
              <div class="col-lg-6 px-0">
                <div class="logo" data-aos="fade-down-right">
                  <a href="./index.html">
                    <i class="fa fa-utensils me-3"></i>
                    <h1 class="mb-0">Mero Bhoj</h1>
                  </a>
                </div>
              </div>
              <div data-aos="fade-down" class="col-lg-6 pt-4 pt-lg-0 d-flex align-items-center justify-content-start justify-content-lg-end">
                <div class="social-icons d-flex">
                  <ul class="d-flex mb-0 ps-0">
                    <li class="mx-2"><a class="text-decoration-none text-white" href="https://www.facebook.com/subodh.paudel.779"><i class="fab fa-facebook"></i></a></li>
                    <li class="mx-2"><a class="text-decoration-none text-white" href=""><i class="fab fa-twitter"></i></a></li>
                    <li class="mx-2"><a class="text-decoration-none text-white" href="https://www.instagram.com/subodh_543/"><i class="fab fa-instagram"></i></a></li>
                    
                  </ul>
                </div>
              </div>
            </div>
            <div class="row pt-5 content-desc" data-aos="fade-right">
              <p class="px-0">Thank you for visiting Mero Bhoj—where every dish is a tribute to Nepali tradition and every guest is family.</p>
              </div>
            <div class="row" data-aos="fade-right">
              <div class="d-flex flex-column flex-lg-row px-0 justify-content-between">
                <div class="location d-flex align-items-center pe-2 py-3">
                  <i class="fa-solid fa-location-dot text-white fa-2x border-bottom pb-2"></i>
                  <div class="ps-3">
                    <p class="mb-0">
                      street No 1 Pokhara,Lakeside Nepal <br>
                     
                    </p>
                  </div>
                </div>
                <div class="location d-flex align-items-center pe-2 py-3">
                  <i class="fa-solid fa-mobile text-white fa-2x border-bottom pb-2"></i>
                  <div class="ps-3">
                    <p class="mb-0">
                      9748759699 <br>
                  
                      
                    </p>
                  </div>
                </div>
                <div class="location d-flex align-items-center pe-2 py-3">
                  <i class="fa-solid fa-envelope text-white fa-2x border-bottom pb-2"></i>
                  <div class="ps-3">
                    <p class="mb-0">
                      Merobhoj@gmail.com <br>
                      
                    </p>
                  </div>
                </div>
            </div>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="reservation-box" data-aos="fade-down-left">
              <div class="reservation-wrapper">
                <h2>Open Hours</h2>
                <div class="reservation-date-time">
                  <p>Tuesday: .......................... 7AM - 11PM</p>
                  <p>Wednesday: ..................... 7AM - 11PM</p>
                  <p>Thursday: ......................... 7AM - 11PM</p>
                  <p>Friday: ............................... 7AM - 11PM</p>
                  <p>Saturday: ........................... 7AM - 11PM</p>
                  <p>Sunday: ............................. 7AM - 11PM</p>
                  <p>Monday: ............................. 7AM- 11PM</p>
                </div>
                <h2 class="pb-2">Reservation Numbers</h2>
                <h3>9748759699</h3>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <p class="text-center pt-4 mt-3 pt-lg-0">&copy; <span id="copyrightCurrentYear"></span> <b> Mero Bhoj.</b> All rights reserved. Design by <a href="https://www.instagram.com/subodh_543/" class="fw-bold author-name">Subodh Paudel</a></p>
        </div>
      </div>
    </footer>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
      crossorigin="anonymous"
    ></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="<?php echo asset('js/script.js'); ?>"></script>
    <script src="<?php echo asset('js/clientscript.js'); ?>"></script>
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    
    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/9779748759699" class="floating-whatsapp" target="_blank" rel="noopener noreferrer">
        <i class="fab fa-whatsapp"></i>
    </a>
    
  </body>
</html>


