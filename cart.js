const CART_STORAGE_KEY = 'cart';

let authState = {
    loggedIn: false,
    userName: ''
};

let authInitPromise = null;

function sanitizeCart(items) {
    if (!Array.isArray(items)) return [];

    return items
        .filter(function (item) {
            return item
                && typeof item.name === 'string'
                && item.name.length > 0
                && item.name.length <= 200
                && typeof item.price === 'number'
                && isFinite(item.price)
                && item.price >= 0
                && typeof item.qty === 'number'
                && isFinite(item.qty)
                && item.qty >= 1;
        })
        .map(function (item) {
            return {
                name: item.name,
                price: Math.round(item.price * 100) / 100,
                qty: Math.max(1, Math.floor(item.qty))
            };
        });
}

function readCart() {
    try {
        return sanitizeCart(JSON.parse(localStorage.getItem(CART_STORAGE_KEY) || '[]'));
    } catch (e) {
        return [];
    }
}

function writeCart(cart) {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(sanitizeCart(cart)));
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function fetchAuthState() {
    try {
        const response = await fetch('auth_status.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            return { loggedIn: false, userName: '' };
        }

        const data = await response.json();
        return {
            loggedIn: Boolean(data && data.loggedIn),
            userName: data && typeof data.userName === 'string' ? data.userName : ''
        };
    } catch (e) {
        return { loggedIn: false, userName: '' };
    }
}

function renderAuthLinks() {
    const nav = document.querySelector('nav');
    if (!nav) return;

    let container = document.getElementById('auth-links');
    if (!container) {
        container = document.createElement('span');
        container.id = 'auth-links';
        const cartAnchor = nav.querySelector('a[href="cart.html"]');
        if (cartAnchor) {
            nav.insertBefore(container, cartAnchor);
        } else {
            nav.appendChild(container);
        }
    }

    if (authState.loggedIn) {
        container.innerHTML =
            '<a href="logout.php">KILÉPÉS (' + escapeHtml(authState.userName || 'felhasználó') + ')</a>';
    } else {
        container.innerHTML =
            '<a href="login.php">BELÉPÉS</a>' +
            '<a href="signup.php">REGISZTRÁCIÓ</a>';
    }
}

async function initAuthState() {
    if (!authInitPromise) {
        authInitPromise = fetchAuthState().then(function (state) {
            authState = state;
            renderAuthLinks();
            return state;
        });
    }

    return authInitPromise;
}

function mergeCarts(localCart, serverCart) {
    const merged = new Map();

    function upsert(item) {
        const key = item.name + '|' + item.price.toFixed(2);
        const existing = merged.get(key);
        if (existing) {
            existing.qty += item.qty;
        } else {
            merged.set(key, {
                name: item.name,
                price: item.price,
                qty: item.qty
            });
        }
    }

    localCart.forEach(upsert);
    serverCart.forEach(upsert);

    return Array.from(merged.values());
}

async function loadCartFromServer() {
    if (!authState.loggedIn) return null;

    try {
        const response = await fetch('cart_api.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) return null;

        const data = await response.json();
        if (!data || !Array.isArray(data.cart)) return [];
        return sanitizeCart(data.cart);
    } catch (e) {
        return null;
    }
}

async function saveCartToServer(cart) {
    if (!authState.loggedIn) return;

    try {
        await fetch('cart_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ cart: sanitizeCart(cart) })
        });
    } catch (e) {
        // Ignore sync errors to keep UX uninterrupted.
    }
}

async function syncCartWithServer() {
    await initAuthState();
    await saveCartToServer(readCart());
}

async function initializeCartSync() {
    await initAuthState();

    if (!authState.loggedIn) {
        updateCartCount();
        return;
    }

    const localCart = readCart();
    const serverCart = await loadCartFromServer();

    if (serverCart === null) {
        updateCartCount();
        return;
    }

    const merged = mergeCarts(localCart, serverCart);
    writeCart(merged);
    updateCartCount();
    await saveCartToServer(merged);
}

function addToCart(name, price) {
    let cart = readCart();
    const existing = cart.find(function (item) { return item.name === name; });
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ name: name, price: price, qty: 1 });
    }

    writeCart(cart);
    updateCartCount();
    syncCartWithServer();

    const toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = name + ' hozzáadva a kosárhoz!';
        toast.classList.add('show');
        setTimeout(function () { toast.classList.remove('show'); }, 2500);
    }
}

function updateCartCount() {
    const cart = readCart();
    const count = cart.reduce(function (sum, item) { return sum + item.qty; }, 0);
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

window.syncCartWithServer = syncCartWithServer;

document.addEventListener('DOMContentLoaded', function () {
    updateCartCount();
    initializeCartSync();
});
