if (window.AOS) {
  AOS.init({
    offset: '140', // 50% viewport height ko offset
  });
}

/* Shared client helpers
   Central place for behaviors that appear across multiple front-end pages. */
function mkjShowToastFromSession() {
  if (!window.ToastNotifications || !window.MKJ_SESSION_MSG) return;
  const msg = window.MKJ_SESSION_MSG;
  if (!msg || !msg.text) return;
  if (msg.type === 'success') {
    ToastNotifications.success(msg.text);
  } else if (msg.type === 'warning') {
    ToastNotifications.warning(msg.text);
  } else {
    ToastNotifications.error(msg.text);
  }
  window.MKJ_SESSION_MSG = null;
}

function mkjSetMinDate(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.setAttribute('min', new Date().toISOString().split('T')[0]);
}

function mkjBindFormBusyState(formSelector) {
  const form = document.querySelector(formSelector);
  if (!form) return;
  form.addEventListener('submit', function () {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (!submitBtn) return;
    submitBtn.disabled = true;
    submitBtn.dataset.originalText = submitBtn.textContent;
    submitBtn.textContent = 'Processing...';
  });
}

function mkjInitBookingForm() {
  const bookingForm = document.getElementById('bookingForm');
  const reservationDate = document.getElementById('reservationDate');
  
  if (!bookingForm || !reservationDate) return;
  
  // Get today's date in YYYY-MM-DD format based on local timezone
  // This is required for the HTML 'date' input field format
  const today = new Date().toISOString().split('T')[0];
  
  // Prevent users from clicking past dates on the calendar picker UI
  // by setting the minimum allowed date to today
  reservationDate.setAttribute('min', today);
  
  bookingForm.addEventListener('submit', function (e) {
    // Extra fallback validation: if the user somehow submits a past date 
    // (e.g., by manually typing it), we block the form submission
    if (reservationDate.value && reservationDate.value < today) {
      e.preventDefault(); // Stop form submission
      reservationDate.value = ''; // Clear the invalid date
      reservationDate.focus();
      
      // Show HTML5 validation error popup to the user
      reservationDate.setCustomValidity('Please select today or a future date.');
      reservationDate.reportValidity();
      return;
    }
    
    // Clear any previous custom errors if the date is valid
    reservationDate.setCustomValidity('');
    
    // Prevent double-clicking by disabling the submit button and changing text
    const submitBtn = bookingForm.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.innerHTML = 'Booking...';
      submitBtn.disabled = true;
    }
  });
}

function mkjOpenCartDrawer() {
  const opener = document.getElementById('cartPageOpenButton');
  if (!opener) return;
  opener.addEventListener('click', function (e) {
    e.preventDefault();
    const drawerToggle = document.querySelector('#cart-drawer-open-button, .shopping-cart');
    if (drawerToggle && drawerToggle.click) {
      drawerToggle.click();
      return;
    }
    const cart = document.querySelector('.shopping-cart');
    if (cart) cart.style.right = '0';
  });
}
document.addEventListener("DOMContentLoaded", function() {
  const loader = document.querySelector('.loader');
  if (loader) setTimeout(() => {
    loader.style.opacity = '0';
    loader.style.display = 'none';
  }, 3000);

  // Toggle Table Number visibility based on Order Type
  document.addEventListener('change', function(e) {
      if (e.target.matches('.order-type-radio')) {
          const formGroup = e.target.closest('form') || document;
          const tableWrapper = formGroup.querySelector('#table_number_wrapper, #drawer_table_number_wrapper');
          if (tableWrapper) {
              if (e.target.value === 'Dine In') {
                  tableWrapper.classList.remove('mkj-hidden');
              } else {
                  tableWrapper.classList.add('mkj-hidden');
              }
          }
      }
  });
});

