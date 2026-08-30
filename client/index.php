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

try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
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
    <title>Mero Bhoj</title>
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
    <link rel="stylesheet" href="<?php echo asset('css/clientstyle.css'); ?>" />
    
    
    <!-- Include toast styles -->
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
  </head>
  <body class="home-page">
    

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
              <a class="text-decoration-none text-uppercase p-4" href="./index.php"
                >Home</a
              >
            </li>
            <li class="list-unstyled py-2">
                <a class="text-decoration-none text-uppercase p-4" href="./aboutus.php"
                  >About</a
                >
            </li>
            
            </li>
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4" href="./menu.php"
                >Menu</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4" href="./myorder.php"
                >My Order</a>
            </li>
            <?php if (!$currentUser): ?>
              <li class="list-unstyled py-2">
                <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
              </li>
            <?php endif; ?>
            <li class="list-unstyled py-2">
              <a class="text-decoration-none text-uppercase p-4" href="./contactus.php"
                >Contact</a
              >
            </li>
            
            </li>
          </ul>
        </div>
        

        

        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none" id="shoppingbutton" href="./cart.php">
            <i class="fa fa-shopping-bag me-3"></i>
          </a>
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                
                <li><a class="dropdown-item" href="<?php echo url('includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </div>
            <div class="dropdown">
    

    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
      <li>
        <h6 class="dropdown-header">
          <?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>
        </h6>
      </li>

      <li><hr class="dropdown-divider"></li>

      

      <li>
        <a class="dropdown-item" href="<?php echo url('includes/logout.php'); ?>">
          <i class="fa fa-right-from-bracket me-2"></i>Logout
        </a>
      </li>

    </ul>
  </div>
<?php endif; ?>
         
        </div>
      </div>

      <div
        class="d-flex justify-content-around py-3 align-items-center d-lg-none"
      >
        <div id="hamburger">
          <i class="fa fa-2x fa-bars me-3 text-white"></i>
        </div>
        <div class="mobile-nav-logo">
          <div class="logo">
            <a href="./index.php">
              <i class="fa fa-utensils me-3 text-white"></i>
              <h1 class="mb-0 text-white">Mero Bhoj</h1>
            </a>
          </div>
        </div>
        <div class="mobile-nav-icons">
          <div class="icons">
            <a class="text-decoration-none" id="searchBtnMobile" href="#">
              <i class="fa fa-search me-3 text-white"></i>
            </a>
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="./cart.php">
              <i class="fa fa-shopping-bag me-3 text-white"></i>
            </a>
          </div>
        </div>
        <div
          class="position-fixed w-75 bg-white h-100 top-0 start-0"
          id="mobile-menu"
        >
          <div
            id="hamburger-cross"
            class="d-flex justify-content-end align-items-center py-2"
          >
            <i class="fa fa-2x fa-times me-3"></i>
          </div>
          <div class="menus">
            <ul class="d-flex flex-column ps-2 mb-0 mt-4">
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./index.php"
                  >Home</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./aboutus.php"
                  >About</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./menu.php"
                  >Menu</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./myorder.php"
                  >My Order</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./contactus.php"
                  >Contact</a
                >
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>

    

    
            <!-- Hero section -->
    <main>
      <section class="banner py-5">
        <div class="container py-5">
          <div class="row">
            <div class="col-md-6 banner-content pe-5" data-aos="fade-right" data-aos-delay="3000">
              <h1 class="display-2">Enjoy Our <br> Delicious Meal</h1>
                <p>
                 Step into the world of Mero Bhoj — where every bite tells a story. From fiery grills to rich Nepali spices, we serve tradition with a twist. Come hungry, leave inspired.
                </p>
                <p>🔸 Book your table now and taste the legend.
                   </p>
                <div class="book-a-table">
                  <div class="anim-layer"></div>
                  <a href="#book-table-section">Book a table</a>
                </div>
            </div>
            <div class="col-md-6 banner-img" data-aos="fade-left" data-aos-delay="3000">
               <img class="img  mt-5 mt-lg-0" src="<?php echo asset('images/naglosetx.png'); ?>" alt="">
            </div>
          </div>
        </div>
      </section>
      
      <section class="services my-5 py-5">
        <div class="container">
          <div class="row gy-4">
            <div class="col-md-3">
              <div class="cards px-4 py-5" data-aos="fade-right">
                <div class="anim-layer"></div>
                <div class="icon"> 
                  <i class="fa fa-3x fa-user-tie mb-4"></i>
                </div>
                <div class="heading">
                  <h5>Master Chefs</h5>
                </div>
                <div class="para">
                  <p>Our culinary team blends tradition with innovation—led by chefs who grew up savoring Nepali flavors and now craft dishes that honor heritage while thrilling modern palates</p>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="cards px-4 py-5" data-aos="fade-down">
                <div class="anim-layer"></div>
                <div class="icon">
                  <i class="fa fa-3x fa-utensils mb-4"></i>
                </div>
                <div class="heading">
                  <h5>Quality Food</h5>
                </div>
                <div class="para">
                  <p>Every plate is a tribute to Nepali soul food—locally sourced ingredients, slow-cooked broths, and spices ground fresh daily. Taste the difference in every bite.

