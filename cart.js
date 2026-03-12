function addToCart(name, price) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const existing = cart.find(function(item) { return item.name === name; });
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ name: name, price: price, qty: 1 });
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();

    const toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = name + ' hozzáadva a kosárhoz!';
        toast.classList.add('show');
        setTimeout(function() { toast.classList.remove('show'); }, 2500);
    }
}

function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const count = cart.reduce(function(sum, item) { return sum + item.qty; }, 0);
    const badge = document.getElementById('cart-count');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', updateCartCount);