// Header functionality
document.addEventListener("DOMContentLoaded", function() {
  // Sticky transparent-to-orange header on scroll (home page only)
  const siteHeader = document.querySelector('header');
  if (siteHeader && document.body.classList.contains('home-page')) {
    const handleHeaderScroll = () => {
      const scrollPos = window.pageYOffset || document.documentElement.scrollTop || window.scrollY || 0;
      if (scrollPos > 30) {
        siteHeader.classList.add('scrolled');
      } else {
        siteHeader.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', handleHeaderScroll, { passive: true });
    handleHeaderScroll(); // Check immediately on load
  }

  var getHamburgerIcon = document.getElementById("hamburger");
  var getHamburgerCrossIcon = document.getElementById("hamburger-cross");
  var getMobileMenu = document.getElementById("mobile-menu");

  // Search bar functionality
  const searchBtn = document.getElementById("searchBtn");
  const searchBtnMobile = document.getElementById("searchBtnMobile");
  const closeBtn = document.getElementById("search-close-btn");
  const searchCon = document.getElementById("search-container");

  // Shopping cart functionality
  var shoppingbtn = document.getElementById('shoppingbutton');
  var shoppingbtnMobile = document.getElementById('shoppingbuttonMobile');
  var shoppingCart = document.querySelector('.shopping-cart');
  var cartClose = document.querySelectorAll('.shopping-cart-header > i');

  // Check if elements exist before attaching event listeners
  if (getHamburgerIcon && getHamburgerCrossIcon && getMobileMenu) {
    // Open the mobile menu
    getHamburgerIcon.addEventListener("click", function () {
        getMobileMenu.classList.add("show");
    });

    // Close the mobile menu
    function closeMenu() {
        getMobileMenu.classList.remove("show");
    }

    // Close the mobile menu when the close icon is clicked
    getHamburgerCrossIcon.addEventListener("click", closeMenu);

    // Close the mobile menu if clicking outside of it
    document.addEventListener("click", function(event) {
        // Check if mobile menu and hamburger icon exist
        if (getMobileMenu && getHamburgerIcon) {
            var isClickInsideMenu = getMobileMenu.contains(event.target);
            var isClickOnIcon = getHamburgerIcon.contains(event.target);

            if (!isClickInsideMenu && !isClickOnIcon) {
                closeMenu();
            }
        }
    });
  }

  if (searchBtn) {
    // Show search container when search button is clicked
    searchBtn.addEventListener("click", (event) => {
      event.preventDefault();
      searchCon.classList.remove("d-none");
      requestAnimationFrame(() => {
        searchCon.classList.add("show");
      });
    });
  }

  if (searchBtnMobile) {
    // Show search container when mobile search button is clicked
    searchBtnMobile.addEventListener("click", (event) => {
      event.preventDefault();
      searchCon.classList.remove("d-none");
      requestAnimationFrame(() => {
        searchCon.classList.add("show");
      });
    });
  }

  if (closeBtn) {
    // Hide search container when close button is clicked
    closeBtn.addEventListener("click", () => {
      searchCon.classList.remove("show");
      setTimeout(() => {
        searchCon.classList.add("d-none");
      }, 500); // Delay hiding the search container to allow animation to complete
    });
  }

  if (shoppingbtn) {
    shoppingbtn.addEventListener('click', function(event) {
      event.preventDefault();
      console.log('chl');
      if (shoppingCart) {
        shoppingCart.style.right = "0";
      }
    });
  }

  if (shoppingbtnMobile) {
    shoppingbtnMobile.addEventListener('click', function(event) {
      event.preventDefault();
      console.log('chl');
      if (shoppingCart) {
        shoppingCart.style.right = "0";
      }
    });
  }

  if (cartClose && cartClose.length > 0) {
    cartClose.forEach(function(closeBtn) {
      closeBtn.addEventListener('click', function(event) {
        event.preventDefault();
        if (shoppingCart) {
          shoppingCart.style.right = "-100vw";
        }
      });
    });
  }

  mkjShowToastFromSession();
  mkjOpenCartDrawer();
});

// Header scroll behavior
const header = document.querySelector('header');
const headerClass = document.querySelector('.header');

const checkScroll = () => {
  if (!header || !headerClass) return; //new line
  if (window.scrollY > 10) {
    header.classList.add('scrolled');
    headerClass.classList.remove('my-3');
    headerClass.classList.add('my-2');
    sessionStorage.setItem('scrolled', 'true');
    
  } else {
    header.classList.remove('scrolled');
    headerClass.classList.add('my-3');
    headerClass.classList.remove('my-2');
    sessionStorage.removeItem('scrolled');
  }
};

// Check scroll position on page load
if (sessionStorage.getItem('scrolled') === 'true') {
  header.classList.add('scrolled');
}
window.addEventListener('scroll', checkScroll);  
checkScroll(); // Initial check

// Update copyright year
// document.getElementById('copyrightCurrentYear').textContent = new Date().getFullYear();

const copyright = document.getElementById('copyrightCurrentYear');

if(copyright){
  copyright.textContent = new Date().getFullYear();
}


if (window.jQuery) {
$('.testimonials .slider-content').slick({
  slidesToShow: 1,
  slidesToScroll: 1,
  arrows: false,
  fade: false,
  speed: 300,
  asNavFor: '.testimonials .slider-nav',
  draggable: true,
  swipe: true,
});




// Navigation Slider for Testimonials
$('.testimonials .slider-nav').slick({
  slidesToShow: 3,
  slidesToScroll: 1,
  asNavFor: '.testimonials .slider-content',
  dots: false,
  focusOnSelect: true,
  centerMode: true, // Center the active slide
  centerPadding: '0px',
  draggable: true,
  swipe: true,
  arrows: false, // Disable navigation arrows
  infinite: true,
});

$('.slider-nav').slick({
    slidesToShow: 3,
    slidesToScroll: 1,
    asNavFor: '.slider-content',
    dots: false,
    focusOnSelect: true,
    centerMode: true,
    centerPadding: '0px', // Prevents side images from overlapping the center
    arrows: false,
    infinite: true
});

$('.our-chefs .our-chef-slider-wrapper').slick({
  slidesToShow: 3,
  slidesToScroll: 1,
  arrows: true,
  focusOnSelect: true,
  centerMode: true, // Center the active slide
  centerPadding: '0px',
  fade: false,
  speed: 300,
  draggable: false,
  swipe: false,
  prevArrow: '<button class="slide-arrow prev-arrow"><i class="fas fa-chevron-left"></i></button>',
  nextArrow: '<button class="slide-arrow next-arrow"><i class="fas fa-chevron-right"></i></button>', // <-- comma added here
  responsive: [
    {
      breakpoint: 990,
      settings: {
        slidesToShow: 1,
      }
    }
  ]
});
}

document.addEventListener('DOMContentLoaded', function () {
  mkjBindFormBusyState('form');
  mkjInitBookingForm();
});

/**
 * CONTACT FEEDBACK FORM — contact-form.js
 * ─────────────────────────────────────────
 * 
 *
 * No dependencies beyond vanilla JS. Self-contained IIFE so
 * it never pollutes the global scope.
 */

// Wait for DOM to be fully loaded before initializing the feedback form
document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  /* ── Emoji labels for each star value ── */
  var RATING_LABELS = ['', 'Terrible 😞', 'Poor 😕', 'Okay 😐', 'Good 😊', 'Amazing! 🤩'];


  /* ════════════════════════════════════════════
     1. STAR RATING — emoji hint on selection
  ════════════════════════════════════════════ */
  var srHint = document.getElementById('srHint');

  if (srHint) {
    document.querySelectorAll('.star-rating input').forEach(function (input) {
      input.addEventListener('change', function () {
        srHint.textContent = RATING_LABELS[this.value] || '';
        srHint.classList.add('visible');
      });
    });
  }


  /* ════════════════════════════════════════════
     2. CATEGORY CHIPS — single-select toggle
  ════════════════════════════════════════════ */
  document.querySelectorAll('.feedback-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      // Deactivate all, then activate this one
      document.querySelectorAll('.feedback-chip').forEach(function (c) {
        c.classList.remove('active');
      });
      chip.classList.add('active');
      document.getElementById('selectedCategory').value = chip.dataset.val;
    });
  });


  /* ════════════════════════════════════════════
     3. CHARACTER COUNTER (message textarea)
  ════════════════════════════════════════════ */
  var msgArea   = document.getElementById('ff-msg');
