let products = [];
let cart = [];

const productList = document.getElementById('productList');
const productSearch = document.getElementById('productSearch');
const categoryFilter = document.getElementById('categoryFilter');
const stockFilter = document.getElementById('stockFilter');
const productCounter = document.getElementById('productCounter');
const cartBody = document.getElementById('cartBody');
const cartCount = document.getElementById('cartCount');
const cartTotal = document.getElementById('cartTotal');
const confirmSaleBtn = document.getElementById('confirmSaleBtn');
const clearCartBtn = document.getElementById('clearCartBtn');

function productCode(product) {
    return product.code || product.Code || '';
}

function stockAvailable(product) {
    const code = productCode(product);
    const stock = Number(product.amount ?? product.Amount ?? 0);
    const inCart = cart.find(item => item.code === code);
    return stock - Number(inCart?.amount || 0);
}

function stateClass(product) {
    const state = (product.state || product.State || '').toLowerCase();

    if (state === 'discontinued') {
        return 'off';
    }

    if (Number(product.amount ?? product.Amount ?? 0) <= 0 || state === 'soldout') {
        return 'warn';
    }

    return 'ok';
}

function stateText(product) {
    const css = stateClass(product);

    if (css === 'off') {
        return 'descontinuado';
    }

    if (css === 'warn') {
        return 'agotado';
    }

    return 'disponible';
}

function filteredProducts() {
    const text = productSearch.value.trim().toLowerCase();
    const category = categoryFilter.value;
    const stock = stockFilter.value;

    return products.filter(product => {
        const name = (product.name || product.Name || '').toLowerCase();
        const code = productCode(product).toLowerCase();
        const type = product.type || product.Type || '';
        const state = (product.state || product.State || '').toLowerCase();
        const realState = Number(product.amount ?? product.Amount ?? 0) <= 0 && state !== 'discontinued' ? 'soldout' : state;
        const matchesText = !text || name.includes(text) || code.includes(text);
        const matchesCategory = !category || type === category;
        const matchesStock = !stock || realState === stock;

        return matchesText && matchesCategory && matchesStock;
    });
}

function renderCategories() {
    const categories = [...new Set(products.map(product => product.type || product.Type).filter(Boolean))].sort();
    const current = categoryFilter.value;

    categoryFilter.innerHTML = '<option value="">Todas las categorias</option>';
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });
    categoryFilter.value = current;
}

function renderProducts() {
    const list = filteredProducts();
    const available = products.filter(product => stateClass(product) === 'ok').length;

    productCounter.textContent = `${available} disponibles`;

    if (!list.length) {
        productList.innerHTML = '<div class="empty-state">No hay productos para mostrar.</div>';
        return;
    }

    productList.innerHTML = list.map(product => {
        const code = productCode(product);
        const name = product.name || product.Name || 'Producto';
        const description = product.description || product.Description || '';
        const price = Number(product.price ?? product.Price ?? 0);
        const type = product.type || product.Type || 'Sin categoria';
        const state = stateText(product);
        const css = stateClass(product);
        const stock = Math.max(0, stockAvailable(product));
        const disabled = css !== 'ok' || stock <= 0;

        return `
            <article class="product-card ${App.escapeHtml(css)}">
                <img class="product-photo" src="${App.escapeHtml(App.productPhoto(product))}" alt="${App.escapeHtml(name)}">
                <div>
                    <h3>${App.escapeHtml(name)}</h3>
                    <p>${App.escapeHtml(description)}</p>
                    <div class="product-meta">
                        <span class="status-pill ${App.escapeHtml(css)}">${App.escapeHtml(state)}</span>
                        <span class="status-pill">${App.escapeHtml(type)}</span>
                        <span class="status-pill">${stock} en stock</span>
                        <span class="status-pill">${App.money(price)}</span>
                    </div>
                </div>
                <div class="product-actions">
                    <button class="btn btn-primary btn-sm" type="button" data-add="${App.escapeHtml(code)}" ${disabled ? 'disabled' : ''}>
                        <i class="bi bi-plus-lg"></i>
                        Agregar
                    </button>
                </div>
            </article>
        `;
    }).join('');
}

