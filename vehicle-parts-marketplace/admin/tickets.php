<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_name = htmlspecialchars($_SESSION['name']);

// Fetch ALL tickets from buyers and sellers
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.subject, t.status, t.priority, u.name as user_name, u.role as user_role, t.created_at, t.sender_role
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE u.role LIKE '%buyer%' OR u.role LIKE '%seller%'
        ORDER BY 
            CASE WHEN t.priority = 'urgent' THEN 1 WHEN t.priority = 'high' THEN 2 ELSE 3 END,
            t.created_at DESC
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch tickets: " . $e->getMessage());
    $_SESSION['error'] = "Could not load tickets.";
}

// Calculate active tickets (not resolved or closed)
$active_tickets = array_filter($tickets, fn($t) => $t['status'] === 'open' || $t['status'] === 'in_progress');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tickets - Admin Dashboard</title>

  <!-- ✅ Corrected Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-indigo-600 to-purple-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Support Tickets</h1>
      <p class="text-indigo-100 max-w-2xl mx-auto text-lg">Monitor all customer and seller support requests.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4">
            <i class="fas fa-ticket-alt text-xl"></i>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Total Tickets</p>
            <p class="text-2xl font-bold text-gray-900"><?= count($tickets) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4">
            <i class="fas fa-check-circle text-xl"></i>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Resolved</p>
            <p class="text-2xl font-bold text-gray-900">
              <?= count(array_filter($tickets, fn($t) => $t['status'] === 'resolved')) ?>
            </p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-orange-100 text-orange-600 mr-4">
            <i class="fas fa-exclamation-circle text-xl"></i>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Urgent</p>
            <p class="text-2xl font-bold text-gray-900">
              <?= count(array_filter($tickets, fn($t) => $t['priority'] === 'urgent')) ?>
            </p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-yellow-100 text-yellow-600 mr-4">
            <i class="fas fa-clock text-xl"></i>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Active</p>
            <p class="text-2xl font-bold text-gray-900">
              <?= count($active_tickets) ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-5 border-b border-indigo-100">
        <h2 class="text-xl font-bold text-gray-800 flex items-center">
          <i class="fas fa-ticket-alt mr-3 text-indigo-600"></i>
          All Tickets
        </h2>
        <p class="text-gray-600 mt-1 text-sm">View and monitor all buyer and seller tickets. (Admin View Only)</p>
      </div>

      <div class="overflow-x-auto">
        <?php if (empty($tickets)): ?>
          <div class="text-center py-16 px-6">
            <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-500">No tickets yet</h3>
            <p class="text-gray-400 mt-2">Wait for users to submit tickets.</p>
          </div>
        <?php else: ?>
          <table class="min-w-full">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Subject</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">User</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Role</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Priority</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tickets as $index => $ticket): ?>
                <tr class="hover:bg-gray-50 <?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="font-mono font-bold text-gray-800 text-sm">#<?= htmlspecialchars($ticket['id']) ?></span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($ticket['subject']) ?></div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($ticket['user_name']) ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php
                    $sender_role = $ticket['sender_role'] ?? 'unknown';
                    $color = $sender_role === 'buyer' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                      <?= ucfirst($sender_role) ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php
                    $priority = $ticket['priority'];
                    $badge = match($priority) {
                        'urgent' => ['bg-red-100', 'text-red-800', 'Urgent'],
                        'high'   => ['bg-orange-100', 'text-orange-800', 'High'],
                        'medium' => ['bg-yellow-100', 'text-yellow-800', 'Medium'],
                        default  => ['bg-gray-100', 'text-gray-800', 'Low']
                    };
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge[0] ?> <?= $badge[1] ?>">
                      <?= $badge[2] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php
                    $status = $ticket['status'];
                    $status_badge = match($status) {
                        'open'        => ['bg-blue-100', 'text-blue-800', 'Open'],
                        'in_progress' => ['bg-purple-100', 'text-purple-800', 'In Progress'],
                        'resolved'    => ['bg-green-100', 'text-green-800', 'Resolved'],
                        default       => ['bg-gray-100', 'text-gray-800', 'Closed']
                    };
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $status_badge[0] ?> <?= $status_badge[1] ?>">
                      <?= $status_badge[2] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-gray-700 text-sm">
                    <?= date('M j, Y', strtotime($ticket['created_at'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <a href="view_ticket.php?id=<?= (int)$ticket['id'] ?>" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition duration-200">
                      <i class="fas fa-eye mr-2"></i> View
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>