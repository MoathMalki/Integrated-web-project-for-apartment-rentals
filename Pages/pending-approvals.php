<?php
// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle approval/rejection actions
if (isset($_POST['action']) && isset($_POST['flat_id'])) {
    $flat_id = $_POST['flat_id'];
    $action = $_POST['action'];
    
    // Get manager_id from managers table
    $stmt = $pdo->prepare("SELECT manager_id FROM managers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $manager = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$manager) {
        $error = "Error: Manager ID not found.";
    } else {
        $manager_id = $manager['manager_id'];
        
        try {
            if ($action === 'approve') {
                // Generate flat reference number (6 digits)
                $flat_reference = generateFlatReference($pdo);
                
                $stmt = $pdo->prepare("UPDATE flats SET status = 'approved', flat_reference = ?, approved_by = ?, approved_date = NOW() WHERE flat_id = ?");
                $stmt->execute([$flat_reference, $manager_id, $flat_id]);
                
                // Get owner details for notification
                $stmt = $pdo->prepare("SELECT u.name, u.email, f.location 
                                     FROM flats f 
                                     JOIN owners o ON f.owner_id = o.owner_id 
                                     JOIN users u ON o.user_id = u.user_id 
                                     WHERE f.flat_id = ?");
                $stmt->execute([$flat_id]);
                $owner_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$owner_info) {
                    throw new Exception("Could not find owner information for flat ID: $flat_id");
                }
                
                // Send notification to owner
                $notification_title = "Flat Listing Approved";
                $notification_message = "Your flat listing at {$owner_info['location']} has been approved and is now available for rent. Reference number: $flat_reference";
                
                $stmt = $pdo->prepare("INSERT INTO messages (user_id, title, message, sender_type, created_date) VALUES (?, ?, ?, 'system', NOW())");
                $stmt->execute([getOwnerIdByFlatId($pdo, $flat_id), $notification_title, $notification_message]);
                
                $message = "Flat listing has been approved successfully. Reference number: $flat_reference";
                
            } elseif ($action === 'reject') {
                $rejection_reason = $_POST['rejection_reason'] ?? 'No reason provided';
                
                // Update flat status
                $stmt = $pdo->prepare("UPDATE flats SET status = 'rejected', rejected_by = ?, rejected_date = NOW(), rejection_reason = ? WHERE flat_id = ?");
                $stmt->execute([$manager_id, $rejection_reason, $flat_id]);
                
                // Get owner details for notification
                $stmt = $pdo->prepare("SELECT u.user_id, u.name, u.email, f.location 
                                     FROM flats f 
                                     JOIN owners o ON f.owner_id = o.owner_id 
                                     JOIN users u ON o.user_id = u.user_id 
                                     WHERE f.flat_id = ?");
                $stmt->execute([$flat_id]);
                $owner_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($owner_info) {
                    // Send notification to owner
                    $notification_title = "Flat Listing Rejected";
                    $notification_message = "Your flat listing at {$owner_info['location']} has been rejected. Reason: $rejection_reason";
                    
                    $stmt = $pdo->prepare("INSERT INTO messages (user_id, title, message, sender_type, created_date) VALUES (?, ?, ?, 'system', NOW())");
                    $stmt->execute([$owner_info['user_id'], $notification_title, $notification_message]);
                    
                    $message = "Flat listing has been rejected successfully.";
                } else {
                    throw new Exception("Could not find owner information for flat ID: $flat_id");
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred while processing the request: " . $e->getMessage();
        }
    }
}

// Get sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_date';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';
$next_order = $sort_order === 'asc' ? 'desc' : 'asc';

// Valid columns for sorting
$valid_columns = ['location', 'monthly_cost', 'bedrooms', 'created_date', 'owner_name'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'created_date';
}

// Get all pending flats
$query = "SELECT f.*, u.name as owner_name, u.email as owner_email, u.mobile_number as owner_phone,
          f.created_date as submission_date
          FROM flats f 
          JOIN owners o ON f.owner_id = o.owner_id
          JOIN users u ON o.user_id = u.user_id 
          WHERE f.status = 'pending_approval' 
          ORDER BY f.$sort_column $sort_order";

try {
    // Debug output
    error_log("Executing pending flats query: " . $query);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $pending_flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug output
    error_log("Found " . count($pending_flats) . " pending flats");
    
    if (count($pending_flats) === 0) {
        // Check all flats
        $all_flats_query = "SELECT flat_id, owner_id, status FROM flats";
        $all_flats_stmt = $pdo->query($all_flats_query);
        $all_flats = $all_flats_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("All flats in database: " . json_encode($all_flats));
        
        // Check all flat statuses
        $check_query = "SELECT status, COUNT(*) as count FROM flats GROUP BY status";
        $check_stmt = $pdo->query($check_query);
        $status_counts = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Status counts: " . json_encode($status_counts));
        
        // Check owner_id values
        $owner_query = "SELECT o.owner_id, o.user_id, u.name, u.user_type 
                       FROM owners o 
                       JOIN users u ON o.user_id = u.user_id";
        $owner_stmt = $pdo->query($owner_query);
        $owner_info = $owner_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Owner information: " . json_encode($owner_info));
    }
} catch (PDOException $e) {
    error_log("Database error in pending-approvals.php: " . $e->getMessage());
    error_log("Query that failed: " . $query);
    $error = "An error occurred while fetching pending flats.";
    $pending_flats = [];
}

// Get manager information
$stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_info = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Pending Approvals";

// Helper function to get owner user_id by flat ID
function getOwnerIdByFlatId($pdo, $flat_id) {
    $stmt = $pdo->prepare("SELECT u.user_id 
                          FROM flats f 
                          JOIN owners o ON f.owner_id = o.owner_id 
                          JOIN users u ON o.user_id = u.user_id 
                          WHERE f.flat_id = ?");
    $stmt->execute([$flat_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['user_id'] : null;
}

// Handle modal display based on GET parameters
$show_reject_modal = isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['flat_id']);
$show_owner_modal = isset($_GET['action']) && $_GET['action'] === 'view_owner' && isset($_GET['flat_id']);

// Handle reject form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_flat'])) {
    $flat_id = $_POST['flat_id'];
    $rejection_reason = $_POST['rejection_reason'];
    
    // Get owner's user_id
    $owner_sql = "SELECT u.user_id, f.location, f.address 
                  FROM users u 
                  JOIN owners o ON u.user_id = o.user_id 
                  JOIN flats f ON o.owner_id = f.owner_id 
                  WHERE f.flat_id = :flat_id";
    $owner_stmt = $pdo->prepare($owner_sql);
    $owner_stmt->execute([':flat_id' => $flat_id]);
    $owner_info = $owner_stmt->fetch();
    
    // Update flat status to rejected
    $update_sql = "UPDATE flats SET status = 'rejected', rejected_by = :manager_id, rejected_date = NOW(), rejection_reason = :reason WHERE flat_id = :flat_id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':manager_id' => $_SESSION['manager_id'],
        ':reason' => $rejection_reason,
        ':flat_id' => $flat_id
    ]);
    
    // Send rejection message to owner
    $message_sql = "INSERT INTO messages (user_id, sender_id, sender_type, title, message, is_read) 
                    VALUES (:user_id, :sender_id, 'manager', :title, :message, 0)";
    $message_stmt = $pdo->prepare($message_sql);
    $message_stmt->execute([
        ':user_id' => $owner_info['user_id'],
        ':sender_id' => $_SESSION['user_id'],
        ':title' => 'Flat Listing Rejected',
        ':message' => "Your flat listing in {$owner_info['location']} ({$owner_info['address']}) has been rejected.\n\nReason: {$rejection_reason}\n\nYou can submit a new listing with the required changes."
    ]);
    
    // Redirect to remove GET parameters
    header('Location: main.php?page=pending-approvals');
    exit;
}

// Handle approve form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_flat'])) {
    $flat_id = $_POST['flat_id'];
    
    // Get owner's user_id
    $owner_sql = "SELECT u.user_id, f.location, f.address 
                  FROM users u 
                  JOIN owners o ON u.user_id = o.user_id 
                  JOIN flats f ON o.owner_id = f.owner_id 
                  WHERE f.flat_id = :flat_id";
    $owner_stmt = $pdo->prepare($owner_sql);
    $owner_stmt->execute([':flat_id' => $flat_id]);
    $owner_info = $owner_stmt->fetch();
    
    // Update flat status to approved
    $update_sql = "UPDATE flats SET status = 'approved', approved_by = :manager_id, approved_date = NOW() WHERE flat_id = :flat_id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':manager_id' => $_SESSION['manager_id'],
        ':flat_id' => $flat_id
    ]);
    
    // Send approval message to owner
    $message_sql = "INSERT INTO messages (user_id, sender_id, sender_type, title, message, is_read) 
                    VALUES (:user_id, :sender_id, 'manager', :title, :message, 0)";
    $message_stmt = $pdo->prepare($message_sql);
    $message_stmt->execute([
        ':user_id' => $owner_info['user_id'],
        ':sender_id' => $_SESSION['user_id'],
        ':title' => 'Flat Listing Approved',
        ':message' => "Your flat listing in {$owner_info['location']} ({$owner_info['address']}) has been approved and is now visible to potential tenants."
    ]);
    
    // Redirect to remove any GET parameters
    header('Location: main.php?page=pending-approvals');
    exit;
}

