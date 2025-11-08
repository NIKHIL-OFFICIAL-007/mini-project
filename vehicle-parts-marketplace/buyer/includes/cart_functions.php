<?php
// buyer/includes/cart_functions.php

function addToCart($part_id, $quantity = 1) {
    if (!isset($_SESSION['user_id'])) return false;

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT stock_quantity FROM parts WHERE id = ?");
    $stmt->execute([$part_id]);
    $part = $stmt->fetch();

    if (!$part || $part['stock_quantity'] < $quantity) {
        return false;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][$part_id] = ($_SESSION['cart'][$part_id] ?? 0) + $quantity;
    return true;
}

function getCartItems() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return [];
    }

    $pdo = getPDO();
    $ids = implode(',', array_keys($_SESSION['cart']));
    $stmt = $pdo->query("SELECT id, name, price, image_url, stock_quantity FROM parts WHERE id IN ($ids)");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cart_items = [];
    foreach ($items as $item) {
        $cart_items[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'price' => $item['price'],
            'image_url' => $item['image_url'],
            'stock' => $item['stock_quantity'],
            'quantity' => $_SESSION['cart'][$item['id']]
        ];
    }

    return $cart_items;
}

function removeFromCart($part_id) {
    if (isset($_SESSION['cart'][$part_id])) {
        unset($_SESSION['cart'][$part_id]);
        return true;
    }
    return false;
}