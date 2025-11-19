<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has approved support role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role']) || !isset($_SESSION['role_status'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('support', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);

// Safely get ticket ID
$ticket_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$ticket_id) {
    $_SESSION['error'] = "Invalid ticket ID.";
    header("Location: tickets.php");
    exit();
}

$ticket = null;
$replies = [];

try {
    // Fetch ticket with user info
    $stmt = $pdo->prepare("
        SELECT t.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $_SESSION['error'] = "Ticket not found.";
        header("Location: tickets.php");
        exit();
    }

    // Fetch all replies with sender info including is_system
    $reply_stmt = $pdo->prepare("
        SELECT tr.*, u.name AS sender_name, u.role AS sender_user_role, tr.is_system
        FROM ticket_replies tr
        JOIN users u ON tr.sender_id = u.id
        WHERE tr.ticket_id = ?
        ORDER BY tr.created_at ASC
    ");
    $reply_stmt->execute([$ticket_id]);
    $replies = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark all buyer/seller replies as read
    $mark_read = $pdo->prepare("
        UPDATE ticket_replies 
        SET is_read = TRUE 
        WHERE ticket_id = ? 
          AND sender_role IN ('buyer', 'seller') 
          AND is_read = FALSE
    ");
    $mark_read->execute([$ticket_id]);

} catch (Exception $e) {
    error_log("Failed to load ticket: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the ticket.";
    header("Location: tickets.php");
    exit();
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message = trim($_POST['reply_message']);

    if (empty($message)) {
        $_SESSION['error'] = "Reply message cannot be empty.";
    } else {
        try {
            $insert_reply = $pdo->prepare("
                INSERT INTO ticket_replies (ticket_id, sender_id, sender_role, message, is_read, created_at)
                VALUES (?, ?, 'support', ?, 0, NOW())
            ");
            $insert_reply->execute([$ticket_id, $user_id, $message]);

            // Auto-update status to 'in_progress' on first reply
            if ($ticket['status'] === 'open') {
                $update_status = $pdo->prepare("UPDATE tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
                $update_status->execute([$ticket_id]);
                $ticket['status'] = 'in_progress';
            } else {
                $update_timestamp = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                $update_timestamp->execute([$ticket_id]);
            }

            $_SESSION['success'] = "Your reply has been sent successfully.";
            header("Location: view_ticket.php?id=" . (int)$ticket_id);
            exit();

        } catch (Exception $e) {
            error_log("Failed to send reply: " . $e->getMessage());
            $_SESSION['error'] = "Failed to send reply. Please try again.";
        }
    }

    // Redirect after error
    header("Location: view_ticket.php?id=" . (int)$ticket_id);
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $valid_statuses = ['open', 'in_progress', 'resolved', 'closed'];

    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid status selected.";
    } else {
        try {
            $update_query = "UPDATE tickets SET status = ?, updated_at = NOW()";
            
            if ($new_status === 'resolved') {
                $update_query .= ", resolved_at = NOW(), resolved_by = ?";
            } elseif ($new_status === 'closed') {
                $update_query .= ", closed_at = NOW()";
            } elseif ($new_status === 'open') {
                $update_query .= ", resolved_at = NULL, resolved_by = NULL, closed_at = NULL";
            }
            
            $update_query .= " WHERE id = ?";
            
            $update_status = $pdo->prepare($update_query);

            if ($new_status === 'resolved') {
                $update_status->execute([$new_status, $user_id, $ticket_id]);
            } else {
                $update_status->execute([$new_status, $ticket_id]);
            }
            
            $ticket['status'] = $new_status;

            // Add system message about status change
            $system_message = "Ticket status changed to '" . ucfirst($new_status) . "' by support agent.";
            $insert_notification = $pdo->prepare("
                INSERT INTO ticket_replies (ticket_id, sender_id, sender_role, message, is_system, created_at)
                VALUES (?, ?, 'support', ?, 1, NOW())
            ");
            $insert_notification->execute([$ticket_id, $user_id, $system_message]);

            $_SESSION['success'] = "Ticket status updated to '" . ucfirst($new_status) . "'.";
            header("Location: view_ticket.php?id=" . (int)$ticket_id);
            exit();

        } catch (Exception $e) {
            error_log("Status update failed: " . $e->getMessage());
            $_SESSION['error'] = "Could not update status.";
        }
    }

    // Redirect after error
    header("Location: view_ticket.php?id=" . (int)$ticket_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Ticket - AutoParts Hub</title>

  <!-- ✅ Corrected Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/support_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Ticket #<?= htmlspecialchars($ticket['id']) ?></h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Manage this support request.</p>
    </div>
  </div>

  <!-- Main Content -->  
  <div class="container mx-auto px-6 py-8">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
        <i class="fas fa-check-circle mr-2"></i> 
        <?= htmlspecialchars($_SESSION['success']) ?>
        <?php unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
        <i class="fas fa-exclamation-circle mr-2"></i> 
        <?= htmlspecialchars($_SESSION['error']) ?>
        <?php unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Ticket Info -->  
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($ticket['subject']) ?></h2>
            <p class="text-gray-600 mt-1">
              Submitted by <strong><?= htmlspecialchars($ticket['user_name']) ?></strong> on 
              <?= date('M j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
            </p>
          </div>
          
          <div class="p-6 space-y-6">
            <!-- User Message -->
            <div class="p-4 bg-blue-50 rounded-lg">
              <div class="flex justify-between items-center mb-2">
                <strong>
                  <?= htmlspecialchars($ticket['user_name']) ?> 
                  <?php
                  $sender_role = $ticket['sender_role'] ?? 'unknown';
                  $color = $sender_role === 'buyer' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                  ?>
                  <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                    <?= ucfirst($sender_role) ?>
                  </span>
                </strong>
                <span class="text-gray-500 text-sm"><?= date('M j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?></span>
              </div>
              <p class="text-gray-800 leading-relaxed"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
            </div>

            <!-- All Replies -->
            <?php foreach ($replies as $reply): ?>
              <div class="p-4 
                <?= ($reply['is_system'] ?? 0) == 1 ? 'bg-gray-50 italic' : 
                   ($reply['sender_role'] === 'support' ? 'bg-purple-50' : 
                   ($reply['sender_role'] === 'buyer' ? 'bg-blue-50' : 'bg-green-50')) ?> 
                rounded-lg"
              >
                <div class="flex justify-between items-center mb-2">
                  <strong>
                    <?php if (($reply['is_system'] ?? 0) == 1): ?>
                      <i class="fas fa-cog mr-1 text-gray-500"></i> System
                    <?php else: ?>
                      <?= htmlspecialchars($reply['sender_name']) ?>
                    <?php endif; ?>
                    <span class="px-2 py-1 rounded-full text-xs font-medium
                      <?= ($reply['is_system'] ?? 0) ? 'bg-gray-100 text-gray-800' :
                         ($reply['sender_role'] === 'buyer' ? 'bg-blue-100 text-blue-800' :
                         ($reply['sender_role'] === 'seller' ? 'bg-green-100 text-green-800' :
                         ($reply['sender_role'] === 'support' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'))) ?>">
                      <?= ($reply['is_system'] ?? 0) ? 'System' : ucfirst($reply['sender_role']) ?>
                    </span>
                  </strong>
                  <span class="text-gray-500 text-sm"><?= date('M j, Y \a\t g:i A', strtotime($reply['created_at'])) ?></span>
                </div>
                <p class="text-gray-800 leading-relaxed"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
              </div>
            <?php endforeach; ?>

            <!-- Reply Form -->
            <div class="mt-8 p-6 bg-white rounded-xl shadow-md border border-gray-200">
              <h3 class="text-lg font-bold text-gray-800 mb-4">Send a Reply</h3>
              <form method="POST">
                <textarea 
                  name="reply_message"
                  rows="4"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                  placeholder="Type your response here..."
                  required></textarea>
                <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium mt-3">
                  Send Reply
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->  
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md overflow-hidden sticky top-24">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Ticket Info</h2>
          </div>
          
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">User</label>
              <p class="text-gray-800"><?= htmlspecialchars($ticket['user_name']) ?></p>
              <p class="text-gray-600 text-sm"><?= htmlspecialchars($ticket['user_email']) ?></p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Priority</label>
              <?php if ($ticket['priority'] === 'urgent'): ?>
                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">Urgent</span>
              <?php elseif ($ticket['priority'] === 'high'): ?>
                <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-bold">High</span>
              <?php elseif ($ticket['priority'] === 'medium'): ?>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">Medium</span>
              <?php else: ?>
                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-bold">Low</span>
              <?php endif; ?>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <?php if ($ticket['status'] === 'open'): ?>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Open</span>
              <?php elseif ($ticket['status'] === 'in_progress'): ?>
                <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">In Progress</span>
              <?php elseif ($ticket['status'] === 'resolved'): ?>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Resolved</span>
              <?php else: ?>
                <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">Closed</span>
              <?php endif; ?>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Category</label>
              <p class="text-gray-800"><?= ucfirst(htmlspecialchars($ticket['category'] ?? 'General')) ?></p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Submitted</label>
              <p class="text-gray-800"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></p>
              <label class="block text-sm font-medium text-gray-700 mt-2">Last Updated</label>
              <p class="text-gray-800"><?= date('M j, Y', strtotime($ticket['updated_at'])) ?></p>
            </div>
            
            <?php if ($ticket['status'] === 'resolved' && !empty($ticket['resolved_at'])): ?>
              <div>
                <label class="block text-sm font-medium text-gray-700">Resolved On</label>
                <p class="text-gray-800"><?= date('M j, Y', strtotime($ticket['resolved_at'])) ?></p>
              </div>
            <?php endif; ?>
            
            <?php if ($ticket['status'] === 'closed' && !empty($ticket['closed_at'])): ?>
              <div>
                <label class="block text-sm font-medium text-gray-700">Closed On</label>
                <p class="text-gray-800"><?= date('M j, Y', strtotime($ticket['closed_at'])) ?></p>
              </div>
            <?php endif; ?>
            
            <!-- Update Status Form -->
            <form method="POST" class="pt-4 border-t">
              <label class="block text-sm font-medium text-gray-700 mb-2">Update Status</label>
              <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-3" required>
                <option value="">-- Select Status --</option>
                <option value="open" <?= ($ticket['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= ($ticket['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="resolved" <?= ($ticket['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="closed" <?= ($ticket['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
              </select>
              <button type="submit" name="update_status" class="w-full px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition font-medium">
                Update Status
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/support_footer.php'; ?>
</body>
</html>