// Get owner info if showing owner modal
$owner_info = null;
if ($show_owner_modal) {
    $flat_id = $_GET['flat_id'];
    $owner_sql = "SELECT u.name, u.email, u.mobile_number 
                  FROM users u 
                  JOIN owners o ON u.user_id = o.user_id 
                  JOIN flats f ON o.owner_id = f.owner_id 
                  WHERE f.flat_id = :flat_id";
    $owner_stmt = $pdo->prepare($owner_sql);
    $owner_stmt->execute([':flat_id' => $flat_id]);
    $owner_info = $owner_stmt->fetch();
}
?>

<div class="pending-approvals-page">
    <div class="page-header">
        <h1>Pending Approvals</h1>
        <p>Review and manage pending flat approvals</p>
    </div>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="stats-bar">
        <div class="stat">
            <span class="number"><?php echo count($pending_flats); ?></span>
            <span class="label">Pending Approvals</span>
        </div>
        <div class="stat">
            <?php
            $total_this_week = 0;
            foreach ($pending_flats as $flat) {
                if (strtotime($flat['created_date']) > strtotime('-1 week')) {
                    $total_this_week++;
                }
            }
            ?>
            <span class="number"><?php echo $total_this_week; ?></span>
            <span class="label">This Week</span>
        </div>
    </div>

    <?php if (empty($pending_flats)): ?>
        <div class="no-results">
            <h3>No Pending Approvals</h3>
            <p>All flat listings have been reviewed. Check back later for new submissions.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>
                            <a href="?sort=location&order=<?php echo $sort_column === 'location' ? $next_order : 'asc'; ?>">
                                Location
                                <?php if ($sort_column === 'location'): ?>
                                    <span class="sort-icon"><?php echo $sort_order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=monthly_cost&order=<?php echo $sort_column === 'monthly_cost' ? $next_order : 'asc'; ?>">
                                Monthly Cost
                                <?php if ($sort_column === 'monthly_cost'): ?>
                                    <span class="sort-icon"><?php echo $sort_order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=bedrooms&order=<?php echo $sort_column === 'bedrooms' ? $next_order : 'asc'; ?>">
                                Details
                                <?php if ($sort_column === 'bedrooms'): ?>
                                    <span class="sort-icon"><?php echo $sort_order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=owner_name&order=<?php echo $sort_column === 'owner_name' ? $next_order : 'asc'; ?>">
                                Owner
                                <?php if ($sort_column === 'owner_name'): ?>
                                    <span class="sort-icon"><?php echo $sort_order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=created_date&order=<?php echo $sort_column === 'created_date' ? $next_order : 'asc'; ?>">
                                Submitted
                                <?php if ($sort_column === 'created_date'): ?>
                                    <span class="sort-icon"><?php echo $sort_order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_flats as $index => $flat): ?>
                        <tr class="<?php echo $index % 2 === 0 ? 'even' : 'odd'; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($flat['location']); ?></strong><br>
                                <small><?php echo htmlspecialchars($flat['address']); ?></small>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($flat['monthly_cost'], 2); ?></strong>
                            </td>
                            <td>
                                <?php echo $flat['bedrooms']; ?> bed, <?php echo $flat['bathrooms']; ?> bath<br>
                                <small><?php echo $flat['size_sqm']; ?> m² • <?php echo $flat['furnished'] ? 'Furnished' : 'Unfurnished'; ?></small>
                            </td>
                            <td>
                                <a href="#" onclick="showOwnerCard('<?php echo $flat['owner_name']; ?>', '<?php echo $flat['owner_email']; ?>', '<?php echo $flat['owner_phone']; ?>')" class="owner-link">
                                    <?php echo htmlspecialchars($flat['owner_name']); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $days_ago = floor((time() - strtotime($flat['created_date'])) / (60 * 60 * 24));
                                if ($days_ago == 0) {
                                    echo "Today";
                                } elseif ($days_ago == 1) {
                                    echo "Yesterday";
                                } else {
                                    echo "$days_ago days ago";
                                }
                                ?><br>
                                <small><?php echo date('M j, Y', strtotime($flat['created_date'])); ?></small>
                            </td>
                            <td class="actions">
                                <a href="main.php?page=flat-detail&id=<?php echo $flat['flat_id']; ?>" class="btn btn-sm btn-view" target="_blank">Review</a>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this listing?');">
                                    <input type="hidden" name="flat_id" value="<?php echo $flat['flat_id']; ?>">
                                    <button type="submit" name="approve_flat" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                
                                <a href="main.php?page=pending-approvals&action=view_owner&flat_id=<?php echo $flat['flat_id']; ?>" class="btn btn-info">View Owner</a>
                                <a href="main.php?page=pending-approvals&action=reject&flat_id=<?php echo $flat['flat_id']; ?>" class="btn btn-danger">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<?php if ($show_reject_modal): ?>