Want to explore that next?
                     </p>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="cards px-4 py-5" data-aos="fade-up">
                <div class="anim-layer"></div>
                <div class="icon">
                  <i class="fa fa-3x fa-cart-plus mb-4"></i>
                </div>
                <div class="heading">
                  <h5>Online Order</h5>
                </div>
                <div class="para">
                  <p>Craving Mero Bhoj from home? Our seamless online ordering brings authentic Nepali cuisine straight to your doorstep—hot, fresh, and just a click away.
</p>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="cards px-4 py-5" data-aos="fade-left">
                <div class="anim-layer"></div>
                <div class="icon">
                  <i class="fa fa-3x fa-headset mb-4"></i>
                </div>
                <div class="heading">
                  <h5>24/7 Service</h5>
                </div>
                <div class="para">
                  <p>Whether it’s a late-night craving or an early morning gathering, we’re here for you. Mero Bhoj is open round the clock to serve comfort food whenever you need it.
                      </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="about-us py-5 my-5">
        <div class="container">
          <div class="row gy-5 g-lg-5 align-items-center">
              <div class="col-lg-6 about-img-box">
                  <div class="row g-3">
                      <div class="col-6" data-aos="fade-right">
                           <img class="img-fluid rounded w-100" src="<?php echo asset('images/about1.jpg'); ?>">
                      </div>
                      <div class="col-6 text-right" data-aos="fade-down">
                           <img class="img-fluid rounded w-75" src="<?php echo asset('images/about22.jpg'); ?>">
                      </div>
                      <div class="col-6 text-end" data-aos="fade-right">
                           <img class="img-fluid rounded w-75" src="<?php echo asset('images/about3.jpg'); ?>">
                      </div>
                      <div class="col-6 text-end" data-aos="fade-up">
                           <img class="img-fluid rounded w-100" src="<?php echo asset('images/about4.jpg'); ?>">
                      </div>
                  </div>
              </div>
              <div class="col-lg-6 about-content" data-aos="fade-left">
                  <h5 class="section-title">About Us</h5>
                  <h2 class="mb-4 dis">Welcome to <i class="fa fa-utensils  me-2"></i>Mero Bhoj</h2>
                  <p class="mb-4">Nestled in the heart of Nepal, Mero Bhoj is more than just a restaurant—it's a celebration of flavor, heritage, and hospitality. Our name pays homage to the beloved Nepali dish that brings families together and warms the soul.

                         </p>
                  <p class="mb-4">From the smoky aroma of slow-cooked meats to the vibrant spices that dance on your tongue, every plate we serve tells a story—crafted with love, passed down through generations, and reimagined for today’s food lovers.</p>
                  <div class="row g-4 mb-4 about-extra">
                      <div class="col-sm-6">
                          <div class="d-flex align-items-center px-3 about-experience">
                              <h1 class="flex-shrink-0  mb-0">15</h1>
                              <div class="ps-4">
                                  <p class="mb-0">Years of</p>
                                  <h6 class="text-uppercase mb-0">Experience</h6>
                              </div>
                          </div>
                      </div>
                      <div class="col-sm-6">
                          <div class="d-flex align-items-center px-3 about-popular">
                              <h1 class="flex-shrink-0  mb-0">50</h1>
                              <div class="ps-4">
                                  <p class="mb-0">Popular</p>
                                  <h6 class="text-uppercase mb-0">Master Chefs</h6>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="book-a-table">
                    <div class="anim-layer"></div>
                    <a href="#book-table-section">Book a table</a>
                  </div>
              </div>
          </div>
        </div>
      </section>

             

      

      <section class="testimonials py-5 my-5">
        <div class="container py-5">
          <div class="row" data-aos="fade-right">
            <div class="section-title text-center">
              <h5>Testimonial</h5>
              <h2 class="display-5 fw-bold">Our Customer Says</h2>
            </div>
          </div>
          <div class="row">
            <div class="testimonial-slider-wrapper" data-aos="fade-up">
              <div class="slider-content pt-4 pb-4 mx-4">
                <div>
                  <div class="testi-content">
                    <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Architecto vel ipsa dolore sunt vitae, culpa, dolor reiciendis facilis sed blanditiis repellat incidunt impedit iusto? Odio veniam beatae veritatis adipisci a!</p>
                  </div>
                  <div class="testi-info">
                    <span class="name">Timothy Doe</span>
                    <span class="position">Customer</span>
                  </div>
                </div>
                <div>
                  <div class="testi-content">
                    <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Architecto vel ipsa dolore sunt vitae, culpa, dolor reiciendis facilis sed blanditiis repellat incidunt impedit iusto? Odio veniam beatae veritatis adipisci a!</p>
                  </div>
                  <div class="testi-info">
                    <span class="name">Sarah	Ruiz</span>
                    <span class="position">Director</span>
                  </div>
                </div>
                <div>
                  <div class="testi-content">
                    <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Architecto vel ipsa dolore sunt vitae, culpa, dolor reiciendis facilis sed blanditiis repellat incidunt impedit iusto? Odio veniam beatae veritatis adipisci a!</p>
                  </div>
                  <div class="testi-info">
                    <span class="name">Tracey Lewis</span>
                    <span class="position">Designer</span>
                  </div>
                </div>
                <div>
                  <div class="testi-content">
                    <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Architecto vel ipsa dolore sunt vitae, culpa, dolor reiciendis facilis sed blanditiis repellat incidunt impedit iusto? Odio veniam beatae veritatis adipisci a!</p>
                  </div>
                  <div class="testi-info">
                    <span class="name">Jamie	Erickson</span>
                    <span class="position">Manager</span>
                  </div>
                </div>
              </div>
              <div class="slider-nav-wrapper mx-5">
                <div class="slider-nav">
                  <div class="slider-nav-img active">
                     <img src="<?php echo asset('images/testi-1.jpg'); ?>" alt="">
                  </div>
                  <div class="slider-nav-img">
                     <img src="<?php echo asset('images/testi-2.jpg'); ?>" alt="">
                  </div>
                  <div class="slider-nav-img">
                     <img src="<?php echo asset('images/testi-3.jpg'); ?>" alt="">
                  </div>
                  <div class="slider-nav-img">
                     <img src="<?php echo asset('images/testi-4.jpg'); ?>" alt="">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="our-chefs py-5">
        <div class="container">
          <div class="row" data-aos="fade-right">
            <div class="section-title text-center">
              <h5>Meet Our</h5>
              <h2 class="display-6 fw-bold">Awesome Master Chefs</h2>
            </div>
          </div>

          <div class="row our-chef-slider-wrapper py-5" data-aos="fade-left">
            <div class="col-lg-4">
              <div class="our-chef-slider d-flex flex-column align-items-center gap-4">
                <img width="200px" src="<?php echo asset('images/team-1.png'); ?>" alt="">
                <div class="chef-slider-content">
                  <h5 class="text-center d-block">Ramu Kaka</h5>
                  <p class="text-center mb-0">Head Chef</p>
                  <div class="d-flex justify-content-center">
                    <hr class="w-25 my-2">
                  </div> 
                  <ul class="list-unstyled d-flex justify-content-center">
                    <li class="mx-2">
                      <a href="https://www.facebook.com" target="_blank" class="text-white">
                        <i class="fab fa-facebook-f"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.instagram.com" target="_blank" class="text-white">
                        <i class="fab fa-instagram"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.twitter.com" target="_blank" class="text-white">
                        <i class="fab fa-twitter"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.linkedin.com" target="_blank" class="text-white">
                        <i class="fab fa-linkedin-in"></i>
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="our-chef-slider d-flex flex-column align-items-center gap-4">
                <img width="200px" src="<?php echo asset('images/team-2.png'); ?>" alt="">
                <div class="chef-slider-content">
                  <h5 class="text-center d-block">Rame Ko Nati</h5>
                  <p class="text-center mb-0">Head Chef</p>
                  <div class="d-flex justify-content-center">
                    <hr class="w-25 my-2">
                  </div> 
                  <ul class="list-unstyled d-flex justify-content-center">
                    <li class="mx-2">
                      <a href="https://www.facebook.com" target="_blank" class="text-white">
                        <i class="fab fa-facebook-f"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.instagram.com" target="_blank" class="text-white">
                        <i class="fab fa-instagram"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.twitter.com" target="_blank" class="text-white">
                        <i class="fab fa-twitter"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.linkedin.com" target="_blank" class="text-white">
                        <i class="fab fa-linkedin-in"></i>
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            
            <div class="col-lg-4">
              <div class="our-chef-slider d-flex flex-column align-items-center gap-4">
                <img width="200px" src="<?php echo asset('images/team-4.png'); ?>" alt="">
                <div class="chef-slider-content">
                  <h5 class="text-center d-block">Teresa Doe</h5>
                  <p class="text-center mb-0">Head Chef</p>
                  <div class="d-flex justify-content-center">
                    <hr class="w-25 my-2">
                  </div> 
                  <ul class="list-unstyled d-flex justify-content-center">
                    <li class="mx-2">
                      <a href="https://www.facebook.com" target="_blank" class="text-white">
                        <i class="fab fa-facebook-f"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.instagram.com" target="_blank" class="text-white">
                        <i class="fab fa-instagram"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.twitter.com" target="_blank" class="text-white">
                        <i class="fab fa-twitter"></i>
                      </a>
                    </li>
                    <li class="mx-2">
                      <a href="https://www.linkedin.com" target="_blank" class="text-white">
                        <i class="fab fa-linkedin-in"></i>
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          

        </div>
      </section>
      <section class="reservation" id="book-table-section">
         <img class="d-md-none d-lg-block" src="<?php echo asset('images/find-a-table.png'); ?>" alt="">
        <div class="container-fluid">
          <div class="row">
            <div class="col-lg-6 py-5 reservation-content px-5" data-aos="fade-right">
              <div class="reservation-column py-5 px-3">
                <h2 class="text-center text-white display-6 fw-bold">Make A Reservation</h2>
                <?php if ($currentUser): ?>
                <form id="bookingForm" action="../includes/booking_table.php" method="POST">
                  <div class="row mt-3">
                    <div class="col-12 col-lg-6 mb-3">
                      <div class="input d-flex align-items-center">
                        <i class="fa fa-user py-2 px-3"></i>
                        <input class="form-control bg-transparent border-0 px-3 text-white" type="text" name="name" placeholder="Name" required>
                      </div>
                    </div>
                    <div class="col-12 col-lg-6 mb-3">
                      <div class="input d-flex align-items-center">
                        <i class="fa fa-envelope py-2 px-3"></i>
                        <input class="form-control bg-transparent border-0 px-3 text-white" type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" readonly required>
                      </div>
                    </div>
                  </div>
                  <div class="row mt-3">
                    <div class="col-12 col-lg-6 mb-3">
                      <div class="input d-flex align-items-center">
                        <i class="fa fa-phone py-2 px-3"></i>
                        <input class="form-control bg-transparent border-0 px-3 text-white" type="text" name="phone" placeholder="Phone">
                      </div>
                    </div>
                    <div class="col-12 col-lg-6 mb-3">
                      <div class="input d-flex align-items-center">
                        <i class="fa fa-users py-2 px-3"></i>
                        <input class="form-control bg-transparent border-0 px-3 text-white" type="number" name="people" min="1" max="20" placeholder="Number of People">
                      </div>
                    </div>
                  </div>
                  

                  <div class="row mt-3">
    <div class="col-12 col-lg-4 mb-3">
        <div class="input d-flex align-items-center bg-dark rounded">
            <i class="fa fa-calendar py-2 px-3 text-white-50"></i>
            <input class="form-control bg-transparent border-0 text-white shadow-none" type="date" name="date" required>
        </div>
    </div>
    <div class="col-12 col-lg-4 mb-3">
        <div class="input d-flex align-items-center bg-dark rounded">
            <i class="fa fa-clock py-2 px-3 text-white-50"></i>
            <select class="form-control booking-time-select bg-transparent border-0 text-white shadow-none" name="start_time" required>
                <option value="" class="booking-time-option">Select Start Time</option>
                <?php
                    $startTimes = [];
                    for ($hour = 7; $hour <= 22; $hour++) {
                        foreach ([0, 30] as $minute) {
                            if ($hour === 23 && $minute > 0) continue;
                            $startTimes[] = sprintf('%02d:%02d', $hour, $minute);
                        }
                    }
                    foreach ($startTimes as $time) {
                        echo '<option value="' . htmlspecialchars($time) . '" class="booking-time-option">' . htmlspecialchars(date('g:i A', strtotime($time))) . '</option>';
                    }
                ?>
            </select>
        </div>
    </div>
    <div class="col-12 col-lg-4 mb-3">
        <div class="input d-flex align-items-center bg-dark rounded">
            <i class="fa fa-clock py-2 px-3 text-white-50"></i>
            <select class="form-control booking-time-select bg-transparent border-0 text-white shadow-none" name="end_time" required>
                <option value="" class="booking-time-option">Select End Time</option>
                <?php
                    $endTimes = [];
                    for ($hour = 8; $hour <= 23; $hour++) {
                        foreach ([0, 30] as $minute) {
                            if ($hour === 23 && $minute > 0) continue;
                            $endTimes[] = sprintf('%02d:%02d', $hour, $minute);
                        }
                    }
                    foreach ($endTimes as $time) {
                        echo '<option value="' . htmlspecialchars($time) . '" class="booking-time-option">' . htmlspecialchars(date('g:i A', strtotime($time))) . '</option>';
                    }
                ?>
            </select>
        </div>
    </div>
