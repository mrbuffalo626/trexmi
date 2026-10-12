<?php
declare(strict_types=1);

session_start();

$products = [
    'ballpen' => ['name' => 'Ballpen', 'price' => 12.00, 'icon' => 'bi-pen'],
    'pencil' => ['name' => 'Pencil', 'price' => 10.00, 'icon' => 'bi-pencil'],
    'notebook' => ['name' => 'Notebook', 'price' => 35.00, 'icon' => 'bi-journal-bookmark'],
    'yellow-pad' => ['name' => 'Yellow Pad', 'price' => 45.00, 'icon' => 'bi-journal-text'],
    'eraser' => ['name' => 'Eraser', 'price' => 8.00, 'icon' => 'bi-eraser'],
    'ruler' => ['name' => 'Ruler', 'price' => 15.00, 'icon' => 'bi-rulers'],
];

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$cart = &$_SESSION['cart'];
$paymentError = isset($_SESSION['payment_error']) ? (string) $_SESSION['payment_error'] : '';
$paymentInput = isset($_SESSION['payment_input']) ? (string) $_SESSION['payment_input'] : '';
$receipt = $_SESSION['receipt'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        exit('Invalid request token. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $productId = $_POST['product_id'] ?? '';

    if ($action === 'add' && is_string($productId) && isset($products[$productId])) {
        $cart[$productId] = ($cart[$productId] ?? 0) + 1;
        unset($_SESSION['receipt'], $_SESSION['payment_input'], $_SESSION['payment_error']);
        $paymentError = '';
    } elseif (($action === 'increase' || $action === 'decrease' || $action === 'remove')
        && is_string($productId) && isset($products[$productId], $cart[$productId])) {
        if ($action === 'increase') {
            $cart[$productId]++;
        } elseif ($action === 'decrease') {
            if ($cart[$productId] <= 1) {
                unset($cart[$productId]);
            } else {
                $cart[$productId]--;
            }
        } else {
            unset($cart[$productId]);
        }
        unset($_SESSION['receipt'], $_SESSION['payment_input'], $_SESSION['payment_error']);
        $paymentError = '';
    } elseif ($action === 'pay') {
        $paymentRaw = $_POST['amount_paid'] ?? '';
        $paymentInput = is_string($paymentRaw) ? trim($paymentRaw) : '';
        $_SESSION['payment_input'] = $paymentInput;

        if (!$cart) {
            $paymentError = 'Add at least one item to your cart before confirming payment.';
        } elseif ($paymentInput === '') {
            $paymentError = 'Please enter the amount paid.';
        } elseif (!is_numeric($paymentInput) || !is_finite((float) $paymentInput)) {
            $paymentError = 'Enter a valid numeric payment amount.';
        } elseif ((float) $paymentInput < 0) {
            $paymentError = 'Payment cannot be negative.';
        } else {
            $total = 0.0;
            $receiptItems = [];
            foreach ($cart as $id => $quantity) {
                $subtotal = $products[$id]['price'] * $quantity;
                $total += $subtotal;
                $receiptItems[] = [
                    'name' => $products[$id]['name'],
                    'quantity' => $quantity,
                    'price' => $products[$id]['price'],
                    'subtotal' => $subtotal,
                ];
            }
            $amountPaid = (float) $paymentInput;
            if ($amountPaid < $total) {
                $paymentError = 'Payment is less than the total amount due.';
            } else {
                $_SESSION['receipt'] = [
                    'reference' => 'SS-' . strtoupper(bin2hex(random_bytes(4))),
                    'date' => date('F j, Y g:i A'),
                    'items' => $receiptItems,
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'change' => $amountPaid - $total,
                ];
                unset($_SESSION['payment_input'], $_SESSION['payment_error']);
                $paymentError = '';
            }
        }
        $_SESSION['payment_error'] = $paymentError;
    } elseif ($action === 'new_transaction') {
        $cart = [];
        unset($_SESSION['receipt'], $_SESSION['payment_input'], $_SESSION['payment_error']);
        $paymentError = '';
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$receipt = $_SESSION['receipt'] ?? null;
$paymentInput = isset($_SESSION['payment_input']) ? (string) $_SESSION['payment_input'] : '';
$total = 0.0;
foreach ($cart as $id => $quantity) {
    if (isset($products[$id])) {
        $total += $products[$id]['price'] * $quantity;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Supplies Ordering System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #17243b;
            --muted: #758198;
            --brand: #315bd6;
            --brand-dark: #2349bd;
            --surface: #ffffff;
            --page: #f5f7fb;
            --line: #e9edf4;
        }
        body { background: var(--page); color: var(--ink); font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif; }
        .navbar { min-height: 76px; }
        .navbar-brand { color: var(--ink); letter-spacing: -.025em; }
        .brand-icon { width: 2.65rem; height: 2.65rem; background: linear-gradient(145deg, #4775f2, #294dc0) !important; box-shadow: 0 .45rem 1rem #315bd633; }
        .hero { position: relative; overflow: hidden; color: #fff; background: linear-gradient(115deg, #223e96 0%, #315bd6 58%, #547cf0 100%); border-radius: 1.25rem; box-shadow: 0 1rem 2.5rem #294dc022; }
        .hero::after { position: absolute; content: ""; width: 15rem; height: 15rem; right: -3rem; top: -8rem; border: 1px solid #ffffff24; border-radius: 50%; box-shadow: 0 0 0 2rem #ffffff0c, 0 0 0 4rem #ffffff08; }
        .hero-content { position: relative; z-index: 1; }
        .hero-kicker { color: #dce6ff; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .hero h1 { letter-spacing: -.04em; }
        .hero-copy { color: #e1e9ff; }
        .section-heading { letter-spacing: -.025em; }
        .section-icon { color: var(--brand); }
        .product-card, .panel-card { background: var(--surface); border: 1px solid var(--line) !important; border-radius: 1rem; box-shadow: 0 .25rem 1.1rem #1a31520a !important; }
        .product-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
        .product-card:hover { transform: translateY(-3px); border-color: #cbd7ff !important; box-shadow: 0 .75rem 1.8rem #1a315214 !important; }
        .product-icon-wrap { width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; color: var(--brand); background: #eef2ff; border-radius: .9rem; display: inline-flex; align-items: center; justify-content: center; }
        .product-icon { font-size: 1.55rem; }
        .product-price { color: var(--ink); font-weight: 700; }
        .btn-primary { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: var(--brand-dark); --bs-btn-hover-border-color: var(--brand-dark); --bs-btn-active-bg: var(--brand-dark); --bs-btn-active-border-color: var(--brand-dark); }
        .btn { border-radius: .65rem; font-weight: 600; }
        .btn-success { --bs-btn-bg: #16835d; --bs-btn-border-color: #16835d; --bs-btn-hover-bg: #106d4d; --bs-btn-hover-border-color: #106d4d; }
        .section-title { font-size: 1.05rem; letter-spacing: -.02em; }
        .panel-card .card-header { border-radius: 1rem 1rem 0 0; }
        .cart-table { --bs-table-bg: transparent; --bs-table-border-color: var(--line); }
        .cart-table thead th, .receipt-table thead th { color: var(--muted); font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .cart-table td { padding-top: .9rem; padding-bottom: .9rem; }
        .quantity-control { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem; border: 1px solid var(--line); border-radius: .6rem; background: #fff; }
        .quantity-control .btn { width: 1.65rem; height: 1.65rem; line-height: 1; border: 0; border-radius: .4rem; color: #53627a; }
        .quantity-value { min-width: 1.1rem; font-size: .875rem; font-weight: 700; }
        .remove-button { color: #a4adbc !important; transition: color .15s ease; }
        .remove-button:hover { color: #dc3545 !important; }
        .summary-box { padding: 1rem; border-radius: .85rem; background: #f6f8fc; }
        .summary-total { color: var(--brand); }
        .payment-input { border-radius: 0 .65rem .65rem 0 !important; }
        .input-group-text { border-color: #dfe5ef; background: #f7f8fb; color: var(--muted); }
        .form-control { border-color: #dfe5ef; }
        .form-control:focus { border-color: #9db2ff; box-shadow: 0 0 0 .2rem #315bd61c; }
        .empty-cart { border: 1px dashed #d8dfeb; border-radius: .85rem; background: #fafbfe; }
        .empty-cart-icon { color: #aab6ca; }
        .receipt { border: 1px dashed #c8d1df; background: #fff; }
        .receipt-check { color: #16835d; }
        .quantity-form { display: inline; }
        @media (min-width: 992px) {
            .order-column { position: sticky; top: 1.25rem; align-self: flex-start; }
        }
        @media (max-width: 575.98px) {
            .hero { border-radius: 1rem; }
            .hero h1 { font-size: 1.65rem; }
            .cart-table { min-width: 390px; }
        }
    </style>
</head>
<body>
<nav class="navbar bg-white border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#">
            <span class="brand-icon rounded-3 bg-primary text-white d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-backpack-fill"></i>
            </span>
            Student Supplies
        </a>
        <span class="text-secondary small"><i class="bi bi-shop me-1"></i> Ordering System</span>
    </div>
</nav>

<main class="container pb-5">
    <header class="hero p-4 p-md-5 mb-4">
        <div class="hero-content">
            <div class="hero-kicker mb-2"><i class="bi bi-stars me-1"></i> Student essentials</div>
            <h1 class="h2 fw-bold mb-2">School supplies, ready to order</h1>
            <p class="hero-copy mb-0">Pick what you need, review your cart, and check out with cash.</p>
        </div>
    </header>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="products-heading">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="text-uppercase small fw-bold text-secondary mb-1">Browse essentials</div>
                    <h2 id="products-heading" class="section-heading section-title fw-bold mb-0"><i class="bi bi-grid me-2 section-icon"></i>Product List</h2>
                </div>
                <span class="badge rounded-pill text-bg-white border text-secondary px-3 py-2">6 items</span>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 g-3">
                <?php foreach ($products as $id => $product): ?>
                    <div class="col">
                        <article class="card product-card h-100">
                            <div class="card-body d-flex align-items-center gap-3 p-3 p-xl-4">
                                <span class="product-icon-wrap"><i class="bi <?= h($product['icon']) ?> product-icon" aria-hidden="true"></i></span>
                                <div class="flex-grow-1">
                                    <h3 class="h6 fw-bold mb-1"><?= h($product['name']) ?></h3>
                                    <span class="product-price"><?= peso($product['price']) ?></span>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= h($id) ?>">
                                    <button class="btn btn-primary btn-sm px-3" type="submit" aria-label="Add <?= h($product['name']) ?> to cart">
                                        <i class="bi bi-plus-lg me-sm-1"></i><span class="d-none d-sm-inline">Add</span>
                                    </button>
                                </form>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <div class="col-lg-5 order-column">
            <section class="card panel-card mb-4" aria-labelledby="cart-heading">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 id="cart-heading" class="section-title fw-bold mb-0"><i class="bi bi-cart3 me-2 section-icon"></i>Shopping Cart</h2>
                </div>
                <div class="card-body px-4">
                    <?php if (!$cart): ?>
                        <div class="empty-cart text-center text-secondary py-4">
                            <i class="bi bi-cart-x empty-cart-icon fs-2 d-block mb-2"></i>Your cart is empty.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table cart-table align-middle mb-0">
                                <thead class="small text-secondary">
                                <tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cart as $id => $quantity): ?>
                                    <?php if (!isset($products[$id])) { continue; } ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= h($products[$id]['name']) ?></div>
                                            <small class="text-secondary"><?= peso($products[$id]['price']) ?> each</small>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <form method="post" class="quantity-form">
                                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="product_id" value="<?= h($id) ?>">
                                                <span class="quantity-control">
                                                    <button class="btn btn-light btn-sm p-0" name="action" value="decrease" type="submit" aria-label="Decrease <?= h($products[$id]['name']) ?> quantity">−</button>
                                                    <span class="quantity-value"><?= (int) $quantity ?></span>
                                                    <button class="btn btn-light btn-sm p-0" name="action" value="increase" type="submit" aria-label="Increase <?= h($products[$id]['name']) ?> quantity">+</button>
                                                </span>
                                            </form>
                                        </td>
                                        <td class="text-end fw-semibold"><?= peso($products[$id]['price'] * $quantity) ?></td>
                                        <td class="text-end">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="product_id" value="<?= h($id) ?>">
                                                <button class="btn btn-link remove-button p-0" name="action" value="remove" type="submit" aria-label="Remove <?= h($products[$id]['name']) ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card panel-card mb-4" aria-labelledby="summary-heading">
                <div class="card-body p-4">
                    <h2 id="summary-heading" class="section-title fw-bold mb-3"><i class="bi bi-receipt-cutoff me-2 section-icon"></i>Order Summary</h2>
                    <div class="summary-box">
                        <div class="d-flex justify-content-between text-secondary mb-2"><span>Subtotal</span><span><?= peso($total) ?></span></div>
                        <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top"><span>Total Amount</span><span class="summary-total"><?= peso($total) ?></span></div>
                    </div>
                </div>
            </section>

            <section class="card panel-card" aria-labelledby="payment-heading">
                <div class="card-body p-4">
                    <h2 id="payment-heading" class="section-title fw-bold mb-3"><i class="bi bi-cash-coin me-2 section-icon"></i>Cash Payment</h2>
                    <?php if ($paymentError !== ''): ?>
                        <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-circle me-1"></i><?= h($paymentError) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="pay">
                        <label for="amount_paid" class="form-label">Amount paid</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text">₱</span>
                            <input class="form-control payment-input" id="amount_paid" name="amount_paid" type="number" step="0.01" inputmode="decimal" value="<?= h($paymentInput) ?>" placeholder="Enter cash amount" <?= $receipt ? 'disabled' : '' ?>>
                        </div>
                        <button class="btn btn-success w-100" type="submit" <?= (!$cart || $receipt) ? 'disabled' : '' ?>>
                            <i class="bi bi-check-circle me-1"></i>Confirm Payment
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <?php if (is_array($receipt)): ?>
        <section class="card panel-card mt-4" aria-labelledby="receipt-heading">
            <div class="card-body p-4 p-md-5">
                <div class="receipt rounded-3 p-4 mx-auto" style="max-width: 680px">
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill receipt-check fs-1"></i>
                        <h2 id="receipt-heading" class="h4 fw-bold mt-2">Payment Successful</h2>
                        <p class="text-secondary mb-0">Thank you for your purchase!</p>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-3 small">
                        <span><strong>Reference:</strong> <?= h($receipt['reference']) ?></span>
                        <span><?= h($receipt['date']) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table receipt-table">
                            <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($receipt['items'] as $item): ?>
                                <tr>
                                    <td><?= h($item['name']) ?></td>
                                    <td class="text-center"><?= (int) $item['quantity'] ?></td>
                                    <td class="text-end"><?= peso((float) $item['price']) ?></td>
                                    <td class="text-end"><?= peso((float) $item['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ms-auto" style="max-width: 300px">
                        <div class="d-flex justify-content-between mb-2"><span>Total amount due</span><strong><?= peso((float) $receipt['total']) ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Amount paid</span><strong><?= peso((float) $receipt['amount_paid']) ?></strong></div>
                        <div class="d-flex justify-content-between fs-5 text-success border-top pt-2"><span>Change</span><strong><?= peso((float) $receipt['change']) ?></strong></div>
                    </div>
                </div>
                <form method="post" class="text-center mt-4">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="new_transaction">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat me-1"></i>New Transaction</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