var charCount = document.getElementById('charCount');

var charDiv = null;

if(msgArea){
  charDiv = msgArea.closest('.ff-group').querySelector('.ff-char-count');
}


  msgArea.addEventListener('input', function () {
    var len = msgArea.value.length;
    charCount.textContent = len;

    // Colour cues: normal → amber at 400 → red at 500
    charDiv.className =
      'ff-char-count' +
      (len >= 500 ? ' limit' : len >= 400 ? ' warn' : '');
  });


  /* ════════════════════════════════════════════
     4. VALIDATION HELPERS
  ════════════════════════════════════════════ */

  /** Basic email format check */
  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  /**
   * Apply or clear valid / invalid state on a field + its group.
   * @param {HTMLElement} field    — the input / textarea
   * @param {string}      groupId  — id of the wrapping .ff-group
   * @param {boolean}     ok       — whether the value passes validation
   * @param {boolean}     dirty    — false = just clear state (field untouched)
   */
  function setFieldState(field, groupId, ok, dirty) {
    var grp = document.getElementById(groupId);
    if (!dirty) {
      field.classList.remove('is-valid', 'is-invalid');
      if (grp) grp.classList.remove('has-error');
      return;
    }
    field.classList.toggle('is-valid',   ok);
    field.classList.toggle('is-invalid', !ok);
    if (grp) grp.classList.toggle('has-error', !ok);
  }

  /**
   * Attach blur + live-input validation to a field.
   * Validation only fires on input AFTER the field has been blurred once
   * (avoids showing errors while the user is still typing for the first time).
   *
   * @param {string}   fieldId  — id of the input
   * @param {string}   groupId  — id of the .ff-group wrapper
   * @param {Function} checkFn  — returns true if value is valid
   */
  function attachValidation(fieldId, groupId, checkFn) {
    var field   = document.getElementById(fieldId);
    var touched = false;

    field.addEventListener('blur', function () {
      touched = true;
      setFieldState(field, groupId, checkFn(field.value.trim()), true);
    });

    field.addEventListener('input', function () {
      if (touched) {
        setFieldState(
          field, groupId,
          checkFn(field.value.trim()),
          field.value.trim() !== ''
        );
      }
    });
  }

  /* Wire up the three required fields */
  attachValidation('ff-name',  'grp-name',  function (v) { return v.length >= 2; });
  attachValidation('ff-email', 'grp-email', isValidEmail);
  attachValidation('ff-msg',   'grp-msg',   function (v) { return v.length >= 5; });

  /* Phone is optional — just show the green tick if something is entered */
  var phoneField = document.getElementById('ff-phone');
  phoneField.addEventListener('blur', function () {
    if (phoneField.value.trim()) {
      phoneField.classList.add('is-valid');
    } else {
      phoneField.classList.remove('is-valid', 'is-invalid');
    }
  });


  /* ════════════════════════════════════════════
     5. SUBMIT
  ════════════════════════════════════════════ */
  var btn      = document.getElementById('sendFeedbackBtn');
  var nameF    = document.getElementById('ff-name');
  var emailF   = document.getElementById('ff-email');
  var msgF     = document.getElementById('ff-msg');
  var formNote = document.getElementById('formNote');

  btn.addEventListener('click', function () {

    /* Validate all required fields */
    var nameOk  = nameF.value.trim().length >= 2;
    var emailOk = isValidEmail(emailF.value.trim());
    var msgOk   = msgF.value.trim().length >= 5;

    setFieldState(nameF,  'grp-name',  nameOk,  true);
    setFieldState(emailF, 'grp-email', emailOk, true);
    setFieldState(msgF,   'grp-msg',   msgOk,   true);

    if (!nameOk || !emailOk || !msgOk) {
      formNote.textContent = '⚠ Please fix the highlighted fields.';
      /* Smooth-scroll to the first invalid field */
      var firstBad = document.querySelector('.ff-field.is-invalid');
      if (firstBad) firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    formNote.textContent = '';

    /* ── Loading state ── */
    btn.classList.add('loading');

    // Prepare form data for submission
    const formData = new FormData();
    formData.append('name', nameF.value.trim());
    formData.append('email', emailF.value.trim());
    formData.append('rating', document.querySelector('input[name="rating"]:checked')?.value || 0);
    formData.append('comments', msgF.value.trim());
    // Use the selected category or empty string if none selected
    const selectedCategoryElement = document.getElementById('selectedCategory');
    formData.append('category', selectedCategoryElement ? selectedCategoryElement.value : '');

    // Make actual fetch request to backend
    fetch('../includes/feedback_form.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      // Check if response is OK before parsing JSON
      if (!response.ok) {
        throw new Error('Server responded with status ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      btn.classList.remove('loading');
      if (data.status === 'success') {
        btn.classList.add('sent');
        btn.querySelector('.btn-label').innerHTML = '✓ Sent!';
        setTimeout(function () {
          showSuccess();
        }, 600);
      } else {
        btn.classList.remove('sent');
        formNote.textContent = '⚠ ' + (data.message || 'An error occurred');
        btn.classList.add('is-invalid');
        setTimeout(() => {
          btn.classList.remove('is-invalid');
        }, 3000);
      }
    })
    .catch(error => {
      btn.classList.remove('loading');
      console.error('Error:', error);
      formNote.textContent = '⚠ Network error: ' + error.message + '. Please check your internet connection or try again.';
      btn.classList.add('is-invalid');
      setTimeout(() => {
        btn.classList.remove('is-invalid');
      }, 5000);
    });
  });


  /* ════════════════════════════════════════════
     6. SUCCESS TRANSITION
  ════════════════════════════════════════════ */
  function showSuccess() {
    var wrap    = document.getElementById('feedbackFormWrap');
    var overlay = document.getElementById('formSuccessOverlay');

    /* Fade + slide the form out */
    wrap.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    wrap.style.opacity    = '0';
    wrap.style.transform  = 'translateY(-10px)';

    setTimeout(function () {
      wrap.style.display = 'none';
      /* Show success overlay — CSS transitions handle the icon + text entrance */
      overlay.classList.add('visible');
    }, 350);
  }


  /* ════════════════════════════════════════════
     7. RESET ("Send Another" button)
  ════════════════════════════════════════════ */
  document.getElementById('sendAnotherBtn').addEventListener('click', function () {
    var overlay = document.getElementById('formSuccessOverlay');

    /* Fade the overlay out first */
    overlay.style.transition = 'opacity 0.3s ease';
    overlay.style.opacity    = '0';

    setTimeout(function () {
      overlay.classList.remove('visible');
      overlay.style.opacity = '';

      /* ── Clear all field values and states ── */
      [nameF, emailF, msgF, phoneField].forEach(function (f) {
        f.value = '';
        f.classList.remove('is-valid', 'is-invalid');
      });

      charCount.textContent = '0';
      charDiv.className = 'ff-char-count';

      ['grp-name', 'grp-email', 'grp-msg'].forEach(function (id) {
        var g = document.getElementById(id);
        if (g) g.classList.remove('has-error');
      });

      /* Clear chips */
      document.querySelectorAll('.feedback-chip').forEach(function (c) {
        c.classList.remove('active');
      });
      document.getElementById('selectedCategory').value = '';

      /* Clear stars */
      document.querySelectorAll('input[name="rating"]').forEach(function (r) {
        r.checked = false;
      });
      srHint.textContent = '';
      srHint.classList.remove('visible');

      formNote.textContent = '';

      /* ── Fade + slide the form back in ── */
      var wrap = document.getElementById('feedbackFormWrap');
      wrap.style.display   = '';
      wrap.style.opacity   = '0';
      wrap.style.transform = 'translateY(12px)';

      /* Double rAF ensures the display:'' is painted before we start the transition */
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          wrap.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
          wrap.style.opacity    = '1';
          wrap.style.transform  = 'translateY(0)';
        });
      });

      /* Reset the button back to its default state */
      btn.classList.remove('sent', 'loading');
      btn.querySelector('.btn-label').innerHTML =
        'Send Feedback &nbsp;<i class="fa fa-paper-plane"></i>';

    }, 300);
  });

}); // End of DOMContentLoaded for feedback form

