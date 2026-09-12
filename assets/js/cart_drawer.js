(function () {
  const drawer = document.getElementById('cartDrawer');
  const backdrop = document.getElementById('cartDrawerBackdrop');
  const body = document.getElementById('cartDrawerBody');
  const footer = document.getElementById('cartDrawerFooter');
  const closeBtn = document.getElementById('cartDrawerClose');
  const clearBtn = document.getElementById('cartDrawerClear');
  const checkoutForm = document.getElementById('checkoutForm');
  const checkoutBtn = document.getElementById('checkoutBtn');
  const checkoutTotal = document.getElementById('checkoutModalTotal');
  const cartEndpoint = 'cart.php';
  let cartItems = Array.isArray(window.initialCartDrawerItems) ? window.initialCartDrawerItems : [];

  function money(value) {
    return 'Rs. ' + (parseFloat(value) || 0).toFixed(2);
  }

  function itemName(item) {
    return item.name || item.menu_name || 'Menu item';
  }

  function itemImage(item) {
    return item.image || '../assets/images/menu/menu-item-1.png';
  }

  function itemTotal(item) {
    return parseFloat(item.total || ((parseFloat(item.price) || 0) * (parseInt(item.quantity, 10) || 1))) || 0;
  }

  function cartCount() {
    return cartItems.length;
  }

  function cartSubtotal() {
    return cartItems.reduce((sum, item) => sum + itemTotal(item), 0);
  }

  function updateDeliveryEstimate() {
    const totalQty = cartItems.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
    const extra = Math.max(0, Math.floor(Math.max(0, totalQty - 1) / 3)) * 5;
    const estimate = document.getElementById('cartDeliveryEstimate');
    if (estimate) estimate.textContent = `${30 + extra} - ${40 + extra} mins`;
  }

  function ensureBadge(link) {
    let badge = link.querySelector('.cart-drawer-count');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'cart-drawer-count';
      link.appendChild(badge);
    }
    return badge;
  }

  function updateBadges() {
    document.querySelectorAll('a[href$="cart.php"], #shoppingbutton, #shoppingbuttonMobile').forEach(link => {
      link.classList.add('cart-drawer-trigger');
      const badge = ensureBadge(link);
      badge.textContent = cartCount();
      badge.classList.toggle('is-empty', cartCount() === 0);
    });

    const headerCount = document.getElementById('cartDrawerHeaderCount');
    if (headerCount) headerCount.textContent = cartCount();
  }

  function renderCart() {
    updateBadges();
    const subtotal = cartSubtotal();
    document.getElementById('cartDrawerSubtotal').textContent = money(subtotal);
    document.getElementById('cartDrawerTotal').textContent = money(subtotal);
    if (checkoutTotal) checkoutTotal.textContent = money(subtotal);
    updateDeliveryEstimate();
    const subtotalLabel = document.getElementById('cartDrawerSubtotalLabel');
    const totalLabel = document.getElementById('cartDrawerTotalLabel');
    if (subtotalLabel) subtotalLabel.textContent = money(subtotal);
    if (totalLabel) totalLabel.textContent = money(subtotal);
    if (checkoutBtn) checkoutBtn.textContent = 'Confirm Purchase';

    if (!cartItems.length) {
      body.innerHTML = `
        <div class="cart-empty-state">
          <i class="fa fa-shopping-bag"></i>
          <h3 class="h5 mb-0">Your cart is empty</h3>
          <p class="mb-2">Add something delicious from the menu.</p>
          <a class="btn btn-outline-primary cart-menu-btn" href="menu.php">Browse Menu</a>
        </div>
      `;
      footer.style.display = 'none';
      return;
    }

    footer.style.display = '';
    const firstItem = cartItems[0] || {};
    const drawerItemImage = document.getElementById('drawerItemImage');
    const drawerItemName = document.getElementById('drawerItemName');
    const drawerItemDescription = document.getElementById('drawerItemDescription');
    const drawerSummaryList = document.getElementById('drawerSummaryList');
    if (drawerItemImage) drawerItemImage.src = itemImage(firstItem);
    if (drawerItemName) drawerItemName.textContent = itemName(firstItem);
    if (drawerItemDescription) drawerItemDescription.textContent = cartItems.length > 1
      ? 'You have ' + cartItems.length + ' items in your cart. Review them before confirming.'
      : 'Review your cart, fill in delivery details, and confirm your order in one step.';
    if (drawerSummaryList) {
      drawerSummaryList.innerHTML = cartItems.map(item => `
        <div class="mkj-summary-item" style="margin-bottom:14px;">
          <img src="${itemImage(item)}" alt="${escapeHtml(itemName(item))}" class="mkj-summary-image" style="width:54px;height:54px;">
          <div class="mkj-summary-copy" style="flex:1;">
            <div class="mkj-summary-row">
              <strong>${escapeHtml(itemName(item))}</strong>
              <strong class="mkj-summary-price">${money(itemTotal(item))}</strong>
            </div>
            <div class="mkj-summary-qty">Qty: ${parseInt(item.quantity, 10) || 1}</div>
          </div>
        </div>
      `).join('');
    }
    body.innerHTML = cartItems.map((item, index) => `
      <div class="cart-drawer-item" data-index="${index}">
        <img src="${itemImage(item)}" alt="${escapeHtml(itemName(item))}">
        <div>
          <h3 class="cart-item-name">${escapeHtml(itemName(item))}</h3>
          <p class="cart-item-price">${money(item.price)} each</p>
          <div class="cart-item-actions">
            <div class="cart-qty-control" aria-label="Quantity">
              <button class="cart-qty-btn" type="button" data-cart-action="decrease" data-index="${index}">-</button>
              <span class="cart-qty-value">${parseInt(item.quantity, 10) || 1}</span>
              <button class="cart-qty-btn" type="button" data-cart-action="increase" data-index="${index}">+</button>
            </div>
            <strong class="cart-item-total">${money(itemTotal(item))}</strong>
          </div>
          <button class="cart-remove-btn mt-2" type="button" data-cart-action="remove" data-index="${index}">
            <i class="fa fa-trash"></i> Remove
          </button>
        </div>
      </div>
    `).join('');
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function openDrawer() {
    drawer.classList.add('open');
    backdrop.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('cart-drawer-locked');
    refreshCart();
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('cart-drawer-locked');
  }

  function postCart(data) {
    return fetch(cartEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data)
    }).then(response => response.json());
  }

  function refreshCart() {
    return postCart({ ajax_action: 'get_cart' }).then(data => {
      if (data.success) {
        cartItems = Array.isArray(data.data) ? data.data : [];
        renderCart();
      }
    }).catch(() => renderCart());
  }

  function updateQuantity(index, quantity) {
    quantity = Math.max(1, parseInt(quantity, 10) || 1);
    return postCart({ ajax_action: 'update_quantity', index, quantity }).then(data => {
      if (data.success) {
        return refreshCart();
      }
      showError(data.message || 'Could not update quantity.', 'Update failed');
    });
  }

  function removeItem(index) {
    return postCart({ ajax_action: 'remove_item', index }).then(data => {
      if (data.success) {
        showSuccess(data.message || 'Item removed from cart.', 'Item deleted');
        return refreshCart();
      }
      showError(data.message || 'Could not remove item.', 'Delete failed');
    });
  }

  function clearCart() {
    if (!cartItems.length) return;

    const doClear = () => {
      postCart({ ajax_action: 'clear_cart' }).then(data => {
        if (data.success) {
          showSuccess(data.message || 'Cart cleared.', 'Cart cleared');
          refreshCart();
        } else {
          showError(data.message || 'Could not clear cart.', 'Clear failed');
        }
      });
    };

    // Shared confirm popup (same UI as the admin-panel delete-confirm modal)
    if (typeof window.openDeleteConfirm === 'function') {
      openDeleteConfirm({
        title: 'Clear Cart?',
        message: 'All items will be removed from your cart. This action cannot be undone.',
        confirmText: 'Clear Cart',
        onConfirm: doClear
      });
      return;
    }

    // Fallback to the native dialog if the shared modal is unavailable
    if (!confirm('Clear all items from your cart?')) return;
    doClear();
  }

  function showSuccess(message, title = 'Success') {
    if (window.ToastNotifications) ToastNotifications.success(message, { title });
  }

  function showError(message, title = 'Something went wrong') {
    if (window.ToastNotifications) ToastNotifications.error(message, { title });
    else alert(message);
  }

  function showWarning(message, title = 'Please check') {
    if (window.ToastNotifications) ToastNotifications.warning(message, { title });
    else alert(message);
  }

  function showInfo(message, title = 'Notice') {
    if (window.ToastNotifications) ToastNotifications.info(message, { title });
  }

  document.addEventListener('click', function (event) {
    const cartLink = event.target.closest('a[href$="cart.php"], #shoppingbutton, #shoppingbuttonMobile');
    if (cartLink) {
      event.preventDefault();
      openDrawer();
      return;
    }

    const cartButton = event.target.closest('[data-cart-action]');
    if (!cartButton) return;

    const index = parseInt(cartButton.dataset.index, 10);
    const item = cartItems[index];
    if (!item) return;

    if (cartButton.dataset.cartAction === 'increase') {
      updateQuantity(index, (parseInt(item.quantity, 10) || 1) + 1);
    }

    if (cartButton.dataset.cartAction === 'decrease') {
      updateQuantity(index, (parseInt(item.quantity, 10) || 1) - 1);
    }

    if (cartButton.dataset.cartAction === 'remove') {
      removeItem(index);
    }
  });

  document.addEventListener('submit', async function (event) {
    const addForm = event.target.closest('form[action*="includes/cart.php?action=add"]');
    if (!addForm) return;

    event.preventDefault();
    const addUrl = new URL(addForm.action, window.location.href);
    addUrl.searchParams.set('ajax', '1');

    try {
      const response = await fetch(addUrl.toString(), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: new FormData(addForm)
      });

      if (response.redirected && response.url.includes('login.php')) {
        showWarning('Please login to add items to your cart.', 'Login required');
        window.location.href = response.url;
        return;
      }

      const data = await response.json();
      if (!data.success) {
        showError(data.message || 'Could not add item to cart.', 'Add failed');
        return;
      }

      cartItems = Array.isArray(data.data) ? data.data : cartItems;
      renderCart();
      showSuccess('Item added to your cart.', 'Added to cart');
      openDrawer();
    } catch (error) {
      showError('Could not add item to cart.', 'Add failed');
    }
  });

  closeBtn?.addEventListener('click', closeDrawer);
  backdrop?.addEventListener('click', closeDrawer);
  clearBtn?.addEventListener('click', clearCart);

  // Close the cart drawer before opening the checkout modal.
  document.getElementById('cartDrawerCheckout')?.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeDrawer();
  });

  checkoutForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!checkoutForm.checkValidity()) {
      checkoutForm.reportValidity();
      return;
    }

    if (!cartItems.length) {
      return;
    }

    const formData = new FormData(checkoutForm);
    formData.append('ajax_action', 'checkout');
    const originalText = checkoutBtn.textContent;
    checkoutBtn.textContent = 'Processing...';
    checkoutBtn.disabled = true;

    fetch(cartEndpoint, { method: 'POST', body: formData })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // eSewa payment: redirect via hidden POST form to esewa/pay.php
          if (data.payment_method === 'eSewa' && data.order_id) {
            const esewaForm = document.createElement('form');
            esewaForm.method = 'POST';
            esewaForm.action = '../esewa/pay.php';
            esewaForm.style.display = 'none';

            function addHidden(name, value) {
              const input = document.createElement('input');
              input.type = 'hidden';
              input.name = name;
              input.value = value || '';
              esewaForm.appendChild(input);
            }

            addHidden('order_id',     data.order_id);
            addHidden('order_number', data.order_number || '');

            document.body.appendChild(esewaForm);
            esewaForm.submit();
            return;
          }

          // Normal order (cash / pay at restaurant)
          cartItems = [];
          renderCart();
          setTimeout(() => {
            window.location.href = data.redirect || '/Merobhoj/client/myorder.php';
          }, 1200);
        } else {
        }
      })
      .catch(() => {})
      .finally(() => {
        checkoutBtn.textContent = originalText;
        checkoutBtn.disabled = false;
      });
  });

  renderCart();
})();
