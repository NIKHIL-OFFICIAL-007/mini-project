<?php
session_start();
include 'includes/config.php';

// Support check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}
$roles = explode(',', $_SESSION['role']);
if (!in_array('support', $roles) || ($_SESSION['role_status'] ?? '') !== 'approved') {
    header("Location: ../login.php");
    exit();
}

// Configuration
$per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $per_page;

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status']) && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (in_array($new_status, $allowed_statuses)) {
            try {
                $pdo->beginTransaction();
                
                // Update order status
                $update_stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$new_status, $order_id]);
                
                // Add to status history
                $history_stmt = $pdo->prepare("
                    INSERT INTO order_status_history (order_id, status, changed_by, changed_by_role, notes)
                    VALUES (?, ?, ?, 'support', ?)
                ");
                $history_stmt->execute([$order_id, $new_status, $_SESSION['user_id'], $notes]);
                
                $pdo->commit();
                
                $_SESSION['success'] = "Order #{$order_id} status updated to " . ucfirst($new_status) . "!";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Failed to update order status: " . $e->getMessage());
                $_SESSION['error'] = "Failed to update order status. Please try again.";
            }
        }
        
        header("Location: manage_orders.php?" . http_build_query([
            'page' => $current_page,
            'status' => $filter_status,
            'search' => $search_term
        ]));
        exit();
    }
    
    if (isset($_POST['cancel_order']) && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $cancellation_reason = $_POST['cancellation_reason'] ?? '';
        
        if (empty($cancellation_reason)) {
            $_SESSION['error'] = "Cancellation reason is required.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update order status to cancelled
                $update_stmt = $pdo->prepare("
                    UPDATE orders 
                    SET status = 'cancelled', 
                        cancelled_by = 'support',
                        cancellation_reason = ?,
                        cancelled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$cancellation_reason, $order_id]);
                
                // Add to status history
                $history_stmt = $pdo->prepare("
                    INSERT INTO order_status_history (order_id, status, changed_by, changed_by_role, notes)
                    VALUES (?, 'cancelled', ?, 'support', ?)
                ");
                $history_stmt->execute([$order_id, $_SESSION['user_id'], "Cancelled by support: " . $cancellation_reason]);
                
                $pdo->commit();
                
                $_SESSION['success'] = "Order #{$order_id} has been cancelled successfully.";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Failed to cancel order: " . $e->getMessage());
                $_SESSION['error'] = "Failed to cancel order. Please try again.";
            }
        }
        
        header("Location: manage_orders.php?" . http_build_query([
            'page' => $current_page,
            'status' => $filter_status,
            'search' => $search_term
        ]));
        exit();
    }
}

// Build SQL query
$sql_where = [];
$sql_params = [];