// toast


    /**
     * Function to generate the toast HTML and trigger animations
     */
    function showToast(status) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'custom-toast';
        const duration = 5000; // Matches the CSS progress bar animation

        if (status === 'success') {
            toast.classList.add('toast-success');
            toast.innerHTML = `
                <i class="fa fa-check-circle toast-icon"></i>
                <div class="toast-content">
                    <strong>Subscription Active!</strong>
                    <span>You've been added to our food tribe.</span>
                </div>
                <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
            `;
        } else {
            toast.classList.add('toast-error');
            toast.innerHTML = `
                <i class="fa fa-circle-exclamation toast-icon"></i>
                <div class="toast-content">
                    <strong>Oops!</strong>
                    <span>Something went wrong. Please try again.</span>
                </div>
                <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
            `;
        }

        container.appendChild(toast);

        // Remove the toast after the duration
        setTimeout(() => {
            toast.classList.add('toast-fade-out');
            setTimeout(() => {
                toast.remove();
            }, 800); // Wait for the slide-out animation to finish
        }, duration - 800);
    }

    /**
     * Check the URL for status parameters when the page loads
     */
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        
        if (status) {
            showToast(status);
            // Clean the URL so the toast doesn't reappear if the user refreshes
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    };

/* ═══════════════════════════════════════════════════════════════════
   PAGE SCRIPTS — consolidated from the former per-page script files
   (home.js, home-faq.js, home-toast.js, menu-page.js, login.js,
   register.js, formvalidation.js).
   Blocks that were duplicated across those files now exist only once:
   - showToast()/URL ?status= handler (was ×2 in script.js AND ×2 in
     home-toast.js — the copy above is the single remaining one)
   - bootstrap .toast auto-show (was in home-toast/menu-page/login/register)
   - Google Sign-In GIS block (was identical in login.js and register.js)
   - floating food emojis (was identical in login.js and register.js)
   Everything is guarded so this file stays safe on every page.
   ═══════════════════════════════════════════════════════════════════ */