<div class="modal" id="reject-modal" style="display: block;">
    <div class="modal-content">
        <span class="close" onclick="window.location.href='main.php?page=pending-approvals'">&times;</span>
        <h2>Reject Flat Listing</h2>
        <form method="POST" action="main.php?page=pending-approvals">
            <input type="hidden" name="flat_id" value="<?php echo htmlspecialchars($_GET['flat_id']); ?>">
            <div class="form-group">
                <label for="rejection_reason">Reason for Rejection:</label>
                <textarea name="rejection_reason" id="rejection_reason" required></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='main.php?page=pending-approvals'">Cancel</button>
                <button type="submit" name="reject_flat" class="btn btn-danger">Reject Listing</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Owner Info Modal -->
<?php if ($show_owner_modal && $owner_info): ?>
<div class="modal" id="owner-modal" style="display: block;">
    <div class="modal-content">
        <span class="close" onclick="window.location.href='main.php?page=pending-approvals'">&times;</span>
        <h2>Owner Information</h2>
        <div class="owner-info">
            <p><strong>Name:</strong> <span id="owner-card-name"><?php echo htmlspecialchars($owner_info['name']); ?></span></p>
            <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($owner_info['email']); ?>" id="owner-card-email"><?php echo htmlspecialchars($owner_info['email']); ?></a></p>
            <p><strong>Phone:</strong> <span id="owner-card-phone"><?php echo htmlspecialchars($owner_info['mobile_number']); ?></span></p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-primary" onclick="window.location.href='main.php?page=pending-approvals'">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>