// Filter by status
if ($filter_status !== 'all' && in_array($filter_status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
    $sql_where[] = "o.status = ?";
    $sql_params[] = $filter_status;
}

// Search filter
if ($search_term) {
    $sql_where[] = "(o.id LIKE ? OR u_buyer.name LIKE ? OR u_seller.name LIKE ? OR o.shipping_name LIKE ?)";
    $sql_params[] = '%' . $search_term . '%';
    $sql_params[] = '%' . $search_term . '%';
    $sql_params[] = '%' . $search_term . '%';
    $sql_params[] = '%' . $search_term . '%';
}

$where_clause = count($sql_where) > 0 ? " WHERE " . implode(" AND ", $sql_where) : "";

// Get total count
$total_orders = 0;
try {
    $count_sql = "
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        LEFT JOIN users u_buyer ON o.buyer_id = u_buyer.id
        LEFT JOIN users u_seller ON o.seller_id = u_seller.id
        " . $where_clause;
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($sql_params);
    $total_orders = (int)$count_stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to count orders: " . $e->getMessage());
}

$total_pages = ceil($total_orders / $per_page);

// Fetch orders with minimal details
$orders = [];
try {
    $orders_sql = "
        SELECT 
            o.id, o.total_amount, o.status, o.created_at, o.updated_at,
            o.shipping_name, o.shipping_city, o.shipping_state, o.shipping_country,
            o.cancelled_by, o.cancellation_reason, o.cancelled_at,
            u_buyer.name as buyer_name,
            u_seller.name as seller_name,
            COUNT(DISTINCT oi.id) as item_count,
            SUM(oi.quantity) as total_quantity,
            COUNT(DISTINCT p.seller_id) as seller_count
        FROM orders o
        LEFT JOIN users u_buyer ON o.buyer_id = u_buyer.id
        LEFT JOIN users u_seller ON o.seller_id = u_seller.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN parts p ON oi.part_id = p.id
        " . $where_clause . "
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
    
    $orders_stmt = $pdo->prepare($orders_sql);
    
    // Bind parameters
    $param_index = 0;
    foreach ($sql_params as $param) {
        $orders_stmt->bindValue(++$param_index, $param);
    }
    $orders_stmt->bindValue(++$param_index, $per_page, PDO::PARAM_INT);
    $orders_stmt->bindValue(++$param_index, $offset, PDO::PARAM_INT);
    
    $orders_stmt->execute();
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get seller names for each order
    foreach ($orders as &$order) {
        $seller_stmt = $pdo->prepare("
            SELECT DISTINCT u.name as seller_name
            FROM order_items oi
            JOIN parts p ON oi.part_id = p.id
            JOIN users u ON p.seller_id = u.id
            WHERE oi.order_id = ?
        ");
        $seller_stmt->execute([$order['id']]);
        $sellers = $seller_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $seller_names = array_column($sellers, 'seller_name');
        $order['seller_names'] = implode(', ', $seller_names);
        $order['seller_count'] = count($seller_names);
    }
    unset($order); // Break the reference
    
} catch (Exception $e) {
    error_log("Failed to fetch orders: " . $e->getMessage());
}

// Get status theme
function getStatusTheme($status) {
    switch ($status) {
        case 'pending': 
            return [
                'bg_color' => 'bg-yellow-50',
                'border_color' => 'border-yellow-200',
                'text_color' => 'text-yellow-800',
                'badge_color' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'fas fa-clock'
            ];
        case 'processing': 
            return [
                'bg_color' => 'bg-blue-50',
                'border_color' => 'border-blue-200',
                'text_color' => 'text-blue-800',
                'badge_color' => 'bg-blue-100 text-blue-800',
                'icon' => 'fas fa-cog'
            ];
        case 'shipped': 
            return [
                'bg_color' => 'bg-purple-50',
                'border_color' => 'border-purple-200',
                'text_color' => 'text-purple-800',
                'badge_color' => 'bg-purple-100 text-purple-800',
                'icon' => 'fas fa-shipping-fast'
            ];
        case 'delivered': 
            return [
                'bg_color' => 'bg-green-50',
                'border_color' => 'border-green-200',
                'text_color' => 'text-green-800',
                'badge_color' => 'bg-green-100 text-green-800',
                'icon' => 'fas fa-check-circle'
            ];
        case 'cancelled': 
            return [
                'bg_color' => 'bg-red-50',
                'border_color' => 'border-red-200',
                'text_color' => 'text-red-800',
                'badge_color' => 'bg-red-100 text-red-800',
                'icon' => 'fas fa-times-circle'
            ];
        default: 
            return [
                'bg_color' => 'bg-gray-50',
                'border_color' => 'border-gray-200',
                'text_color' => 'text-gray-800',
                'badge_color' => 'bg-gray-100 text-gray-800',
                'icon' => 'fas fa-question-circle'
            ];
    }
}

// Get order statistics
$order_stats = [];
try {
    $stats_sql = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total_amount) as total_amount
        FROM orders 
        GROUP BY status
    ";
    $stats_stmt = $pdo->query($stats_sql);
    $stats_data = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats_data as $stat) {
        $order_stats[$stat['status']] = $stat;
    }
} catch (Exception $e) {
    error_log("Failed to fetch order statistics: " . $e->getMessage());
}