/* ── Home page (index.php): table-booking form (was assets/js/home.js).
   Its "Booking..." submit-loading state duplicates mkjInitBookingForm /
   mkjBindFormBusyState above, so it is not repeated here. ── */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bookingForm');
    if (!form) return;

    const dateInput = form.querySelector('input[name="date"]');
    const startTimeInput = form.querySelector('input[name="start_time"]');
    const endTimeInput = form.querySelector('input[name="end_time"]');
    const peopleInput = form.querySelector('input[name="people"]');
    const tableSelect = document.getElementById('tableSelect');

    function updateTables() {
        const date = dateInput.value;
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        const people = peopleInput.value;

        if (date && startTime && endTime && people > 0) {
            tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Loading available tables...</option>';

            const formData = new FormData();
            formData.append('date', date);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);
            formData.append('people', people);

            fetch('./includes/get_available_tables.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Select a Table</option>';
                if (data.success && data.tables.length > 0) {
                    data.tables.forEach(table => {
                        const option = document.createElement('option');
                        option.value = table.id;
                        option.textContent = `${table.name} - ${table.capacity} Seats`;
                        option.style.color = 'white';
                        option.style.backgroundColor = '#212529';
                        tableSelect.appendChild(option);
                    });
                } else if (data.success && data.tables.length === 0) {
                    tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">No tables available for this time and group size</option>';
                } else {
                    tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Error loading tables</option>';
                }
            })
            .catch(err => {
                tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Error loading tables</option>';
            });
        } else {
            tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Select a Date, Start & End Time, and People first</option>';
        }
    }

    dateInput.addEventListener('change', updateTables);
    startTimeInput.addEventListener('change', updateTables);
    endTimeInput.addEventListener('change', updateTables);
    peopleInput.addEventListener('change', updateTables);
    peopleInput.addEventListener('keyup', updateTables);

    form.addEventListener('submit', function(e) {
        // Phone validation: +977 or 977 followed by 96/97/98 + 8 digits
        const phone = form.querySelector('input[name="phone"]').value.replace(/[\s\-]/g, '');
        if (phone && !/^(\+?977)?9[6-8]\d{8}$/.test(phone)) {
            e.preventDefault();
            alert('Please enter a valid Nepal phone number (e.g., 98XXXXXXXX or +977-98XXXXXXXX).');
            return;
        }

        // Opening hours validation
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        if (startTime) {
            const startHour = parseInt(startTime.split(':')[0], 10);
            if (startHour < 7 || startHour >= 23) {
                e.preventDefault();
                alert('We are open from 7:00 AM to 11:00 PM. Please choose a valid start time.');
                return;
            }
        }
        if (endTime) {
            const endHour = parseInt(endTime.split(':')[0], 10);
            const endMin = parseInt(endTime.split(':')[1], 10);
            if (endHour < 7 || endHour > 23 || (endHour === 23 && endMin > 0)) {
                e.preventDefault();
                alert('We are open from 7:00 AM to 11:00 PM. Please choose a valid end time.');
                return;
            }
        }
        if (startTime && endTime) {
            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
                return;
            }
        }

        // Max people validation
        const people = parseInt(peopleInput.value, 10);
        if (people > 8) {
            e.preventDefault();
            alert('The maximum capacity for a single table is 8 people.');
            return;
        }
    });
});

/* ── Home page (index.php): FAQ accordion toggle (was assets/js/home-faq.js).
   Global on purpose — index.php calls it from inline onclick handlers. ── */
function mkjToggle(btn) {
    const item = btn.parentElement;
    const container = document.getElementById('mkjFaqContainer');
    const isActive = item.classList.contains('active');

    // Force reflow for smoother performance
    container.querySelectorAll('.mkj-faq-item').forEach(el => {
        if(el !== item) el.classList.remove('active');
    });

    item.classList.toggle('active');
}

/* ── Shared: bootstrap toast auto-show (was duplicated in home-toast.js,
   menu-page.js, login.js and register.js) ── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.toast').forEach(function (t) {
    if (window.bootstrap && window.bootstrap.Toast) {
      new bootstrap.Toast(t, { delay: 5000 }).show();
    }
  });
});

/* ── Menu page (menu.php) (was assets/js/menu-page.js). The bootstrap-toast
   block at the top of this file replaced its duplicate, and the guest modal is
   now guarded so the code cannot throw on pages without the modal. ── */
