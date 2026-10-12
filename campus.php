<?php
declare(strict_types=1);

session_start();

$services = [
    'bw-print' => ['name' => 'Black and White Printing', 'unit' => 'page', 'price' => 2.00, 'icon' => 'bi-printer'],
    'color-print' => ['name' => 'Colored Printing', 'unit' => 'page', 'price' => 5.00, 'icon' => 'bi-printer-fill'],
    'photocopy' => ['name' => 'Photocopy', 'unit' => 'page', 'price' => 1.50, 'icon' => 'bi-copy'],
    'scanning' => ['name' => 'Scanning', 'unit' => 'page', 'price' => 5.00, 'icon' => 'bi-upc-scan'],
    'lamination' => ['name' => 'Document Lamination', 'unit' => 'sheet', 'price' => 20.00, 'icon' => 'bi-file-earmark-lock'],
    'id-picture' => ['name' => 'ID Picture Printing', 'unit' => 'set', 'price' => 30.00, 'icon' => 'bi-person-badge'],
];

$orderKey = 'campus_order';
$receiptKey = 'campus_receipt';
$errorKey = 'campus_payment_error';
$paymentKey = 'campus_payment_input';
$tokenKey = 'campus_csrf_token';

if (!isset($_SESSION[$orderKey]) || !is_array($_SESSION[$orderKey])) {
    $_SESSION[$orderKey] = [];
}
if (!isset($_SESSION[$tokenKey])) {
    $_SESSION[$tokenKey] = bin2hex(random_bytes(32));
}

