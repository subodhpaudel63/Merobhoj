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
    <title>Mero Bhoj - About</title>
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
    <link
      rel="stylesheet"
      type="text/css"
      href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"
    />
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <!-- <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" /> -->
     <link rel="stylesheet" href="../assets/css/style.css" />
    <!-- Include toast styles -->
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
  
    <style>
      /* Add padding to prevent content from being hidden under navbar */
      .main-content {
        padding-top: 100px;
      }
      
      @media (max-width: 991px) {
        .main-content {
          padding-top: 80px;
        }
      }
    </style>
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
                >My Order</a>
    </li>
            
            <?php if (!$currentUser): ?>
              <li class="list-unstyled py-2">
                <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
              </li>
            <?php endif; ?>
            <li class="list-unstyled py-2">
              <a
                class="text-dark text-decoration-none text-uppercase p-4"
                href="./contactus.php"
                >Contact</a
              >
            </li>
          </ul>
        </div>
        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none" id="shoppingbutton" href="./cart.php"><i class="fa fa-shopping-bag me-3"></i></a>
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                
                <li><a class="dropdown-item" href="<?php echo url('./includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div
        class="d-flex justify-content-around py-3 align-items-center d-lg-none"
      >
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
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2">
                  <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
                </li>
              <?php endif; ?>
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

    <div class="search-bar d-none" id="search-container">
      <div class="close-btn" id="search-close-btn">
        <i class="fa fa-close"></i>
      </div>
      <div class="search-bar-wrapper">
        <input type="search" placeholder="Enter any text here..." />
        <div class="search-button">
          <a href="#"><i class="fa fa-search"></i></a>
        </div>
      </div>
    </div>

    <main class="about-page main-content">
      <section class="page-banner d-flex align-items-center">
        <div class="container">
          <div class="row">
            <div class="banner-content">
              <h2 class="text-white display-6 fw-bold text-center" data-aos="fade-right" data-aos-delay="3000">About Us</h2>
              <div class="divider" data-aos="fade-up-right" data-aos-delay="3000">
                <div class="dot mb-2"></div>
              </div>
              <p class="text-white mb-0 text-center" data-aos="fade-left" data-aos-delay="3000">
                We bring to you the unforgetable moment with our delicious
                dishes
              </p>
            </div>
          </div>
        </div>
      </section>