</div>

                   

                  <div class="row mt-3">
                    <div class="col-12 mb-3">
                      <div class="input d-flex align-items-center">
                        <i class="fa fa-comment py-2 px-3"></i>
                        <textarea class="form-control bg-transparent border-0 px-3 text-white" name="message" placeholder="Message" rows="2"></textarea>
                      </div>
                    </div>
                  </div>
                  <div class="row mt-3">
                    <div class="col-12 mb-3">
                      <div class="input d-flex align-items-center">
                        <i class="fa fa-chair py-2 px-3"></i>
                        <select id="tableSelect" name="table_id" class="form-control bg-transparent border-0 px-3 text-white" required>
                          <option value="" style="color: white; background-color: #212529;">Select a Date, Time, and People first</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-center mt-4 pt-3">
                    <button type="submit" class="btn btn-dark px-5">Book Table</button>
                  </div>
                </form>
                <?php else: ?>
                <div class="alert alert-warning text-center">
                    <p>Please <a href="<?php echo url('/login.php'); ?>">login</a> to book a table.</p>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-lg-6 d-none d-md-block reservation-bg" data-aos="fade-left"></div>
          </div>
        </div>
        
        
      </section>      
      
      <section class="our-services py-5 my-5">
        <div class="container">
          <div class="row">
            <div class="section-title text-center" data-aos="fade-right">
              <h5>Our Service</h5>
              <h2 class="display-6 fw-bold">What We Focus On</h2>
            </div>
          </div>
          <div class="row pt-5">
            <div data-aos="fade-up-right" class="col-sm-12 col-md-6 col-lg-3 d-flex justify-content-center align-items-center flex-column">
              <div class="icon-box">
                <i class="fas fa-utensils fa-2x"></i>
                <span class="number">1</span>
              </div>
              <h4>Reservation</h4>
              <p class="text-center">Reserve your table and savor the warmth of Nepali hospitality—where every meal feels like a homecoming.</p>
            </div>
            <div data-aos="fade-down" class="col-sm-12 col-md-6 col-lg-3 d-flex justify-content-center align-items-center flex-column">
              <div class="icon-box">
                <i class="fas fa-wine-glass-alt fa-2x"></i>
                <span class="number">2</span>
              </div>
              <h4>Private Event</h4>
              <p class="text-center">Host your special moments with us. From family gatherings to cultural celebrations, we craft unforgettable experiences with authentic flavors.</p>
            </div>
            <div data-aos="fade-up" class="col-sm-12 col-md-6 col-lg-3 d-flex justify-content-center align-items-center flex-column">
              <div class="icon-box">
                <i class="fas fa-laptop-house fa-2x"></i>
                <span class="number">3</span>
              </div>
              <h4>Online Order</h4>
              <p class="text-center">Craving Mero Bhoj at home? Order online and enjoy our soulful dishes delivered straight to your doorstep
