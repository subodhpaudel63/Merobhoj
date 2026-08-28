<?php
$drawerCart = array_values($_SESSION['cart'] ?? []);
$drawerCount = count($drawerCart);
?>
<link rel="stylesheet" href="<?php echo asset('css/order_ui.css'); ?>">
<style>
  .cart-drawer-trigger {
    position: relative;
    display: inline-flex;
    align-items: center;
  }

  .cart-drawer-count {
    position: absolute;
    top: -9px;
    right: 2px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #e53935;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    line-height: 18px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(229, 57, 53, .35);
  }

  .cart-drawer-count.is-empty {
    display: none;
  }

  .cart-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1998;
    background: rgba(18, 18, 18, .46);
    opacity: 0;
    pointer-events: none;
    transition: opacity .28s ease;
  }

  .cart-drawer-backdrop.open {
    opacity: 1;
    pointer-events: auto;
  }

  .cart-drawer {
    position: fixed;
    top: 0;
    right: 0;
    z-index: 1999;
    width: min(430px, 100vw);
    height: 100vh;
    background: #fff;
    box-shadow: -24px 0 60px rgba(0, 0, 0, .2);
    transform: translateX(105%);
    transition: transform .32s cubic-bezier(.22, .61, .36, 1);
    display: flex;
    flex-direction: column;
  }

  .cart-drawer.open {
    transform: translateX(0);
  }

  .cart-drawer-header {
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .cart-drawer-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #1f1f1f;
  }

  .cart-drawer-close,
  .cart-qty-btn,
  .cart-remove-btn {
    border: 0;
    background: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }

  .cart-drawer-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: #333;
    background: #f6f6f6;
  }

  .cart-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 18px;
    background: #fafafa;
  }

  .cart-empty-state {
    min-height: 55vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #777;
    gap: 8px;
  }

  .cart-empty-state i {
    font-size: 3rem;
    color: #ddd;
  }

  .cart-drawer-item {
    display: grid;
    grid-template-columns: 76px 1fr;
    gap: 12px;
    padding: 12px;
    margin-bottom: 12px;
    background: #fff;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
  }

  .cart-drawer-item img {
    width: 76px;
    height: 76px;
    object-fit: cover;
    border-radius: 8px;
  }

  .cart-item-name {
    margin: 0 0 4px;
    font-weight: 800;
    color: #202020;
    font-size: .98rem;
  }

  .cart-item-price {
    margin: 0;
    color: #777;
    font-size: .86rem;
  }

  .cart-item-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
  }

  .cart-qty-control {
    display: inline-flex;
    align-items: center;
    border: 1px solid #e4e4e4;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
  }

  .cart-qty-btn {
    width: 32px;
    height: 32px;
    color: #f05a22;
    font-weight: 900;
  }

  .cart-qty-value {
    width: 34px;
    text-align: center;
    font-weight: 800;
    color: #222;
  }

  .cart-remove-btn {
    color: #d93636;
    font-size: .88rem;
    gap: 5px;
  }

  .cart-item-total {
    font-weight: 900;
    color: #151515;
    white-space: nowrap;
  }

  .cart-drawer-footer {
    padding: 18px 20px 20px;
    border-top: 1px solid #ededed;
    background: #fff;
    box-shadow: 0 -8px 28px rgba(0, 0, 0, .06);
  }

  .cart-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    color: #555;
  }

  .cart-summary-row.total {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #ddd;
    color: #111;
    font-size: 1.1rem;
    font-weight: 900;
  }

  .cart-footer-actions {
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 10px;
    margin-top: 16px;
  }

  .cart-clear-btn,
  .cart-checkout-btn,
  .cart-menu-btn {
    border-radius: 8px;
    min-height: 44px;
    font-weight: 800;
  }

  .cart-checkout-btn {
    background: #f05a22;
    border-color: #f05a22;
    color: #fff;
  }

  .cart-checkout-btn:hover {
    background: #d94d1b;
    color: #fff;
  }

  body.cart-drawer-locked {
    overflow: hidden;
  }

  @media (max-width: 575px) {
    .cart-drawer-header,
    .cart-drawer-footer {
      padding-left: 16px;
      padding-right: 16px;
    }
  }

  .mkj-order-modal .modal-dialog {
    max-width: min(1120px, calc(100vw - 24px));
  }
  .mkj-order-modal-content {
    border: 0;
    border-radius: 26px;
    overflow: hidden;
    background: #fff;
  }
  .mkj-order-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(320px, .95fr);
  }
  .mkj-order-main {
    padding: 22px 24px 24px;
  }
  .mkj-order-summary {
    padding: 28px 24px 24px;
    background: linear-gradient(180deg, #fff, #fcfbf8);
    border-left: 1px solid #eee7df;
  }
  .mkj-order-close-wrap {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 6px;
  }
  .mkj-order-close {
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 50%;
    background: #f5f5f5;
    color: #333;
  }
  .mkj-order-product {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 18px;
    align-items: start;
  }
  .mkj-order-product-image,
  .mkj-summary-image {
    width: 100%;
    object-fit: cover;
    border-radius: 18px;
  }
  .mkj-order-product-image {
    height: 170px;
  }
  .mkj-order-product-title {
    margin: 4px 0 10px;
    font-size: clamp(1.5rem, 2vw, 2rem);
    font-weight: 800;
    color: #111;
  }
  .mkj-order-product-description {
    margin: 0 0 12px;
    color: #5f6472;
    line-height: 1.55;
    font-size: 1rem;
  }
  .mkj-order-price,
  .mkj-summary-price {
    color: #f05a22;
    font-weight: 900;
    font-size: 1.15rem;
  }
  .mkj-order-divider {
    border-color: #ececec;
    opacity: 1;
    margin: 18px 0;
  }
  .mkj-order-label {
    display: block;
    margin-bottom: 10px;
    font-weight: 800;
    color: #222;
  }
  .mkj-stepper {
    display: inline-flex;
    border: 1px solid #eadfd3;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
  }
  .mkj-stepper-btn {
    width: 46px;
    height: 42px;
    border: 0;
    background: #fff7f0;
    color: #f05a22;
    font-size: 1.2rem;
    font-weight: 800;
  }
  .mkj-stepper-input {
    width: 62px;
    border: 0 !important;
    text-align: center;
    font-weight: 800;
    box-shadow: none !important;
  }
  .mkj-form-grid {
    display: grid;
    gap: 14px;
  }
  .mkj-control {
    min-height: 48px;
    border-radius: 10px;
    border-color: #dfe3ea;
    padding-left: 14px;
    padding-right: 14px;
  }
  .mkj-control-textarea {
    min-height: 90px;
  }
  .mkj-summary-title {
    margin: 0 0 18px;
    font-size: 1.35rem;
    font-weight: 800;
    color: #111;
  }
  .mkj-summary-item {
    display: grid;
    grid-template-columns: 54px 1fr;
    gap: 12px;
    align-items: center;
    margin-bottom: 20px;
  }
  .mkj-summary-image {
    height: 54px;
  }
  .mkj-summary-row,
  .mkj-summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }
  .mkj-summary-copy strong,
  .mkj-summary-qty {
    color: #222;
  }
  .mkj-summary-qty {
    margin-top: 5px;
    color: #5e6472;
  }
  .mkj-summary-box {
    border-top: 1px solid #ececec;
    border-bottom: 1px solid #ececec;
    padding: 16px 0;
    margin-bottom: 16px;
  }
  .mkj-summary-line {
    margin: 10px 0;
    color: #30343f;
  }
  .mkj-summary-total {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #ececec;
    font-size: 1.05rem;
    font-weight: 800;
  }
  .mkj-summary-note {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid transparent;
    margin-bottom: 12px;
  }
  .mkj-summary-note i {
    font-size: 1.1rem;
    margin-top: 2px;
  }
  .mkj-note-green {
    background: #f2fbf1;
    border-color: #c7ebc4;
    color: #1f7a2f;
  }
  .mkj-note-amber {
    background: #fff8ed;
    border-color: #f0d9a8;
    color: #6d540b;
  }
  .mkj-benefits {
    list-style: none;
    padding: 8px 0 0;
    margin: 0;
    display: grid;
    gap: 14px;
    color: #4f5665;
  }
  .mkj-benefits li {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .mkj-order-footer {
    display: flex;
    gap: 14px;
    padding: 0 24px 24px;
  }
  .mkj-confirm-btn {
    flex: 1;
    min-height: 54px;
    border: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, #f05a22, #f26a21);
    color: #fff;
    font-weight: 800;
    box-shadow: 0 10px 22px rgba(240, 90, 34, .22);
  }
  .mkj-cancel-btn {
    min-width: 124px;
    min-height: 54px;
    border-radius: 12px;
    background: #f6f7f9;
    color: #596271;
    font-weight: 800;
  }
  @media (max-width: 991px) {
    .mkj-order-layout {
      grid-template-columns: 1fr;
    }
    .mkj-order-summary {
      border-left: 0;
      border-top: 1px solid #eee7df;
    }
  }
  @media (max-width: 575px) {
    .mkj-order-main,
    .mkj-order-summary,
    .mkj-order-footer {
      padding-left: 16px;
      padding-right: 16px;
    }
    .mkj-order-product {
      grid-template-columns: 1fr;
    }
    .mkj-order-product-image {
      height: 210px;
    }
    .mkj-order-footer {
      flex-direction: column;
    }
    .mkj-cancel-btn {
      width: 100%;
    }
  }

  /* eSewa payment logo inside radio button */
  .mkj-radio-esewa-logo {
    height: 22px;
    width: auto;
    object-fit: contain;
    vertical-align: middle;
    filter: grayscale(30%);
    transition: filter .2s;
  }
  .mkj-custom-radio-btn:has(input:checked) .mkj-radio-esewa-logo {
    filter: none;
  }
  /* Hide the generic eSewa icon when logo is used */
  .mkj-custom-radio-btn:has(.mkj-radio-esewa-logo) .mkj-radio-icon {
    display: none;
  }

</style>

<div class="cart-drawer-backdrop" id="cartDrawerBackdrop"></div>
<aside class="cart-drawer" id="cartDrawer" aria-hidden="true" aria-label="Shopping cart">
  <div class="cart-drawer-header">
    <div>
      <h2 class="cart-drawer-title">Your Cart</h2>
      <small class="text-muted"><span id="cartDrawerHeaderCount"><?php echo $drawerCount; ?></span> item(s)</small>
    </div>
    <button class="cart-drawer-close" type="button" id="cartDrawerClose" aria-label="Close cart">
      <i class="fa fa-times"></i>
    </button>
  </div>
  <div class="cart-drawer-body" id="cartDrawerBody"></div>
  <div class="cart-drawer-footer" id="cartDrawerFooter">
    <div class="cart-summary-row">
      <span>Subtotal</span>
      <strong id="cartDrawerSubtotal">Rs. 0.00</strong>
    </div>
    <div class="cart-summary-row">
      <span>Delivery</span>
      <strong>Free</strong>
    </div>
    <div class="cart-summary-row total">
      <span>Total</span>
      <strong id="cartDrawerTotal">Rs. 0.00</strong>
    </div>
    <div class="cart-footer-actions">
      <button class="btn btn-outline-danger cart-clear-btn" type="button" id="cartDrawerClear">Clear</button>
      <button class="btn cart-checkout-btn" type="button" id="cartDrawerCheckout" data-bs-toggle="modal" data-bs-target="#checkoutModal">Checkout</button>
    </div>
  </div>
</aside>

<div class="modal fade mkj-order-modal" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content mkj-order-modal-content shadow">
      <form id="checkoutForm">
        <div class="mkj-order-layout">
          <div class="mkj-order-main">
            <div class="mkj-order-close-wrap">
              <button type="button" class="mkj-order-close" data-bs-dismiss="modal" aria-label="Close">
                <i class="fa fa-xmark"></i>
              </button>
            </div>

            <div class="mkj-order-product">
              <img id="drawerItemImage" src="../assets/images/menu/menu-item-1.png" alt="Order item" class="mkj-order-product-image">
              <div class="mkj-order-product-copy">
                <h2 class="mkj-order-product-title" id="drawerItemName">Your Order</h2>
                <p class="mkj-order-product-description" id="drawerItemDescription">Review your cart, fill in delivery details, and confirm your order in one step.</p>
                <p class="mkj-order-price">रु<span id="checkoutModalTotal">0.00</span></p>
              </div>
            </div>

            <hr class="mkj-order-divider">

            <div class="mkj-form-grid">
              <div class="mkj-field">
                  <label for="drawerName" class="mkj-order-label">Full Name *</label>
                  <div class="input-group">
                      <span class="input-group-text bg-white border-end-0"><i class="fa fa-user text-muted"></i></span>
                      <input type="text" id="drawerName" name="full_name" class="form-control mkj-control border-start-0 ps-0" value="<?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? ''); ?>" required placeholder="Enter your full name">
                  </div>
              </div>
              <div class="mkj-field">
                  <label for="drawerMobile" class="mkj-order-label">Mobile Number *</label>
                  <div class="input-group">
                      <span class="input-group-text bg-white border-end-0"><i class="fa fa-phone text-muted"></i></span>
                      <input type="tel" id="drawerMobile" name="mobile" class="form-control mkj-control border-start-0 ps-0" pattern="[0-9]{10}" maxlength="10" required placeholder="Enter your mobile number">
                  </div>
                  <div class="invalid-feedback">Please enter valid number.</div>
              </div>
              <div class="mkj-field">
                  <label for="drawerEmail" class="mkj-order-label">Email *</label>
                  <div class="input-group">
                      <span class="input-group-text bg-white border-end-0"><i class="fa fa-envelope text-muted"></i></span>
                      <input type="email" id="drawerEmail" name="email" class="form-control mkj-control border-start-0 ps-0" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email" required>
                  </div>
              </div>
              <div class="mkj-field">
                  <label class="mkj-order-label">Order Type *</label>
                  <div class="d-flex gap-2 flex-wrap mkj-radio-group">
                      <label class="mkj-custom-radio-btn">
                          <input type="radio" name="order_type" value="Delivery" class="order-type-radio" checked>
                          <span class="mkj-radio-indicator"></span>
                          <i class="fa fa-motorcycle mkj-radio-icon"></i>
                          <span class="mkj-radio-label">Delivery</span>
                      </label>

                      <label class="mkj-custom-radio-btn">
                          <input type="radio" name="order_type" value="Takeaway" class="order-type-radio">
                          <span class="mkj-radio-indicator"></span>
                          <i class="fa fa-shopping-bag mkj-radio-icon"></i>
                          <span class="mkj-radio-label">Takeaway</span>
                      </label>

                      <label class="mkj-custom-radio-btn">
                          <input type="radio" name="order_type" value="Dine In" class="order-type-radio">
                          <span class="mkj-radio-indicator"></span>
                          <i class="fa fa-chair mkj-radio-icon"></i>
                          <span class="mkj-radio-label">Dine In</span>
                      </label>
                  </div>
              </div>
                <div class="mkj-field" id="drawer_table_number_wrapper" style="display:none;" hidden>
                  <label for="drawerTableNumber" class="mkj-order-label">Table Number (for Dine In)</label>
                  <input type="text" id="drawerTableNumber" name="table_number" class="form-control mkj-control" placeholder="Enter table number">
              </div>
              <div class="mkj-field" id="drawer_address_wrapper">
                  <label for="drawerAddress" class="mkj-order-label">Delivery Address *</label>
                  <div class="input-group">
                      <span class="input-group-text bg-white border-end-0 align-items-start pt-2"><i class="fa fa-location-dot text-muted"></i></span>
                      <textarea id="drawerAddress" name="address" class="form-control mkj-control border-start-0 ps-0 mkj-control-textarea" rows="3" required placeholder="Enter your complete address"></textarea>
                  </div>
                  <div class="invalid-feedback">Please enter valid address</div>
              </div>
              <div class="mkj-field">
                  <label for="drawerNote" class="mkj-order-label">Special Instructions (optional)</label>
                  <div class="input-group">
                      <span class="input-group-text bg-white border-end-0 align-items-start pt-2"><i class="fa fa-pen text-muted"></i></span>
                      <textarea id="drawerNote" name="special_instructions" class="form-control mkj-control border-start-0 ps-0 mkj-control-textarea" rows="2" placeholder="Any special instructions for your order?"></textarea>
                  </div>
              </div>
              <div class="mkj-field">
                  <label class="mkj-order-label">Payment Method *</label>
                  <div class="d-flex gap-2 flex-wrap mkj-radio-group">
                      <label class="mkj-custom-radio-btn">
                          <input type="radio" name="payment_method" value="Cash on Delivery" checked>
                          <span class="mkj-radio-indicator"></span>
                          <i class="fa fa-money-bill mkj-radio-icon"></i>
                          <span class="mkj-radio-label">Cash on Delivery</span>
                      </label>

                      <label class="mkj-custom-radio-btn">
                          <input type="radio" name="payment_method" value="Pay at Restaurant">
                          <span class="mkj-radio-indicator"></span>
                          <i class="fa fa-store mkj-radio-icon"></i>
                          <span class="mkj-radio-label">Pay at Restaurant</span>
                      </label>

                      <label class="mkj-custom-radio-btn">
                 <input type="radio" name="payment_method" value="eSewa">
                 <span class="mkj-radio-indicator"></span>
                <img src="../assets/img/esewa/esewalogo.png"
                     alt="eSewa"
                     class="mkj-radio-esewa-logo">
                  <span class="mkj-radio-label">Pay with eSewa</span>
                    </label>
                  </div>
              </div>
              <div class="mkj-field mt-2">
                  <div class="form-check mkj-custom-checkbox">
                      <input class="form-check-input" type="checkbox" id="drawer_confirm_details" required>
                      <label class="form-check-label text-muted" for="drawer_confirm_details">
                          I confirm that my order details are correct.
                      </label>
                  </div>
              </div>
            </div>
          </div>

          <aside class="mkj-order-summary">
            <h3 class="mkj-summary-title">Order Summary</h3>
            <div id="drawerSummaryList"></div>
            <div class="mkj-summary-box">
              <div class="mkj-summary-line">
                <span>Subtotal</span>
                <strong id="cartDrawerSubtotalLabel">Rs. 0.00</strong>
              </div>
              <div class="mkj-summary-line">
                <span>Delivery Charge</span>
                <strong class="text-success">FREE</strong>
              </div>
              <div class="mkj-summary-line mkj-summary-total">
                <span>Total</span>
                <strong class="mkj-summary-price" id="cartDrawerTotalLabel">Rs. 0.00</strong>
              </div>
            </div>
            <div class="mkj-summary-note mkj-note-green">
              <i class="fa-regular fa-clock"></i>
              <div>
                <strong>Estimated Delivery Time</strong>
                <div>30 - 40 mins</div>
              </div>
            </div>
            <div class="mkj-summary-note mkj-note-amber">
              <i class="fa-regular fa-circle-info"></i>
              <div>
                <strong>Note</strong>
                <div>You will receive order updates on your mobile number.</div>
              </div>
            </div>
            <ul class="mkj-benefits">
              <li><i class="fa-regular fa-shield"></i><span>Safe &amp; Secure Order</span></li>
              <li><i class="fa-regular fa-circle-check"></i><span>Quality Food Guaranteed</span></li>
              <li><i class="fa-solid fa-headset"></i><span>24/7 Customer Support</span></li>
            </ul>
          </aside>
        </div>

        <div class="mkj-order-footer">
          <button type="submit" class="btn mkj-confirm-btn" id="checkoutBtn">Confirm Purchase</button>
          <button type="button" class="btn mkj-cancel-btn" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  window.initialCartDrawerItems = <?php echo json_encode($drawerCart); ?>;
</script>
<script src="<?php echo asset('js/cart_drawer.js'); ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const checkoutForm = document.getElementById('checkoutForm');
    if (!checkoutForm) return;

    const orderTypeRadios = checkoutForm.querySelectorAll('input[name="order_type"]');
    const addressWrapper = document.getElementById('drawer_address_wrapper');
    const addressField = document.getElementById('drawerAddress');
    const tableWrapper = document.getElementById('drawer_table_number_wrapper');
    const tableField = document.getElementById('drawerTableNumber');

    function syncOrderTypeFields() {
      const selected = checkoutForm.querySelector('input[name="order_type"]:checked')?.value || 'Delivery';
      const isDelivery = selected === 'Delivery';
      const isDineIn = selected === 'Dine In';

      if (addressWrapper) {
        addressWrapper.style.display = isDelivery ? '' : 'none';
        addressWrapper.hidden = !isDelivery;
      }
      if (addressField) {
        addressField.required = isDelivery;
        if (!isDelivery) addressField.value = '';
      }

      if (tableWrapper) {
        tableWrapper.style.display = isDineIn ? '' : 'none';
        tableWrapper.hidden = !isDineIn;
      }
      if (tableField) {
        tableField.required = isDineIn;
        if (!isDineIn) tableField.value = '';
      }
    }

    orderTypeRadios.forEach(radio => radio.addEventListener('change', syncOrderTypeFields));
    syncOrderTypeFields();
  });
</script>