<section class="about-story mt-5 pt-5">
        <div class="container">
          <div class="row" data-aos="fade-right">
            <h2 class="text-center display-6 fw-bold">
              Mero Bhoj Glory Story
            </h2>
            <div
              class="about-line d-flex justify-content-center align-items-center"
            >
              <span></span>
            </div>
          </div>
          <div class="row">
            <div class="story-timeline">
              <div class="story-indicators my-4">
                <div class="row">
                  <div data-aos="fade-right" class="image-box col-lg-2 px-0">
                    <img
                      class="w-100"
                      src="../assets/images/timeline-1.jpg"
                      alt=""
                    />
                    <div class="image-box-inner"><p class="mb-0">2000</p></div>
                  </div>
                  <div data-aos="fade-down" class="image-box col-lg-2 px-0">
                    <img
                      class="w-100"
                      src="../assets/images/timeline-2.jpg"
                      alt=""
                    />
                    <div class="image-box-inner"><p class="mb-0">2002</p></div>

                  </div>
                  <div data-aos="fade-up" class="image-box col-lg-2 px-0">
                    <img
                      class="w-100"
                      src="../assets/images/timeline-3.jpg"
                      alt=""
                    />
                    <div class="image-box-inner"><p class="mb-0">2004</p></div>
                  </div>
                  <div data-aos="fade-down" class="image-box col-lg-2 px-0">
                    <img
                      class="w-100"
                      src="../assets/images/timeline-4.jpg"
                      alt=""
                    />
                    <div class="image-box-inner"><p class="mb-0">2008</p></div>

                  </div>
                  <div data-aos="fade-up" class="image-box col-lg-2 px-0">
                    <img
                      class="w-100"
                      src="../assets/images/timeline-5.jpg"
                      alt=""
                    />
                    <div class="image-box-inner"><p class="mb-0">2012</p></div>

                  </div>
                  <div data-aos="fade-left" class="image-box col-lg-2 px-0">
                    <img
                      class="w-100"
                      src="../assets/images/timeline-6.jpg"
                      alt=""
                    />
                    <div class="image-box-inner"><p class="mb-0">2016</p></div>

                  </div>
                </div>
              </div>
              <div class="story-content py-5 my-4" data-aos="fade-right">
                <div>
                  <p class="text-center"><strong>16.10.2000:</strong> Humble Beginnings</p>
                  <p class="text-center">
                    Mero Bhoj started as a small family-run restaurant in the heart of the city. With just a handful of tables and a passion for authentic Nepali flavors, we served our first customers with traditional recipes passed down through generations.
                  </p>
                  <p class="text-center">
                    Our founder, Subodh Paudel, had a vision to bring the authentic taste of Nepali cuisine to food lovers everywhere, starting with his signature spicy meat curry that would become our namesake dish.
                  </p>
                </div>
                <div>
                  <p class="text-center"><strong>05.03.2002:</strong> First Expansion</p>
                  <p class="text-center">
                    Due to overwhelming customer response, we expanded our seating capacity and introduced our now-famous thukpa and momos to the menu. The restaurant became a local favorite among food enthusiasts.
                  </p>
                  <p class="text-center">
                    We also began sourcing ingredients from local farms to ensure the highest quality and freshness in all our dishes, establishing relationships that continue to this day.
                  </p>
                </div>
                <div>
                  <p class="text-center"><strong>18.07.2004:</strong> Recognition & Awards</p>
                  <p class="text-center">
                    Mero Bhoj received its first culinary award for "Best Ethnic Cuisine" from the City Food Council. This recognition motivated us to further refine our recipes and service quality.
                  </p>
                  <p class="text-center">
                    We introduced our signature spice blends and began training our chefs in traditional cooking techniques to maintain consistency across all our locations.
                  </p>
                </div>
                <div>
                  <p class="text-center"><strong>22.11.2008:</strong> Second Location</p>
                  <p class="text-center">
                    Growing demand led us to open our second restaurant in the suburban area, bringing our authentic flavors to a new community. Both locations maintained the same quality and hospitality.
                  </p>
                  <p class="text-center">
                    We also launched our catering service, providing Nepali cuisine for special events and celebrations, which became increasingly popular among our customers.
                  </p>
                </div>
                <div>
                  <p class="text-center"><strong>12.04.2012:</strong> Culinary Innovation</p>
                  <p class="text-center">
                    We introduced fusion dishes that combined traditional Nepali flavors with international cooking techniques, creating unique offerings that appealed to diverse palates while preserving authenticity.
                  </p>
                  <p class="text-center">
                    Our third location opened in the city's business district, making our cuisine accessible to working professionals and business travelers seeking a taste of home.
                  </p>
                </div>
                <div>
                  <p class="text-center"><strong>30.09.2016:</strong> Regional Chain</p>
                  <p class="text-center">
                    Mero Bhoj had grown to five locations across the region, becoming a recognized name for authentic Nepali cuisine. We established our own spice farm to ensure consistent quality.
                  </p>
                  <p class="text-center">
                    With our growing reputation, we began training programs for aspiring Nepali chefs and started exporting our signature spice blends to international markets.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>


       <section class="testimonials py-2 my-2 mt-lg-4 pt-lg-4 mb-lg-0 pb-lg-0">
        <div class="container my-2 py-2 mt-lg-4 pt-lg-4 mb-lg-0 pb-lg-0">
          <div class="row">
            <div class="col-lg-4 d-none d-lg-block">
              <img src="../assets/images/ab_team_01.png" alt="" data-aos="fade-right">  
              <!-- chef like -->
            </div>
            <div class="col-12 col-lg-8">
              <div class="testimonial-slider-wrapper">
                <div class="slider-content pt-5 pb-4 mx-4" data-aos="fade-down-left">
                  <div>
                    <div class="testi-content">
                      <p>"The authentic flavors of Nepal in every bite! Mero Bhoj brings back memories of my grandmother's cooking. Their dal bhat is exactly how I remember it from my childhood in Bangsing."</p>
                    </div>
                    <div class="d-flex justify-content-center mb-3">
                      <img src="../assets/images/testi-signal.png" alt="">
                    </div>
                    <div class="testi-info">
                      <span class="name">Anil Shah</span>
                      <span class="position">Regular Customer</span>
                    </div>
                  </div>
                  <div>
                    <div class="testi-content">
                      <p>"As someone who has never been to Nepal, Mero Bhoj gave me an incredible introduction to Nepali cuisine. The atmosphere is warm and welcoming, and the food is absolutely delicious!"</p>
                    </div>
                    <div class="d-flex justify-content-center mb-3">
                      <img src="../assets/images/testi-signal.png" alt="">
                    </div>
                    <div class="testi-info">
                      <span class="name">Priya Sharma</span>
                      <span class="position">Food Blogger</span>
                    </div>
                  </div>
                  <div>
                    <div class="testi-content">
                      <p>"I've been coming to Mero Bhoj for over five years now, and they never disappoint. Their momos are the best I've had outside of Nepal, and their service is consistently excellent."</p>
                    </div>
                    <div class="d-flex justify-content-center mb-3">
                      <img src="../assets/images/testi-signal.png" alt="">
                    </div>
                    <div class="testi-info">
                      <span class="name">Sunita Paudel</span>
                      <span class="position">Vloger</span>
                    </div>
                  </div>
                  <div>
                    <div class="testi-content">
                      <p>"The perfect spot for family gatherings! Mero Bhoj offers not just great food but an authentic cultural experience. Their thukpa warmed my heart on a cold winter evening."</p>
                    </div>
                    <div class="d-flex justify-content-center mb-3">
                      <img src="../assets/images/testi-signal.png" alt="">
                    </div>
                    <div class="testi-info">
                      <span class="name">Sunita Rai</span>
                      <span class="position">Event Organizer</span>
                    </div>
                  </div>
                </div>
                <div class="slider-nav-wrapper mx-5" data-aos="fade-up-right">
                  <div class="slider-nav">
                    <div class="slider-nav-img active">
                      <img src="../assets/img/usersprofiles/profilepic.jpg" alt="">
                    </div>
                    <div class="slider-nav-img">
                      <img src="../assets/images/testi-2.jpg" alt="">
                    </div>
                    <div class="slider-nav-img">
                      <img src="../assets/images/testi-3.jpg" alt="">
                    </div>
                    <div class="slider-nav-img">
                      <img src="../assets/images/testi-4.jpg" alt="">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>



      <section class="our-special my-5 py-5">
        <div class="container">
          <div class="row" data-aos="fade-right">
            <h2 class="text-center display-6 fw-bold">
              Our Special
            </h2>
            <div class="about-line d-flex justify-content-center align-items-center">
              <span></span>
            </div>
          </div>
          <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 mb-5 mb-lg-0">
              <div class="special-card" data-aos="fade-right">
                <div class="box-inner">
                  <div class="box-wrapper px-4">
                    <h2 class="pb-2">FRESH MENU</h2>
                    <p class="pb-4">Our kitchen brings you the true taste of Nepal with dishes prepared from locally sourced ingredients and traditional recipes. From the comforting Mero Bhoj to crispy sel roti, every plate is crafted with care to preserve the flavors of home. Freshness isn’t just a promise—it’s our way of life.</p>

                    <div class="book-a-table">
                      <div class="anim-layer"></div>
                      <a href="../404.php">Read More</a>
                    </div>
                  </div>
                  <div class="box-showcase pb-5">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                      <img class="img-fluid" src="../assets/images/feature-box-bg.jpg" alt="">
                      <h2 class="text-center">FRESH MENU</h2>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-5 mb-lg-0">
              <div class="special-card" data-aos="flip-right">
                <div class="box-inner">
                  <div class="box-wrapper px-4">
                    <h2 class="pb-2">VARIOUS DRINK</h2>
                    <p class="pb-4">In Nepali culture, meals are more than nourishment — they are an act of care and community. We prepare our dishes with fresh, seasonal ingredients, honoring the tradition of cooking with the right hand, a symbol of purity and respect. Every plate, from Mero Bhoj to dal bhat, is served with the warmth of home and the values of hospitality that define Nepali life.</p>






                    <div class="book-a-table">
                      <div class="anim-layer"></div>
                      <a href="../404.php">Read More</a>
                    </div>
                  </div>
                  <div class="box-showcase pb-5">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                      <img class="img-fluid" src="../assets/images/feature-box-bg-2.jpg" alt="">
                      <h2 class="text-center">VARIOUS DRINK</h2>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-5 mb-lg-0">
              <div class="special-card" data-aos="fade-left">
                <div class="box-inner">
                  <div class="box-wrapper px-4">
                    <h2 class="pb-2">EXCLUSIVE DISHES</h2>
                    <p class="pb-4">Our exclusive dishes honor Nepali traditions while embracing innovation. Classics like gundruk ko achar and chiura with sukuti reflect the rustic flavors of village life, while fusion creations like momo tacos or sel roti cheesecake show how Nepali culture adapts and thrives. Each dish respects the values of sharing, community, and harmony — the essence of dining in Nepal.</p>

                    <div class="book-a-table">
                      <div class="anim-layer"></div>
                      <a href="../404.php">Read More</a>
                    </div>
                  </div>
                  <div class="box-showcase pb-5">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                      <img class="img-fluid" src="../assets/images/feature-box-bg-3.jpg" alt="">
                      <h2 class="text-center">EXCLUSIVE DISHES</h2>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

     

      <section class="counter my-5">
        <img data-aos="fade-right" class="counter-after" src="<?php echo asset('images/vegetable_01.png'); ?>" alt="">
        <img data-aos="fade-right" class="counter-before" src="<?php echo asset('images/vegetable_02.png'); ?>" alt="">
        <div class="container pt-4 pb-5" data-aos="fade-up-right">
          <div class="row py-5">
            <div class="col-lg-3">
              <div class="counter-box d-flex flex-column align-items-center">
                <div class="counter-info pb-3">
                  <span class="number">103</span>
                  <span class="heading">/dishes</span>
                </div>
                <div class="counter-avatar pt-4">
                  <img src="<?php echo asset('images/counter-1.png'); ?>" alt="">
                </div>
              </div>
            </div>
            <div class="col-lg-3">
              <div class="counter-box d-flex flex-column align-items-center">
                <div class="counter-info pb-3">
                  <span class="number">2389</span>
                  <span class="heading">/customers</span>
                </div>
                <div class="counter-avatar pt-4">
                  <img src="<?php echo asset('images/counter-2.png'); ?>" alt="">
                </div>
              </div>
            </div>
            <div class="col-lg-3">
              <div class="counter-box d-flex flex-column align-items-center">
                <div class="counter-info pb-3">
                  <span class="number">20</span>
                  <span class="heading">/awards</span>
                </div>
                <div class="counter-avatar pt-4">
                  <img src="<?php echo asset('images/counter-3.png'); ?>" alt="">
                </div>
              </div>
            </div>
            <div class="col-lg-3">
              <div class="counter-box d-flex flex-column align-items-center">
                <div class="counter-info pb-3">
                  <span class="number">2589</span>
                  <span class="heading">/working hours</span>
                </div>
                <div class="counter-avatar pt-4">
                  <img src="<?php echo asset('images/counter-4.png'); ?>" alt="">
                </div>
              </div>
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
    <!-- Include toast notifications JS -->
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    
    
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Check for session messages and show toast
        <?php if (isset($_SESSION['msg'])): $m = $_SESSION['msg']; unset($_SESSION['msg']); ?>
          const messageType = '<?php echo $m['type']; ?>';
          const messageText = <?php echo json_encode(htmlspecialchars($m['text'])); ?>;
          
          if (messageType === 'success') {
            ToastNotifications.success(messageText);
          } else {
            ToastNotifications.error(messageText);
          }
        <?php endif; ?>
      });
    </script>
  </body>
</html>