document.addEventListener('DOMContentLoaded', () => {

    /* ── Login Required modal for guests ── */
    const loginModalEl = document.getElementById('loginRequiredModal');
    const loginModal = loginModalEl ? new bootstrap.Modal(loginModalEl) : null;
    document.querySelectorAll('.guest-block').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (loginModal) loginModal.show();
        });
    });

    /* ── Live search ── */
    const searchInput = document.getElementById('menuSearchInput');
    const searchClear = document.getElementById('menuSearchClear');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            searchClear.classList.toggle('visible', q.length > 0);
            document.querySelectorAll('#menuTabContent .col').forEach(col => {
                const name = col.querySelector('.card-title')?.textContent.toLowerCase() || '';
                const desc = col.querySelector('.card-text')?.textContent.toLowerCase() || '';
                col.classList.toggle('search-hidden', q.length > 0 && !name.includes(q) && !desc.includes(q));
            });
            document.querySelectorAll('#menuTabContent .tab-pane').forEach(p => {
                if (q.length > 0) {
                    p.style.cssText = 'display:block;opacity:1;transform:none;pointer-events:auto;';
                } else {
                    p.style.cssText = '';
                }
            });
            const menuTabForOpacity = document.getElementById('menuTab');
            if (menuTabForOpacity) menuTabForOpacity.style.opacity = q.length > 0 ? '0.5' : '1';
        });
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
    }

    /* ── Tab switch — re-trigger card entrance ── */
    const menuTabEl = document.getElementById('menuTab');
    if (menuTabEl) {
        menuTabEl.addEventListener('shown.bs.tab', e => {
            const pane = document.querySelector(e.target.getAttribute('data-bs-target'));
            if (!pane) return;
            pane.querySelectorAll('.col').forEach(col => {
                col.style.animation = 'none';
                col.offsetHeight;
                col.style.animation = '';
            });
        });
    }

    /* ── Wishlist heart ── */
    const wishlist = JSON.parse(localStorage.getItem('mkj_wishlist') || '[]');
    function refreshHearts() {
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            btn.classList.toggle('liked', wishlist.includes(btn.dataset.id));
        });
    }
    refreshHearts();
    document.addEventListener('click', e => {
        const btn = e.target.closest('.wishlist-btn');
        if (!btn) return;
        const id = btn.dataset.id;
        const idx = wishlist.indexOf(id);
        idx === -1 ? wishlist.push(id) : wishlist.splice(idx, 1);
        localStorage.setItem('mkj_wishlist', JSON.stringify(wishlist));
        btn.classList.toggle('liked', wishlist.includes(id));
        btn.style.transform = 'scale(1.5)';
        setTimeout(() => btn.style.transform = '', 250);
    });

    /* ── Buy Now modal ── */
    const buyModal      = document.getElementById('buyModal');
    const modalPrice    = document.getElementById('modal-price');
    const modalTotal    = document.getElementById('modal-total-price');
    const inputMenuId   = document.getElementById('input-menu-id');
    const inputMenuName = document.getElementById('input-menu-name');
    const inputPrice    = document.getElementById('input-price');
    const inputTotal    = document.getElementById('input-total-price');
    const quantityInput = document.getElementById('quantity');

    function recalc() {
        const price = parseFloat(modalPrice.textContent) || 0;
        const qty   = Math.max(1, parseInt(quantityInput.value) || 1);
        quantityInput.value = qty;
        const total = (price * qty).toFixed(2);
        modalTotal.textContent = total;
        inputPrice.value = price.toFixed(2);
        inputTotal.value = total;
    }

    document.getElementById('qty-minus')?.addEventListener('click', () => {
        quantityInput.value = Math.max(1, (parseInt(quantityInput.value) || 1) - 1);
        recalc();
    });
    document.getElementById('qty-plus')?.addEventListener('click', () => {
        quantityInput.value = (parseInt(quantityInput.value) || 1) + 1;
        recalc();
    });
    quantityInput?.addEventListener('input', recalc);

    buyModal?.addEventListener('show.bs.modal', e => {
        const btn = e.relatedTarget;
        inputMenuId.value              = btn.getAttribute('data-id');
        inputMenuName.value            = btn.getAttribute('data-name');
        document.getElementById('modal-name').textContent        = btn.getAttribute('data-name');
        document.getElementById('modal-description').textContent = btn.getAttribute('data-description');
        document.getElementById('modal-image').src               = btn.getAttribute('data-image');
        modalPrice.textContent = parseFloat(btn.getAttribute('data-price')).toFixed(2);
        quantityInput.value    = 1;
        recalc();
    });
});

/* ── Auth pages: Google Sign-In (was duplicated verbatim in login.js and
   register.js). The #gisConfig JSON island only exists on login.php and
   register.php, so GIS_CONFIG is guarded. ── */
var GIS_CONFIG = (function () {
  var el = document.getElementById('gisConfig');
  if (!el) return null;
  try { return JSON.parse(el.textContent); } catch (e) { return null; }
})();

function handleGoogleCredentialResponse(response) {
    console.log('Google credential response received');
    fetch('includes/google_auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id_token: response.credential })
    })
    .then(function(res) {
        console.log('Auth response status:', res.status);
        return res.json();
    })
    .then(function(data) {
        console.log('Auth response data:', data);
        if (data.success) {
            window.location.href = data.redirect || '/Merobhoj/client/index.php';
        } else {
            alert(data.message || 'Google sign-in failed');
        }
    })
    .catch(function(err) {
        console.error('Network error during Google sign-in:', err);
        alert('Network error during Google sign-in');
    });
}

function handleGoogleFallback() {
    alert('Google Sign-In is not configured yet.\n\nPlease set GOOGLE_CLIENT_ID in config/bootstrap.php');
}