// Format price without .00
function formatPrice($price) {
    $price = floatval($price);
    if ($price == intval($price)) {
        return number_format($price, 0);
    } else {
        return number_format($price, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Orders - Support Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        .order-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }
        .order-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.25);
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <?php include 'includes/support_header.php'; ?>

    <!-- Page Header -->
    <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
        <div class="container mx-auto px-6 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Order Management</h1>
            <p class="text-blue-100 max-w-2xl mx-auto text-lg">Manage and monitor all orders across the platform.</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Order Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-2xl p-6 shadow-lg">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm opacity-80">Total Orders</p>
                        <p class="text-3xl font-bold"><?= $total_orders ?></p>
                    </div>
                    <i class="fas fa-shopping-cart text-2xl opacity-70"></i>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500">Pending</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $order_stats['pending']['count'] ?? 0 ?></p>
                    </div>
                    <i class="fas fa-clock text-2xl text-yellow-500"></i>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500">Processing</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $order_stats['processing']['count'] ?? 0 ?></p>
                    </div>
                    <i class="fas fa-cog text-2xl text-blue-500"></i>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500">Delivered</p>
                        <p class="text-2xl font-bold text-green-600"><?= $order_stats['delivered']['count'] ?? 0 ?></p>
                    </div>
                    <i class="fas fa-check-circle text-2xl text-green-500"></i>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-blue-100">
                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-filter mr-3 text-blue-600"></i>
                    Filter Orders
                </h2>
            </div>
            
            <div class="p-6">
                <form method="GET" action="manage_orders.php" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="relative">
                            <input type="text" name="search" placeholder="Search by order ID, buyer, seller, or shipping name..."
                                    class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value="<?= htmlspecialchars($search_term) ?>">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $filter_status === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $filter_status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $filter_status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <!-- Filter Buttons -->
                    <div class="md:col-span-4 flex space-x-4">
                        <button type="submit" 
                                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg font-semibold">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                        <a href="manage_orders.php" 
                           class="px-6 py-3 border-2 border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl transition-all duration-300 font-semibold">
                            <i class="fas fa-refresh mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Info -->
        <div class="flex justify-between items-center mb-6">
            <div class="text-gray-600">
                <?php if (empty($orders)): ?>
                    <p class="text-lg">No orders found.</p>
                <?php else: ?>
                    <p class="font-medium text-gray-800">
                        Showing <span class="text-blue-600 font-semibold"><?= count($orders) ?></span> of <span class="text-blue-600 font-semibold"><?= $total_orders ?></span> order<?= $total_orders !== 1 ? 's' : '' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Orders Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php if (empty($orders)): ?>
                <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
                    <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No orders found</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Try adjusting your filters or search terms.</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php $status_theme = getStatusTheme($order['status']); ?>
                    <div class="order-card bg-white rounded-2xl overflow-hidden shadow-sm border <?= $status_theme['border_color'] ?>">
                        <!-- Header -->
                        <div class="<?= $status_theme['bg_color'] ?> px-6 py-4 border-b <?= $status_theme['border_color'] ?>">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="font-bold <?= $status_theme['text_color'] ?> text-lg">Order #<?= $order['id'] ?></h3>
                                    <p class="text-sm <?= $status_theme['text_color'] ?> opacity-80">
                                        <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $status_theme['badge_color'] ?>">
                                    <i class="<?= $status_theme['icon'] ?> mr-1"></i>
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Minimal Content -->
                        <div class="p-6">
                            <!-- Essential Info -->
                            <div class="space-y-4">
                                <!-- Buyer & Sellers -->
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="font-semibold text-gray-700">Buyer</p>
                                        <p class="text-gray-600 truncate"><?= htmlspecialchars($order['buyer_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-700">Sellers</p>
                                        <p class="text-gray-600 line-clamp-2" title="<?= htmlspecialchars($order['seller_names']) ?>">
                                            <?= htmlspecialchars($order['seller_names']) ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Shipping Location -->
                                <div class="border-t border-gray-100 pt-4">
                                    <p class="font-semibold text-gray-700 text-sm mb-1">Shipping To</p>
                                    <p class="text-gray-600 text-sm">
                                        <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?>, <?= htmlspecialchars($order['shipping_country']) ?>
                                    </p>
                                    <p class="text-gray-500 text-xs"><?= htmlspecialchars($order['shipping_name']) ?></p>
                                </div>

                                <!-- Order Summary -->
                                <div class="border-t border-gray-100 pt-4">
                                    <div class="flex justify-between items-center text-sm mb-2">
                                        <span class="text-gray-600">Items:</span>
                                        <span class="font-semibold"><?= $order['item_count'] ?> items</span>
                                    </div>
                                    <div class="flex justify-between items-center text-lg font-bold">
                                        <span class="text-gray-800">Total:</span>
                                        <span class="text-blue-600">₹<?= formatPrice($order['total_amount']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="border-t border-gray-100 pt-4 mt-4 space-y-3">
                                <!-- Status Update (Quick) -->
                                <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                                    <form method="POST" action="manage_orders.php" class="flex space-x-2">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="status" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Quick Update</option>
                                            <option value="processing">Processing</option>
                                            <option value="shipped">Shipped</option>
                                            <option value="delivered">Delivered</option>
                                        </select>
                                        <button type="submit" name="update_status" 
                                                class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-150 text-sm font-semibold">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- View Details Button -->
                                <a href="view_order.php?id=<?= $order['id'] ?>" 
                                   class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-150 text-sm font-semibold">
                                    <i class="fas fa-eye mr-2"></i> View Full Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-between items-center bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                <p class="text-sm text-gray-700">
                    Page <span class="font-semibold"><?= $current_page ?></span> of <span class="font-semibold"><?= $total_pages ?></span>
                </p>
                
                <div class="flex space-x-2">
                    <?php
                    function get_pagination_url($page, $filter_status, $search_term) {
                        $query_params = [
                            'status' => $filter_status,
                            'search' => $search_term,
                            'page' => $page
                        ];
                        return 'manage_orders.php?' . http_build_query(array_filter($query_params));
                    }
                    ?>

                    <a href="<?= $current_page > 1 ? get_pagination_url($current_page - 1, $filter_status, $search_term) : '#' ?>"
                       class="px-4 py-2 rounded-xl font-medium transition-all duration-300 
                              <?= $current_page > 1 
                                ? 'bg-blue-600 text-white hover:bg-blue-700 transform hover:scale-105' 
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed' ?>">
                        <i class="fas fa-chevron-left mr-2"></i>Previous
                    </a>
                    
                    <a href="<?= $current_page < $total_pages ? get_pagination_url($current_page + 1, $filter_status, $search_term) : '#' ?>"
                       class="px-4 py-2 rounded-xl font-medium transition-all duration-300 
                              <?= $current_page < $total_pages 
                                ? 'bg-blue-600 text-white hover:bg-blue-700 transform hover:scale-105' 
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed' ?>">
                        Next<i class="fas fa-chevron-right ml-2"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/support_footer.php'; ?>

    <script>
        // Add interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to order cards
            const orderCards = document.querySelectorAll('.order-card');
            orderCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-6px)';
                    this.style.boxShadow = '0 12px 20px -4px rgba(37, 99, 235, 0.25)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.06)';
                });
            });
        });
    </script>
</body>
</html>