$order = &$_SESSION[$orderKey];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION[$tokenKey], $token)) {
        http_response_code(400);
        exit('Invalid request token. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $serviceId = $_POST['service_id'] ?? '';

    if ($action === 'new_transaction') {
        $order = [];
        unset($_SESSION[$receiptKey], $_SESSION[$errorKey], $_SESSION[$paymentKey]);
    } elseif (!isset($_SESSION[$receiptKey]) && $action === 'add'
        && is_string($serviceId) && isset($services[$serviceId])) {
        $order[$serviceId] = ($order[$serviceId] ?? 0) + 1;
        unset($_SESSION[$errorKey], $_SESSION[$paymentKey]);
    } elseif (!isset($_SESSION[$receiptKey])
        && in_array($action, ['increase', 'decrease', 'remove'], true)
        && is_string($serviceId) && isset($services[$serviceId], $order[$serviceId])) {
        if ($action === 'increase') {
            $order[$serviceId]++;
        } elseif ($action === 'decrease') {
            if ($order[$serviceId] <= 1) {
                unset($order[$serviceId]);
            } else {
                $order[$serviceId]--;
            }
        } else {
            unset($order[$serviceId]);
        }
        unset($_SESSION[$errorKey], $_SESSION[$paymentKey]);
    } elseif (!isset($_SESSION[$receiptKey]) && $action === 'pay') {
        $rawPayment = $_POST['amount_paid'] ?? '';
        $paymentInput = is_string($rawPayment) ? trim($rawPayment) : '';
        $_SESSION[$paymentKey] = $paymentInput;
        $paymentError = '';

        if (!$order) {
            $paymentError = 'Add at least one service to your order before confirming payment.';
        } elseif ($paymentInput === '') {
            $paymentError = 'Please enter the amount paid.';
        } elseif (!is_numeric($paymentInput) || !is_finite((float) $paymentInput)) {
            $paymentError = 'Enter a valid numeric payment amount.';
        } elseif ((float) $paymentInput < 0) {
            $paymentError = 'Payment cannot be negative.';
        } else {
            $total = 0.0;
            $receiptItems = [];
            foreach ($order as $id => $quantity) {
                if (!isset($services[$id]) || !is_int($quantity) || $quantity < 1) {
                    continue;
                }
                $subtotal = $services[$id]['price'] * $quantity;
                $total += $subtotal;
                $receiptItems[] = [
                    'name' => $services[$id]['name'],
                    'unit' => $services[$id]['unit'],
                    'quantity' => $quantity,
                    'price' => $services[$id]['price'],
                    'subtotal' => $subtotal,
                ];
            }

            $amountPaid = (float) $paymentInput;
            if ($amountPaid < $total) {
                $paymentError = 'Payment is less than the total amount due.';
            } else {
                $_SESSION[$receiptKey] = [
                    'reference' => 'CP-' . strtoupper(bin2hex(random_bytes(4))),
                    'date' => date('F j, Y g:i A'),
                    'items' => $receiptItems,
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'change' => $amountPaid - $total,
                ];
                unset($_SESSION[$paymentKey], $_SESSION[$errorKey]);
            }
        }

        if ($paymentError !== '') {
            $_SESSION[$errorKey] = $paymentError;
        }
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$receipt = $_SESSION[$receiptKey] ?? null;
$paymentError = isset($_SESSION[$errorKey]) ? (string) $_SESSION[$errorKey] : '';
$paymentInput = isset($_SESSION[$paymentKey]) ? (string) $_SESSION[$paymentKey] : '';
$total = 0.0;
foreach ($order as $id => $quantity) {
    if (isset($services[$id]) && is_int($quantity) && $quantity > 0) {
        $total += $services[$id]['price'] * $quantity;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campus Printing &amp; Photocopying</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #193631;
            --muted: #71817c;
            --brand: #168477;
            --brand-dark: #10685e;
            --page: #f5f6f2;
            --line: #e5ebe6;
        }
        body { background: var(--page); color: var(--ink); font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif; }
        .navbar { min-height: 76px; }
        .navbar-brand { color: var(--ink); letter-spacing: -.025em; }
        .brand-icon { width: 2.65rem; height: 2.65rem; background: linear-gradient(145deg, #28a392, #126e63); box-shadow: 0 .45rem 1rem #16847738; }
        .hero { position: relative; overflow: hidden; color: #fff; background: linear-gradient(115deg, #193f39 0%, #168477 62%, #39a18b 100%); border-radius: 1.25rem; box-shadow: 0 1rem 2.5rem #145b5026; }
        .hero::after { position: absolute; content: ""; width: 15rem; height: 15rem; right: -3rem; top: -8rem; border: 1px solid #ffffff30; border-radius: 50%; box-shadow: 0 0 0 2rem #ffffff12, 0 0 0 4rem #ffffff09; }
        .hero-content { position: relative; z-index: 1; }
        .hero-kicker { color: #d5f1e5; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .hero h1 { letter-spacing: -.04em; }
        .hero-copy { color: #e0f1eb; }
        .section-title { font-size: 1.05rem; letter-spacing: -.02em; }
        .section-icon { color: var(--brand); }
        .service-card, .panel-card { background: #fff; border: 1px solid var(--line) !important; border-radius: 1rem; box-shadow: 0 .25rem 1.1rem #1936310b !important; }
        .service-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
        .service-card:hover { transform: translateY(-3px); border-color: #b9ded4 !important; box-shadow: 0 .75rem 1.8rem #19363117 !important; }
        .service-icon { width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; color: var(--brand); background: #e8f4ef; border-radius: .9rem; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .service-price { color: var(--ink); font-weight: 700; }
        .btn { border-radius: .65rem; font-weight: 600; }
        .btn-primary { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: var(--brand-dark); --bs-btn-hover-border-color: var(--brand-dark); --bs-btn-active-bg: var(--brand-dark); --bs-btn-active-border-color: var(--brand-dark); }
        .btn-success { --bs-btn-bg: #168477; --bs-btn-border-color: #168477; --bs-btn-hover-bg: #10685e; --bs-btn-hover-border-color: #10685e; }
        .panel-card .card-header { border-radius: 1rem 1rem 0 0; }
        .order-table, .receipt-table { --bs-table-bg: transparent; --bs-table-border-color: var(--line); }
        .order-table thead th, .receipt-table thead th { color: var(--muted); font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .order-table td { padding-top: .9rem; padding-bottom: .9rem; }
        .quantity-form { display: inline; }
        .quantity-control { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem; border: 1px solid var(--line); border-radius: .6rem; background: #fff; }
        .quantity-control .btn { width: 1.65rem; height: 1.65rem; line-height: 1; border: 0; border-radius: .4rem; color: #536b64; }
        .quantity-value { min-width: 1.1rem; font-size: .875rem; font-weight: 700; }
        .remove-button { color: #a4adbc !important; }
        .remove-button:hover { color: #dc3545 !important; }
        .summary-box { padding: 1rem; border-radius: .85rem; background: #f2f6f3; }
        .summary-total { color: var(--brand); }
        .input-group-text { border-color: #dce6df; background: #f4f7f4; color: var(--muted); }
        .form-control { border-color: #dce6df; }
        .form-control:focus { border-color: #83c6b5; box-shadow: 0 0 0 .2rem #16847720; }
        .payment-input { border-radius: 0 .65rem .65rem 0 !important; }
        .empty-order { border: 1px dashed #d3dfd8; border-radius: .85rem; background: #f8faf8; }
        .receipt { border: 1px dashed #c7d8ce; background: #fff; }
        .receipt-check { color: #168477; }
        @media (min-width: 992px) { .order-column { position: sticky; top: 1.25rem; align-self: flex-start; } }
        @media (max-width: 575.98px) {
            .hero { border-radius: 1rem; }
            .hero h1 { font-size: 1.65rem; }
            .order-table { min-width: 440px; }
        }
    </style>
</head>
<body>
<nav class="navbar bg-white border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="campus.php">
            <span class="brand-icon rounded-3 text-white d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-printer-fill"></i>
            </span>
            Campus Print Shop
        </a>
        <span class="text-secondary small"><i class="bi bi-geo-alt me-1"></i>Near Campus</span>
    </div>
</nav>

<main class="container pb-5">
    <header class="hero p-4 p-md-5 mb-4">
        <div class="hero-content">
            <div class="hero-kicker mb-2"><i class="bi bi-lightning-charge me-1"></i> Fast campus services</div>
            <h1 class="h2 fw-bold mb-2">Print, copy, and get on with your day.</h1>
            <p class="hero-copy mb-0">Select the services you need, set your quantities, and pay with cash.</p>
        </div>
    </header>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="services-heading">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="text-uppercase small fw-bold text-secondary mb-1">Print shop menu</div>
                    <h2 id="services-heading" class="section-title fw-bold mb-0"><i class="bi bi-grid me-2 section-icon"></i>Service List</h2>
                </div>
                <span class="badge rounded-pill bg-white border text-secondary px-3 py-2">6 services</span>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 g-3">
                <?php foreach ($services as $id => $service): ?>
                    <div class="col">
                        <article class="card service-card h-100">
                            <div class="card-body d-flex align-items-center gap-3 p-3 p-xl-4">
                                <span class="service-icon"><i class="bi <?= h($service['icon']) ?>" aria-hidden="true"></i></span>
                                <div class="flex-grow-1">
                                    <h3 class="h6 fw-bold mb-1"><?= h($service['name']) ?></h3>
                                    <span class="service-price"><?= money($service['price']) ?></span>
                                    <span class="small text-secondary"> / <?= h($service['unit']) ?></span>
                                </div>
                                <?php if (!$receipt): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION[$tokenKey]) ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="service_id" value="<?= h($id) ?>">
                                        <button class="btn btn-primary btn-sm px-3" type="submit" aria-label="Add <?= h($service['name']) ?> to order">
                                            <i class="bi bi-plus-lg me-sm-1"></i><span class="d-none d-sm-inline">Add</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="col-lg-5 order-column">
            <section class="card panel-card mb-4" aria-labelledby="order-heading">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 id="order-heading" class="section-title fw-bold mb-0"><i class="bi bi-basket2 me-2 section-icon"></i>Order List</h2>
                </div>
                <div class="card-body px-4">
                    <?php if (!$order): ?>
                        <div class="empty-order text-center text-secondary py-4">
                            <i class="bi bi-basket empty-order-icon fs-2 d-block mb-2"></i>Your order is empty.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table order-table align-middle mb-0">
                                <thead>
                                <tr><th>Service</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($order as $id => $quantity): ?>
                                    <?php if (!isset($services[$id]) || !is_int($quantity) || $quantity < 1) { continue; } ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= h($services[$id]['name']) ?></div>
                                            <small class="text-secondary"><?= money($services[$id]['price']) ?> / <?= h($services[$id]['unit']) ?></small>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <?php if (!$receipt): ?>
                                                <form method="post" class="quantity-form">
                                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION[$tokenKey]) ?>">
                                                    <input type="hidden" name="service_id" value="<?= h($id) ?>">
                                                    <span class="quantity-control">
                                                        <button class="btn btn-light btn-sm p-0" name="action" value="decrease" type="submit" aria-label="Decrease <?= h($services[$id]['name']) ?> quantity">−</button>
                                                        <span class="quantity-value"><?= $quantity ?></span>
                                                        <button class="btn btn-light btn-sm p-0" name="action" value="increase" type="submit" aria-label="Increase <?= h($services[$id]['name']) ?> quantity">+</button>
                                                    </span>
                                                </form>
                                            <?php else: ?>
                                                <?= $quantity ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold"><?= money($services[$id]['price'] * $quantity) ?></td>
                                        <td class="text-end">
                                            <?php if (!$receipt): ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION[$tokenKey]) ?>">
                                                    <input type="hidden" name="service_id" value="<?= h($id) ?>">
                                                    <button class="btn btn-link remove-button p-0" name="action" value="remove" type="submit" aria-label="Remove <?= h($services[$id]['name']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
                        <div class="d-flex justify-content-between text-secondary mb-2"><span>Subtotal</span><span><?= money($total) ?></span></div>
                        <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top"><span>Total Amount</span><span class="summary-total"><?= money($total) ?></span></div>
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
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION[$tokenKey]) ?>">
                        <input type="hidden" name="action" value="pay">
                        <label for="amount_paid" class="form-label">Amount paid</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text">₱</span>
                            <input class="form-control payment-input" id="amount_paid" name="amount_paid" type="number" step="0.01" inputmode="decimal" value="<?= h($paymentInput) ?>" placeholder="Enter cash amount" <?= $receipt ? 'disabled' : '' ?>>
                        </div>
                        <button class="btn btn-success w-100" type="submit" <?= (!$order || $receipt) ? 'disabled' : '' ?>>
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
                <div class="receipt rounded-3 p-4 mx-auto" style="max-width: 720px">
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill receipt-check fs-1"></i>
                        <h2 id="receipt-heading" class="h4 fw-bold mt-2">Payment Successful</h2>
                        <p class="text-secondary mb-0">Thank you for choosing Campus Print Shop.</p>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between gap-2 border-bottom pb-2 mb-3 small">
                        <span><strong>Reference:</strong> <?= h($receipt['reference']) ?></span>
                        <span><?= h($receipt['date']) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table receipt-table">
                            <thead><tr><th>Service</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($receipt['items'] as $item): ?>
                                <tr>
                                    <td><?= h($item['name']) ?></td>
                                    <td class="text-center"><?= (int) $item['quantity'] ?> <?= h($item['unit']) ?><?= (int) $item['quantity'] === 1 ? '' : 's' ?></td>
                                    <td class="text-end"><?= money((float) $item['price']) ?></td>
                                    <td class="text-end"><?= money((float) $item['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ms-auto" style="max-width: 300px">
                        <div class="d-flex justify-content-between mb-2"><span>Total amount due</span><strong><?= money((float) $receipt['total']) ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Amount paid</span><strong><?= money((float) $receipt['amount_paid']) ?></strong></div>
                        <div class="d-flex justify-content-between fs-5 text-success border-top pt-2"><span>Change</span><strong><?= money((float) $receipt['change']) ?></strong></div>
                    </div>
                </div>
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Receipt</button>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION[$tokenKey]) ?>">
                        <input type="hidden" name="action" value="new_transaction">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat me-1"></i>New Transaction</button>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<style>
    @media print {
        body { background: #fff; }
        nav, .hero, main > .row, .receipt + .d-flex { display: none !important; }
        main { width: 100%; max-width: none; padding: 0 !important; }
        .panel-card { border: 0 !important; box-shadow: none !important; }
        .receipt { border: 1px dashed #777 !important; }
    }
</style>
</body>
</html>