function renderGoogleButton() {
    console.log('renderGoogleButton called, google object:', typeof google);

    if (!GIS_CONFIG.useFallback) {
    if (typeof google === 'undefined' || !google.accounts) {
        console.log('Google accounts not ready, retrying in 200ms...');
        setTimeout(renderGoogleButton, 200);
        return;
    }
    try {
        console.log('Initializing Google Sign-In with client_id:', GIS_CONFIG.clientId);
        google.accounts.id.initialize({
            client_id: GIS_CONFIG.clientId,
            callback: handleGoogleCredentialResponse,
            auto_select: false,
            cancel_on_tap_outside: false,
        });
        console.log('Rendering Google button...');
        google.accounts.id.renderButton(
            document.getElementById('g_id_onload'),
            { theme: 'outline', size: 'large', width: '100%' }
        );
        console.log('Google button rendered successfully');
    } catch (e) {
        console.error('GIS render failed:', e);
    }
    } else {
    console.log('Using fallback button (GOOGLE_USE_FALLBACK is true)');
    }
}

// Try to render immediately and also on DOMContentLoaded
if (GIS_CONFIG) {
  console.log('GIS page loaded, GOOGLE_USE_FALLBACK:', GIS_CONFIG.useFallback);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderGoogleButton);
  } else {
    renderGoogleButton();
  }
}

/* ── Login page (login.php) (was assets/js/login.js) ── */

/* Eye toggle — global, used by the inline onclick handlers in login.php */
function togglePw(id, iconId) {
  const inp = document.getElementById(id);
  const ico = document.getElementById(iconId);
  if (!inp || !ico) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.classList.replace('bi-eye-slash', 'bi-eye');
  } else {
    inp.type = 'password';
    ico.classList.replace('bi-eye', 'bi-eye-slash');
  }
}

(function () {
  var loginButton = document.getElementById('loginButton');
  var loginForm   = document.getElementById('loginForm');
  if (!loginButton && !loginForm) return;

  /* ── RIPPLE ── */
  if (loginButton) {
    loginButton.addEventListener('click', function (e) {
      const btn = this;
      const r = document.createElement('span');
      const rect = btn.getBoundingClientRect();
      const sz = Math.max(rect.width, rect.height);
      r.className = 'btn-ripple';
      r.style.cssText = `width:${sz}px;height:${sz}px;left:${e.clientX - rect.left - sz / 2}px;top:${e.clientY - rect.top - sz / 2}px`;
      btn.appendChild(r);
      r.addEventListener('animationend', () => r.remove());
    });
  }

  if (!loginForm) return;

  /* ── FORM VALIDATION WITH SHAKE ── */
  loginForm.addEventListener('submit', function (e) {
    let ok = true;

    const emailInp = document.getElementById('useremail');
    const passInp  = document.getElementById('password');

    [
      { inp: emailInp, group: 'emailGroup', msg: emailInp.nextElementSibling?.nextElementSibling },
      { inp: passInp,  group: 'passGroup',  msg: passInp.closest('.input-group')?.nextElementSibling }
    ].forEach(function (field) {
      const grp = document.getElementById(field.group);
      if (!field.inp.checkValidity()) {
        ok = false;
        grp.classList.add('is-invalid-group');
        const msgEl = grp.parentElement.querySelector('.invalid-msg');
        if (msgEl) { msgEl.style.display = 'block'; }
        // re-trigger animation
        field.inp.style.animation = 'none';
        void field.inp.offsetWidth;
        field.inp.style.animation = '';
      } else {
        grp.classList.remove('is-invalid-group');
        const msgEl = grp.parentElement.querySelector('.invalid-msg');
        if (msgEl) { msgEl.style.display = 'none'; }
      }
    });

    if (!ok) e.preventDefault();
  });

  /* Clear error on input */
  ['useremail', 'password'].forEach(function (id) {
    var inp = document.getElementById(id);
    if (!inp) return;
    inp.addEventListener('input', function () {
      const grp = this.closest('.input-group');
      if (grp) {
        grp.classList.remove('is-invalid-group');
        const msg = grp.parentElement.querySelector('.invalid-msg');
        if (msg) msg.style.display = 'none';
      }
    });
  });
})();

/* ── Register page (register.php) (was assets/js/register.js).
   Guarded on register-specific elements so it only runs there. ── */
