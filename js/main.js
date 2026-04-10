/* ============================================================
   MarketPlace – main.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    function setFieldError(field, message) {
        field.classList.add('is-invalid');
        field.setCustomValidity(message);
        var fb = field.parentNode.querySelector('.invalid-feedback');
        if (!fb) {
            fb = document.createElement('div');
            fb.className = 'invalid-feedback';
            field.after(fb);
        }
        fb.textContent = message;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.setCustomValidity('');
        var fb = field.parentNode.querySelector('.invalid-feedback');
        if (fb) fb.textContent = '';
    }

    function validateAuthForm(form) {
        let valid = true;
        const email = form.querySelector('input[name="email"]');
        const password = form.querySelector('input[name="password"]');
        const confirm = form.querySelector('input[name="confirm_password"]');

        [email, password, confirm].filter(Boolean).forEach(clearFieldError);

        if (email && email.value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            setFieldError(email, 'Enter a valid email address.');
            valid = false;
        }
        if (password && password.value !== '' && password.value.length < 6) {
            setFieldError(password, 'Password must be at least 6 characters.');
            valid = false;
        }
        if (password && confirm && password.value !== confirm.value) {
            setFieldError(confirm, 'Passwords do not match.');
            valid = false;
        }

        return valid;
    }

    function validateListingForm(form) {
        let valid = true;
        const name = form.querySelector('input[name="name"]');
        const description = form.querySelector('textarea[name="description"]');
        const price = form.querySelector('input[name="price"]');
        const stock = form.querySelector('input[name="stock"]');
        const imageInput = form.querySelector('input[name="images[]"]');

        [name, description, price, stock, imageInput].filter(Boolean).forEach(clearFieldError);

        if (name && name.value.trim().length < 3) {
            setFieldError(name, 'Item name must be at least 3 characters.');
            valid = false;
        }
        if (description && description.value.trim().length < 10) {
            setFieldError(description, 'Description must be at least 10 characters.');
            valid = false;
        }
        if (price && Number(price.value) <= 0) {
            setFieldError(price, 'Price must be greater than 0.');
            valid = false;
        }
        if (stock && Number(stock.value) < 0) {
            setFieldError(stock, 'Stock cannot be negative.');
            valid = false;
        }
        if (imageInput && imageInput.files.length > 4) {
            setFieldError(imageInput, 'You can upload up to 4 images.');
            valid = false;
        }

        return valid;
    }

    function validateMessageForm(form) {
        const message = form.querySelector('textarea[name="message"]');
        if (!message) return true;
        clearFieldError(message);
        if (message.value.trim().length < 2) {
            setFieldError(message, 'Message must be at least 2 characters.');
            return false;
        }
        return true;
    }

    function validateCheckoutForm() {
        return confirm('Place this order now?');
    }

    // --------------------------------------------------------
    // 1. Image preview before upload (sell.php)
    // --------------------------------------------------------
    const imageInput = document.getElementById('product-images');
    const previewContainer = document.getElementById('image-previews');

    if (imageInput && previewContainer) {
        imageInput.addEventListener('change', function () {
            previewContainer.innerHTML = '';
            const files = Array.from(this.files).slice(0, 4);
            files.forEach(function (file) {
                if (!file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    previewContainer.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    // --------------------------------------------------------
    // 2. Star rating click UI (review form on product.php)
    // --------------------------------------------------------
    const starLabels = document.querySelectorAll('.star-rating label');
    starLabels.forEach(function (label) {
        label.addEventListener('click', function () {
            // The CSS handles visual state via :checked ~ label,
            // so no extra JS needed – but we keep the handler for
            // any additional feedback (e.g. aria).
            const input = document.getElementById(this.htmlFor);
            if (input) input.checked = true;
        });
    });

    // --------------------------------------------------------
    // 3. Confirm dialog before delete / destructive actions
    // --------------------------------------------------------
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        if (el.tagName === 'FORM') return;
        el.addEventListener('click', function (e) {
            const msg = this.dataset.confirm || 'Are you sure?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // Also handle forms with data-confirm
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const msg = this.dataset.confirm || 'Are you sure?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // --------------------------------------------------------
    // 4. Cart badge live update helper
    //    Called after add-to-cart AJAX (if used in future).
    //    For now keeps badge in sync on page load.
    // --------------------------------------------------------
    function updateCartBadge(count) {
        const badge = document.getElementById('cart-count');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('d-none');
        } else {
            badge.textContent = '0';
            badge.classList.add('d-none');
        }
    }

    // Expose globally so PHP-rendered pages can call it
    window.updateCartBadge = updateCartBadge;

    // --------------------------------------------------------
    // 5. Auto-dismiss alerts after 4 seconds
    // --------------------------------------------------------
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });

    document.querySelectorAll('[data-validate-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let valid = true;
            const type = this.dataset.validateForm;

            if (type === 'auth') {
                valid = validateAuthForm(this);
            } else if (type === 'listing') {
                valid = validateListingForm(this);
            } else if (type === 'message') {
                valid = validateMessageForm(this);
            } else if (type === 'checkout') {
                valid = validateCheckoutForm();
            }

            if (!valid) {
                e.preventDefault();
                this.reportValidity();
                var firstInvalid = this.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.focus();
            }
        });
    });

    // --------------------------------------------------------
    // 6. Stagger animation on scroll
    // --------------------------------------------------------
    if ('IntersectionObserver' in window) {
        var staggerTargets = document.querySelectorAll(
            '.product-grid .col, .stat-card, .order-card, .dashboard-link, .cart-line'
        );
        var staggerObserver = new IntersectionObserver(function (entries) {
            var visibleCount = 0;
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.style.transitionDelay = (visibleCount * 60) + 'ms';
                    entry.target.classList.add('is-visible');
                    staggerObserver.unobserve(entry.target);
                    visibleCount++;
                }
            });
        }, { threshold: 0.06 });

        staggerTargets.forEach(function (el) {
            el.classList.add('stagger-in');
            staggerObserver.observe(el);
        });
    }

    // --------------------------------------------------------
    // 7. Admin stat counter animation
    // --------------------------------------------------------
    document.querySelectorAll('.stat-card h2').forEach(function (el) {
        var raw = el.textContent.trim();
        var match = raw.match(/^([^\d]*)([\d,.]+)(.*)$/);
        if (!match) return;
        var prefix = match[1], numStr = match[2], suffix = match[3];
        var target = parseFloat(numStr.replace(/,/g, ''));
        if (isNaN(target) || target === 0) return;
        var hasDecimals = numStr.includes('.');
        var duration = 900, start = null;
        function tick(now) {
            if (!start) start = now;
            var t = Math.min((now - start) / duration, 1);
            t = 1 - Math.pow(1 - t, 3);
            var val = target * t;
            el.textContent = prefix + (hasDecimals ? val.toFixed(2) : Math.floor(val).toLocaleString()) + suffix;
            if (t < 1) requestAnimationFrame(tick);
            else el.textContent = raw;
        }
        requestAnimationFrame(tick);
    });

    // --------------------------------------------------------
    // 8. Auto-grow message textarea
    // --------------------------------------------------------
    document.querySelectorAll('textarea[name="message"]').forEach(function (ta) {
        ta.style.overflow = 'hidden';
        ta.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 160) + 'px';
        });
    });

});
