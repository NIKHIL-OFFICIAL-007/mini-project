<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch tickets created by this user AS A BUYER
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.subject, t.status, t.priority, t.created_at,
               (SELECT COUNT(*) FROM ticket_replies tr 
                WHERE tr.ticket_id = t.id 
                  AND tr.sender_role = 'support' 
                  AND tr.is_read = FALSE) as unread_replies
        FROM tickets t
        WHERE t.user_id = ? AND t.sender_role = 'buyer'
        ORDER BY 
            CASE WHEN t.status = 'open' THEN 1 WHEN t.status = 'in_progress' THEN 2 ELSE 3 END,
            t.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch tickets: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Tickets - AutoParts Hub</title>

  <!-- ✅ Corrected Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">My Support Tickets</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Track your support requests and responses.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">My Support Tickets</h2>
        <p class="text-gray-600 mt-1 text-sm">Track your support requests and responses.</p>
      </div>

      <div class="overflow-x-auto">
        <?php if (empty($tickets)): ?>
          <div class="text-center py-16 px-6">
            <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-500">No tickets yet</h3>
            <p class="text-gray-400 mt-2">Open a new ticket to get help.</p>
            <a href="ticket_form.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              Open Ticket
            </a>
          </div>
        <?php else: ?>
          <table class="min-w-full">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Subject</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Priority</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tickets as $index => $ticket): ?>
                <tr class="hover:bg-gray-200 <?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-100' ?>">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="font-mono font-medium text-gray-800">#<?= htmlspecialchars($ticket['id']) ?></span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></div>
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
                  <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                    <?= date('M j, Y', strtotime($ticket['created_at'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <a href="view_ticket.php?id=<?= (int)$ticket['id'] ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline text-sm font-medium">
                      <i class="fas fa-eye mr-1.5"></i> View
                      <?php if ($ticket['unread_replies'] > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-1"><?= (int)$ticket['unread_replies'] ?></span>
                      <?php endif; ?>
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

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>