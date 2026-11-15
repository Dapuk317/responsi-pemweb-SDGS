<?php

require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}

// Validasi CSRF.
verify_csrf();

$user_id = (int) $_SESSION['user_id'];

$order_item_id = filter_input(
    INPUT_POST,
    'order_item_id',
    FILTER_VALIDATE_INT
);

$order_id = filter_input(
    INPUT_POST,
    'order_id',
    FILTER_VALIDATE_INT
);

$rating = filter_input(
    INPUT_POST,
    'rating',
    FILTER_VALIDATE_INT
);

$comment = trim($_POST['comment'] ?? '');

if (
    !$order_item_id ||
    !$order_id ||
    !$rating ||
    $rating < 1 ||
    $rating > 5
) {
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}

// Pastikan item benar-benar milik customer
// dan pesanannya sudah selesai.
$stmt = $conn->prepare("
    SELECT
        oi.product_id,
        o.id AS order_id
    FROM order_items oi
    INNER JOIN orders o
        ON oi.order_id = o.id
    WHERE oi.id = ?
      AND o.id = ?
      AND o.user_id = ?
      AND o.status IN ('complete', 'completed')
    LIMIT 1
");

$stmt->bind_param(
    "iii",
    $order_item_id,
    $order_id,
    $user_id
);

$stmt->execute();

$item = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$item || empty($item['product_id'])) {
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}

$product_id = (int) $item['product_id'];

// Periksa apakah item sudah pernah diulas.
$stmt = $conn->prepare("
    SELECT id
    FROM reviews
    WHERE order_item_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $order_item_id);
$stmt->execute();

$existing_review = $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($existing_review) {
    header(
        'Location: /GreenCycle/pages/order-detail.php?id=' .
        $order_id . '&review=duplicate'
    );
    exit;
}

// Simpan ulasan.
$stmt = $conn->prepare("
    INSERT INTO reviews (
        user_id,
        product_id,
        order_item_id,
        rating,
        comment
    )
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iiiis",
    $user_id,
    $product_id,
    $order_item_id,
    $rating,
    $comment
);

if ($stmt->execute()) {

    $stmt->close();

    header(
        'Location: /GreenCycle/pages/order-detail.php?id=' .
        $order_id . '&review=success'
    );

    exit;

}

// Menangani kemungkinan pengiriman ulasan ganda.
$error_code = $stmt->errno;

$stmt->close();

if ($error_code === 1062) {

    header(
        'Location: /GreenCycle/pages/order-detail.php?id=' .
        $order_id . '&review=duplicate'
    );

    exit;
}

http_response_code(500);
exit('Ulasan gagal disimpan. Silakan coba kembali.');