(function () {
  const pwInp     = document.getElementById('password');
  const cpInp     = document.getElementById('confirmPassword');
  const strFill   = document.getElementById('strFill');
  const strLbl    = document.getElementById('strLabel');
  const matchPill = document.getElementById('matchPill');
  const regForm   = document.getElementById('regForm');
  if (!pwInp || !cpInp || !strFill || !strLbl || !matchPill || !regForm) return;

  /* ── EYE TOGGLES ── */
  function mkEye(btnId, inputId) {
    var btn = document.getElementById(btnId);
    var inp = document.getElementById(inputId);
    if (!btn || !inp) return;
    btn.addEventListener('click', function () {
      const ico = this.querySelector('i');
      if (inp.type === 'password') {
        inp.type = 'text';
        ico.classList.replace('bi-eye-slash','bi-eye');
      } else {
        inp.type = 'password';
        ico.classList.replace('bi-eye','bi-eye-slash');
      }
    });
  }
  mkEye('eyePass', 'password');
  mkEye('eyeConf', 'confirmPassword');

  /* ── RIPPLE ── */
  var regBtn = document.getElementById('regBtn');
  if (regBtn) {
    regBtn.addEventListener('click', function (e) {
      const r = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const sz = Math.max(rect.width, rect.height);
      r.className = 'btn-ripple';
      r.style.cssText = `width:${sz}px;height:${sz}px;left:${e.clientX-rect.left-sz/2}px;top:${e.clientY-rect.top-sz/2}px`;
      this.appendChild(r);
      r.addEventListener('animationend', () => r.remove());
    });
  }

  /* ── STRENGTH METER ── */
  const LEVELS = [
    { pct:'0%',   bg:'transparent', txt:'',              col:'#aaa' },
    { pct:'20%',  bg:'#e74c3c',     txt:'Too weak',      col:'#e74c3c' },
    { pct:'40%',  bg:'#e67e22',     txt:'Weak',          col:'#e67e22' },
    { pct:'62%',  bg:'#f1c40f',     txt:'Fair',          col:'#c9a200' },
    { pct:'82%',  bg:'#27ae60',     txt:'Strong',        col:'#27ae60' },
    { pct:'100%', bg:'#16a085',     txt:'✦ Very strong', col:'#16a085' },
  ];

  function scorePass(pw) {
    let s = 0;
    if (pw.length >= 3)               s++;
    if (pw.length >= 8)               s++;
    if (/[A-Z]/.test(pw))             s++;
    if (/[0-9]/.test(pw))             s++;
    if (/[^A-Za-z0-9]/.test(pw))     s++;
    return Math.min(s, 5);
  }

  pwInp.addEventListener('input', function () {
    const lv = LEVELS[scorePass(this.value)];
    strFill.style.width = lv.pct;
    strFill.style.backgroundColor = lv.bg;
    strLbl.textContent = lv.txt;
    strLbl.style.color = lv.col;
    if (cpInp.value) updateMatch();
    document.getElementById('passGroup').classList.remove('is-invalid-group');
    document.getElementById('passGroup').parentElement.querySelector('.invalid-msg') &&
      (document.getElementById('passGroup').parentElement.querySelector('.invalid-msg').style.display = 'none');
  });

  function updateMatch() {
    const pw = pwInp.value, cp = cpInp.value;
    if (!cp) { matchPill.classList.remove('visible','ok','fail'); return; }
    matchPill.classList.add('visible');
    if (pw === cp) {
      matchPill.className = 'match-pill visible ok';
      matchPill.innerHTML = '<i class="bi bi-check-circle-fill"></i> Passwords match';
    } else {
      matchPill.className = 'match-pill visible fail';
      matchPill.innerHTML = '<i class="bi bi-x-circle-fill"></i> Passwords don\'t match';
    }
  }

  cpInp.addEventListener('input', function () {
    updateMatch();
    document.getElementById('confGroup').classList.remove('is-invalid-group');
  });

  /* ── VALIDATION ── */
  function shakeGroup(groupId, msgText) {
    const grp = document.getElementById(groupId);
    grp.classList.add('is-invalid-group');
    const inp = grp.querySelector('input');
    inp.style.animation = 'none';
    void inp.offsetWidth;
    inp.style.animation = '';
    const msg = grp.parentElement.querySelector('.invalid-msg');
    if (msg) { msg.textContent = msgText; msg.style.display = 'block'; }
    inp.focus();
  }

  document.getElementById('email').addEventListener('input', function() {
    document.getElementById('emailGroup').classList.remove('is-invalid-group');
    const m = document.getElementById('emailGroup').parentElement.querySelector('.invalid-msg');
    if (m) m.style.display = 'none';
  });

  regForm.addEventListener('submit', function (e) {
    let ok = true;
    const em = document.getElementById('email');
    const pw = pwInp, cp = cpInp;
    const hasSym = /[^A-Za-z0-9]/.test(pw.value);
    const hasNum = /[0-9]/.test(pw.value);

    if (!em.value.trim() || !em.checkValidity()) {
      shakeGroup('emailGroup','Please enter a valid email address.');
      ok = false;
    }
    if (pw.value.length < 3 || !hasSym || !hasNum) {
      shakeGroup('passGroup','Min 3 chars, 1 symbol & 1 number required.');
      ok = false;
    }
    if (pw.value !== cp.value) {
      shakeGroup('confGroup','Passwords must match.');
      ok = false;
    }
    if (!ok) e.preventDefault();
  });
})();

/* ── Shared: floating food emojis (was duplicated in login.js and
   register.js). Only runs where the #food-floaters container exists. ── */
(function () {
  const container = document.getElementById('food-floaters');
  if (!container) return;
  const foods = ['🍖','🍗','🌶️','🥘','🍲','🧅','🧄','🥩','🫕','🍛','🌿','🥣'];
  const drifts = ['drift1','drift2','drift3'];

  foods.forEach(function (emoji, i) {
    const el = document.createElement('div');
    el.className = 'food-float';
    el.textContent = emoji;
    const left     = 4 + (i * 8.1) % 92;
    const duration = 11 + (i * 2.3) % 9;   /* 11–20s */
    const delay    = (i * 1.7) % 12;         /* stagger */
    const drift    = drifts[i % 3];
    el.style.cssText = [
      `left:${left}%`,
      `bottom:-80px`,
      `animation:${drift} ${duration}s ${delay}s ease-in infinite`,
      `font-size:${1.2 + (i % 3) * .35}rem`,
    ].join(';');
    container.appendChild(el);
  });
})();

/* ── Bootstrap custom validation (was assets/js/formvalidation.js) ── */
(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