</p>
            </div>
            <div data-aos="fade-up-left" class="col-sm-12 col-md-6 col-lg-3 d-flex justify-content-center align-items-center flex-column">
              <div class="icon-box">
                <i class="fas fa-motorcycle fa-2x"></i>
                <span class="number">4</span>
              </div>
              <h4>Fast Delivery</h4>
              <p class="text-center">Hot, fresh, and fast—our delivery brings the taste of Nepal to you, wherever you are in Pokhara.</p>
            </div>
          </div>
        </div>
      </section>
                   <!-- Gallery section  -->
      <section class="our-gallery pt-5">
        <div class="container-fluid pt-5">
          <div class="row">
            <div class="section-title text-center" data-aos="fade-right">
              <h5>Our Gallery</h5>
              <h2 class="text-white display-6 fw-bold">Fooday Hot Dishes</h2>
            </div>
          </div>
          <div class="row pt-5">
            <div class="col-md-3 p-0">
              <div data-aos="fade-down-right" class="gallery-image gallery-image-one"></div>
            </div>
            <div class="col-md-6 p-0">
              <div class="row m-0">
                <div class="col-md-8 p-0">
                  <div data-aos="fade-down" class="gallery-image-two"></div>
                </div>
                <div class="col-md-4 p-0">
                  <div data-aos="fade-down" class="gallery-image-three"></div>
                </div>
              </div>
              <div class="row m-0">
                <div class="col-md-4 p-0">
                  <div data-aos="fade-up" class="gallery-image-five"></div>
                </div>
                <div class="col-md-8 p-0">
                  <div data-aos="fade-up" class="gallery-image-six"></div>
                </div>
              </div>
            </div>
            <div class="col-md-3 p-0">
              <div data-aos="fade-up-left" class="gallery-image gallery-image-four"></div>
            </div>
          </div>
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
              <form action ="../includes/suscribe.php" method ="POST"class ="subscribe-form d-flex ps-0 ms-0 ps-lg-5 ms-lg-5" data-aos="fade-left">
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
                </form>
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
    <style>
      .booking-time-select,
      .booking-time-select option,
      .booking-time-select optgroup {
        color: #fff !important;
        background-color: #212529 !important;
      }
      .booking-time-select {
        -webkit-appearance: menulist;
        appearance: menulist;
      }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
      crossorigin="anonymous"
    ></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/script.js'); ?>"></script>
    <script src="./script.js"></script>
    <!-- Include toast notifications JS -->
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    
  </body>
</html>