function renderCart() {
    const totalItems = cart.reduce((sum, item) => sum + item.amount, 0);
    const total = cart.reduce((sum, item) => sum + item.price * item.amount, 0);

    cartCount.textContent = totalItems === 1 ? '1 producto' : `${totalItems} productos`;
    cartTotal.textContent = App.money(total);
    confirmSaleBtn.disabled = cart.length === 0;
    clearCartBtn.disabled = cart.length === 0;

    if (!cart.length) {
        cartBody.innerHTML = '<tr><td colspan="3">Agrega productos al ticket.</td></tr>';
        return;
    }

    cartBody.innerHTML = cart.map(item => `
        <tr>
            <td>
                <strong>${App.escapeHtml(item.name)}</strong>
                <br>
                <span class="labels-small">${App.escapeHtml(item.code)}</span>
            </td>
            <td>
                <span class="qty-control">
                    <button type="button" data-cart-action="minus" data-code="${App.escapeHtml(item.code)}" title="Restar">
                        <i class="bi bi-dash"></i>
                    </button>
                    ${item.amount}
                    <button type="button" data-cart-action="plus" data-code="${App.escapeHtml(item.code)}" title="Sumar">
                        <i class="bi bi-plus"></i>
                    </button>
                </span>
            </td>
            <td>
                ${App.money(item.price * item.amount)}
                <button class="table-action ms-1" type="button" data-cart-action="remove" data-code="${App.escapeHtml(item.code)}" title="Quitar">
                    <i class="bi bi-x"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function addToCart(code) {
    const product = products.find(item => productCode(item) === code);

    if (!product || stockAvailable(product) <= 0 || stateClass(product) !== 'ok') {
        App.notify('No hay stock disponible para ese producto.', 'error');
        return;
    }

    const item = cart.find(row => row.code === code);

    if (item) {
        item.amount += 1;
    } else {
        cart.push({
            code,
            name: product.name || product.Name || code,
            price: Number(product.price ?? product.Price ?? 0),
            amount: 1
        });
    }

    renderCart();
    renderProducts();
}

function updateCart(code, action) {
    const item = cart.find(row => row.code === code);

    if (!item) {
        return;
    }

    if (action === 'plus') {
        addToCart(code);
        return;
    }

    if (action === 'minus') {
        item.amount -= 1;
    }

    if (action === 'remove' || item.amount <= 0) {
        cart = cart.filter(row => row.code !== code);
    }

    renderCart();
    renderProducts();
}

async function confirmSale() {
    if (!cart.length) {
        App.notify('El ticket no tiene productos.', 'error');
        return;
    }

    confirmSaleBtn.disabled = true;

    try {
        await App.request('sale', {
            method: 'POST',
            body: {
                Products: cart.map(item => ({
                    Code: item.code,
                    Amount: item.amount
                }))
            }
        });

        App.notify('Venta registrada correctamente.', 'success');
        cart = [];
        renderCart();
        await loadProducts();
    } catch (error) {
        App.notify(error.message, 'error');
    } finally {
        confirmSaleBtn.disabled = cart.length === 0;
    }
}

async function loadProducts() {
    App.setLoading(productList, 'Cargando productos...');

    try {
        products = App.toArray(await App.request('product'));
        renderCategories();
        renderProducts();
    } catch (error) {
        App.setError(productList, error);
    }
}

async function loadCurrentEmployee() {
    try {
        const employee = App.toArray(await App.request('currentemployee'))[0];

        if (!employee) {
            return;
        }

        const fullName = `${employee.name || ''} ${employee.surname || ''}`.trim();
        document.getElementById('cashierName').textContent = fullName || employee.username || 'Encargado de mostrador';
        document.getElementById('currentEmployeePhoto').src = App.photoPath(employee.photo, 'assets/photos/profile.png');
    } catch (error) {
        App.notify('No se pudo cargar el empleado actual.', 'error');
    }
}

function bindEvents() {
    [productSearch, categoryFilter, stockFilter].forEach(control => {
        control.addEventListener('input', renderProducts);
        control.addEventListener('change', renderProducts);
    });

    productList.addEventListener('click', event => {
        const button = event.target.closest('[data-add]');

        if (button) {
            addToCart(button.dataset.add);
        }
    });

    cartBody.addEventListener('click', event => {
        const button = event.target.closest('[data-cart-action]');

        if (button) {
            updateCart(button.dataset.code, button.dataset.cartAction);
        }
    });

    clearCartBtn.addEventListener('click', () => {
        cart = [];
        renderCart();
        renderProducts();
    });

    confirmSaleBtn.addEventListener('click', confirmSale);
}

document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    renderCart();
    loadCurrentEmployee();
    loadProducts();
});

/* 
============================================================================================================
============================================================================================================
Code made by Francisco Emmanuel Luna Hidalgo Last checked 18/05/2026 
============================================================================================================
============================================================================================================
Instituto Tecnológico de Pachuca, Ingeniería en Sistemas Computacionales, Programación Web, proyecto final
============================================================================================================
============================================================================================================
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%%%%%%%%##%%%%%%%%%%@@@@@@@@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%#*++++++++++++++++++++++++++++*#%%%%%%@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#*+++++++++++++++++++++++++++++++++++++++++++*##%%%@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%+++++++++++++++++++++++++++++++++++++++++++++++++++++*#%%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%@@@@@#+++++++++++++++++++++++++++++++++++++++++++++++++++++++%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@%%#+#%@@@@%*++++##+++++++++++++++++++++++++++++++++++++++++++++++%%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@%%*+++++%%@@@@%*+++%@@@%#*+++++++++++++++++++++++++++++++++++++++++#%@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@%#++++++++*%@@@@@%*++%@@@@@@@%#+++++++++++++++++++++++++++++++++++++*%@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@%#++++++++++=#%@@@@@@#+%@@@@@@@@@@%#++++++++++++++++++++++++++++++++++%@@@@@@@@@@
    @@@@@@@@@@@@@@@@@%#++++++++++++++%@@@@@@@%%@@@@@@@@@@@@%%*++++++++++++++++++++++++++++++#%@@@@@@@@@@
    @@@@@@@@@@@@@@@%#++++++++++++++++*%@@@@@@@@@@@@@@@@@@@@@@@%#*++++++++++++++++++++++++++*%@@@@@@@@@@@
    @@@@@@@@@@@@@%%*++++++++++++++++++#%@@@@@@@@@@@@@@@@@@@@@@@@@%#+++++++++++++++++++++++*%@@@@@@@@@@@@
    @@@@@@@@@@@@%#+++++++++++++++++++++%%@@@@@@@@@@@@@@@@@@@@@@@@@@%%*++++++++++++++++++++#%@@@@@@@@@@@@
    @@@@@@@@@@@%*+++++++++++++++++++++++%@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%#+++++++++++++++++#%@@@@@@@@@@@@@
    @@@@@@@@@@%+++++++++++++++++++++++++*%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#++++++++++++++*%@@@@@@@@@@@@@@
    @@@@@@@@%#+++++++++++++++++++++++++++#%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#++++++++++++%@@@@@@@@@@@@@@@
    @@@@@@@%%+++++++++++++++++++++++++++++%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#*++++++++#%@@@@@@@@@@@@@@@
    @@@@@@%%++++++++++++++++++++++++++++++*%@@@@@@@@@@@@@@%%%%%%%%%%%%%%%%@@@@%%##+--*%%@@@@@@@@@@@@@@@@
    @@@@@@%+++++++++++++++++++++++++++++++#%++*#%@@@@%%##*++++++++++++++++*#%%%%=...-=.=%@@@@@@@@@@@@@@@
    @@@@@%*+++++++++++++++++++++++++++++**:-+...-#%#*+++++++++++++++++++++++++##...:*...#@@@@@@@@@@@@@@@
    @@@@%*++++++++++++++++++++++++++++++#-..:+...=%+++++++++++++++++++++++++++*%:..*...:%@@@@@@@@@@@@@@@
    @@@%#+++++++++++++++++++++++++++++++#=...-=..+#++++++++++++++++++++++++++++#%++-..+%@@@@@@@@@@@@@@@@
    @@@%+++++++++++++++++++++++++++++**#%%+:..-**#+++++++++++++++++++++++++++++++*####**#%@@@@@@@@@@@@@@
    @@%#+++++++++++++++++++++++++*#%%@@@%#*#%#%#++++++++++++++++++++++++++++++++++++++++++#%@@@@@@@@@@@@
    @@%++++++++++++++++++++++*#%%@@@@@@%++++++++++++++++++++++++++++++++++++++=+===========*%@@@@@@@@@@@
    @%#+++++++++++++++++++*%%@@@@@@@@%+-=++++++++++++++++++++++++++++++++++++++=:...........:#@@@@@@@@@@
    @%*+++++++++++++++*#%@@@@@@@@@@@%+....-=++++++++++++++++++++=--==++++++++++++=-..........:*%@@@@@@@@
    @%++++++++++++++#%@@@@@@@@@@@@@%+........:=+++++++++++++++++++=.....:-==++++++++=..........#%@@@@@@@
    %#+++++++++++*%@@@@@@@@@@@@@@@%*.............:-===++++++++++++++-.................:-++=:....%@@@@@@@
    %#+++++++++#@@@@@@@@@@@@@@@@@@#:............:-::...::--===+++++++=-....................-*:..-%@@@@@@
    %#+++++=*%@@@@@@@@@@@@@@@@@@@%=..  ......:*=....................................+%@@%+...-:..+@@@@@@
    %#++++++++****#%@@@@@@@@@@@@@#:.     ....+.....:=*#*=:....  .... .....      ..+@@@#.:#@-.....-%@@@@@
    %*+++++++++*#%@@@@@@@@@@@@@@%+.. .   ...::...=@@@@=:-+%*:.                  .*@@@@@+..*@:....:#@@@@@
    %*=+++*##%%@@@@@@@@@@@@@@@@@%=..      ......#@@@@@#....-%+...   .        ...+@@@@@@%..:@#.....*%@@@@
    %%%%%@@@@@@@@@@@@@@@@@@@@@@@%=..      .....#@@@@@@@:.....#*..            ..-@@@@@*:*...*%.....+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%=..         .-@@@@@@%*=.....:#*.           ...%@#=.:=#*...=@:. ..+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%=...        .*@@@#-.:*=......:@+...         .++.:*@@@@-...-@:. ..+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%+...  .     .#%:.:#@@@=...  ..+@:..         .#@@@@@@@%....=@:....+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@*..         :#+#@@@@@@:...  ...%*..        .-%@@@@@@@=.  .+%.....*@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@#-..        :#@@@@@@@#....  ...=#:.       ..=@@@@@@@#.. ..*+....:#@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@+.         .#@@@@@@@=.     ....%-.      ...+@@@@@@%..  ..%:....-%@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%:.......  .*@@@@@@#:.     ....*=.      ...*@@@@@%......-*.....*@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@#.......  .+@@@@@@:..      . .==. .     ..*@@@@+... ...+:....-%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#......  .:@@@@@....   .    .-=.     . ..#@@+........:=.....%%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:..... ..#@%+.....       ..:=.       ..=:..:::::::-=:....==--#%@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@#:.......-+::---===++==+++++-..........:--:::....... ......:*%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%=............................ ...................   ....-%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:......     ..-*+-:....................     .   ....:#%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:.......  ...:+-:=+*#%%%###***++++..............:+%@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:............=#-............:*-.............:*%@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%=............=#*:......:+#-.............-#%@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#=:...........=+****+-............:=#%@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#+-:......................-+#%@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%%#*+=-::::::-=+#%%%%@@@@@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%+**##%%%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
============================================================================================================
============================================================================================================
*/
