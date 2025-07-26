<?php
// Preview appointment page - Book flat viewing appointments
requireUserType('customer');

$flat_id = isset($_GET['flat_id']) ? intval($_GET['flat_id']) : 0;

if (!$flat_id) {
    header('Location: main.php?page=search');
    exit;
}

// Get flat details
$flat = getFlatDetails($pdo, $flat_id);
if (!$flat) {
    header('Location: main.php?page=search');
    exit;
}

$customer_id = $_SESSION['customer_id'];
$message = '';
$error = '';

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $timetable_id = intval($_POST['timetable_id']);
    
    // Verify the appointment is still available
    $check_sql = "SELECT * FROM preview_timetables 
                  WHERE timetable_id = :timetable_id 
                  AND flat_id = :flat_id 
                  AND is_booked = 0 
                  AND available_date >= CURDATE()";
    
    $check_stmt = executeQuery($pdo, $check_sql, [
        ':timetable_id' => $timetable_id,
        ':flat_id' => $flat_id
    ]);
    
    $appointment = $check_stmt->fetch();
    
    if ($appointment) {
        try {
            beginTransaction($pdo);
            
            // Book the appointment
            if (bookPreviewAppointment($pdo, $timetable_id, $customer_id)) {
                // Send message to customer
                $customer_message = "Your preview appointment for flat {$flat['flat_reference']} has been requested for " . 
                                  formatDate($appointment['available_date']) . " at " . 
                                  date('H:i', strtotime($appointment['available_time'])) . 
                                  ". The owner will contact you to confirm. Contact: {$appointment['contact_number']}";
                
                sendMessage($pdo, $_SESSION['user_id'], 'Appointment Requested', $customer_message);
                
                // Send message to owner
                $owner_sql = "SELECT user_id FROM owners WHERE owner_id = :owner_id";
                $owner_stmt = executeQuery($pdo, $owner_sql, [':owner_id' => $flat['owner_id']]);
                $owner = $owner_stmt->fetch();
                
                if ($owner) {
                    $owner_message = "A preview appointment has been requested for your flat {$flat['flat_reference']} " .
                                   "on " . formatDate($appointment['available_date']) . " at " . 
                                   date('H:i', strtotime($appointment['available_time'])) . 
                                   ". Customer: {$_SESSION['name']} ({$_SESSION['email']})";
                    
                    sendMessage($pdo, $owner['user_id'], 'Preview Appointment Request', $owner_message);
                }
                
                commitTransaction($pdo);
                $message = 'Appointment requested successfully! The owner will contact you for confirmation.';
            } else {
                rollbackTransaction($pdo);
                $error = 'This appointment slot is no longer available.';
            }
        } catch (Exception $e) {
            rollbackTransaction($pdo);
            $error = 'Failed to book appointment. Please try again.';
        }
    } else {
        $error = 'This appointment slot is no longer available.';
    }
}

// Get available appointments
$appointments = getAvailableAppointments($pdo, $flat_id);

// Group appointments by date
$grouped_appointments = [];
foreach ($appointments as $appointment) {
    $date = $appointment['available_date'];
    if (!isset($grouped_appointments[$date])) {
        $grouped_appointments[$date] = [];
    }
    $grouped_appointments[$date][] = $appointment;
}

// Check if customer has existing bookings for this flat
$existing_booking_sql = "SELECT pt.*, DATE(pt.available_date) as booking_date, 
                                TIME(pt.available_time) as booking_time
                         FROM preview_timetables pt 
                         WHERE pt.flat_id = :flat_id 
                         AND pt.booked_by = :customer_id 
                         AND pt.available_date >= CURDATE()
                         ORDER BY pt.available_date ASC, pt.available_time ASC";

$existing_bookings = executeQuery($pdo, $existing_booking_sql, [
    ':flat_id' => $flat_id,
    ':customer_id' => $customer_id
])->fetchAll();
?>

<div class="preview-appointment-page">
    <div class="page-header">
        <h1>📅 Book Preview Appointment</h1>
        <p>Schedule a viewing for Flat <?php echo htmlspecialchars($flat['flat_reference']); ?></p>
    </div>

    <!-- Flat Summary -->
    <div class="flat-summary">
        <div class="summary-content">
            <div class="flat-info">
                <h3>🏠 Flat Details</h3>
                <div class="info-grid">
                    <div><strong>Reference:</strong> <?php echo htmlspecialchars($flat['flat_reference']); ?></div>
                    <div><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></div>
                    <div><strong>Monthly Rent:</strong> <?php echo formatCurrency($flat['monthly_cost']); ?></div>
                    <div><strong>Bedrooms:</strong> <?php echo $flat['bedrooms']; ?></div>
                    <div><strong>Bathrooms:</strong> <?php echo $flat['bathrooms']; ?></div>
                    <div><strong>Size:</strong> <?php echo $flat['size_sqm']; ?> m²</div>
                </div>
            </div>
            
            <div class="owner-info">
                <h3>👤 Owner Contact</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
                <p><strong>Mobile:</strong> <?php echo htmlspecialchars($flat['owner_mobile']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($flat['owner_email']); ?></p>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="success-message">
            <h3>✅ Success!</h3>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <h3>⚠️ Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- Existing Bookings -->
    <?php if (!empty($existing_bookings)): ?>
        <div class="existing-bookings">
            <h2>📋 Your Existing Appointments</h2>
            <p>You have already booked the following appointments for this flat:</p>
            
            <div class="bookings-list">
                <?php foreach ($existing_bookings as $booking): ?>
                    <div class="booking-item">
                        <div class="booking-details">
                            <span class="booking-date">📅 <?php echo formatDate($booking['booking_date']); ?></span>
                            <span class="booking-time">🕐 <?php echo date('H:i', strtotime($booking['booking_time'])); ?></span>
                            <span class="booking-contact">📞 <?php echo htmlspecialchars($booking['contact_number']); ?></span>
                        </div>
                        <span class="booking-status confirmed">✅ Requested</span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="booking-note">
                <p>💡 <strong>Note:</strong> The owner will contact you to confirm your appointment(s). 
                   You can still book additional time slots if available.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Available Appointments -->
    <div class="appointments-section">
        <h2>📅 Available Appointment Slots</h2>
        
        <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <h3>😔 No Available Appointments</h3>
                <p>Unfortunately, there are no available appointment slots for this flat at the moment.</p>
                
                <div class="no-appointments-actions">
                    <h4>What you can do:</h4>
                    <ul>
                        <li>📞 <strong>Contact the owner directly:</strong> <?php echo htmlspecialchars($flat['owner_mobile']); ?></li>
                        <li>✉️ <strong>Send an email:</strong> <a href="mailto:<?php echo htmlspecialchars($flat['owner_email']); ?>"><?php echo htmlspecialchars($flat['owner_email']); ?></a></li>
                        <li>🔄 <strong>Check back later</strong> for newly added time slots</li>
                        <li>🏠 <strong>Rent directly</strong> if you don't need a preview</li>
                    </ul>
                </div>
                
                <div class="alternative-actions">
                    <a href="main.php?page=rent-flat&flat_id=<?php echo $flat_id; ?>" class="btn btn-success">
                        🏠 Rent Without Preview
                    </a>
                    <a href="main.php?page=flat-detail&id=<?php echo $flat_id; ?>" class="btn btn-primary">
                        👁️ View Flat Details
                    </a>
                    <a href="main.php?page=search" class="btn btn-secondary">
                        🔍 Search Other Flats
                    </a>
                </div>
            </div>
        <?php else: ?>
            
            <div class="appointment-instructions">
                <h3>📋 Booking Instructions</h3>
                <div class="instructions-grid">
                    <div class="instruction-item">
                        <span class="instruction-icon">1️⃣</span>
                        <div>
                            <strong>Choose a Time Slot</strong>
                            <p>Select from available dates and times below</p>
                        </div>
                    </div>
                    <div class="instruction-item">
                        <span class="instruction-icon">2️⃣</span>
                        <div>
                            <strong>Book Appointment</strong>
                            <p>Click "Book Appointment" to request the slot</p>
                        </div>
                    </div>
                    <div class="instruction-item">
                        <span class="instruction-icon">3️⃣</span>
                        <div>
                            <strong>Wait for Confirmation</strong>
                            <p>The owner will contact you to confirm details</p>
                        </div>
                    </div>
                    <div class="instruction-item">
                        <span class="instruction-icon">4️⃣</span>
                        <div>
                            <strong>Visit the Property</strong>
                            <p>Meet the owner at the scheduled time</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="appointments-calendar">
                <?php foreach ($grouped_appointments as $date => $day_appointments): ?>
                    <div class="day-section">
                        <h3 class="day-header">
                            📅 <?php echo formatDate($date); ?>
                            <span class="day-name">(<?php echo date('l', strtotime($date)); ?>)</span>
                        </h3>
                        
                        <div class="time-slots">
                            <?php foreach ($day_appointments as $appointment): ?>
                                <div class="time-slot <?php echo $appointment['is_booked'] ? 'booked' : 'available'; ?>">
                                    <div class="slot-info">
                                        <span class="slot-time">
                                            🕐 <?php echo date('H:i', strtotime($appointment['available_time'])); ?>
                                        </span>
                                        <span class="slot-contact">
                                            📞 <?php echo htmlspecialchars($appointment['contact_number']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="slot-action">
                                        <?php if ($appointment['is_booked']): ?>
                                            <span class="slot-status booked">❌ Taken</span>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="timetable_id" value="<?php echo $appointment['timetable_id']; ?>">
                                                <button type="submit" name="book_appointment" 
                                                        class="btn btn-success btn-small"
                                                        onclick="return confirm('Book this appointment slot?')">
                                                    📅 Book Appointment
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <!-- Important Notes -->
    <div class="important-notes">
        <h3>⚠️ Important Notes</h3>
        <div class="notes-content">
            <div class="note-item">
                <span class="note-icon">📞</span>
                <div>
                    <strong>Confirmation Required</strong>
                    <p>All appointments require confirmation from the property owner. They will contact you within 24 hours.</p>
                </div>
            </div>
            
            <div class="note-item">
                <span class="note-icon">🕐</span>
                <div>
                    <strong>Be Punctual</strong>
                    <p>Please arrive on time for your appointment. Contact the owner if you need to reschedule.</p>
                </div>
            </div>
            
            <div class="note-item">
                <span class="note-icon">🆔</span>
                <div>
                    <strong>Bring ID</strong>
                    <p>Bring a valid photo ID and any questions you have about the property.</p>
                </div>
            </div>
            
            <div class="note-item">
                <span class="note-icon">💬</span>
                <div>
                    <strong>Check Messages</strong>
                    <p>Monitor your messages for updates and confirmation from the owner.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-grid">
            <a href="main.php?page=flat-detail&id=<?php echo $flat_id; ?>" class="action-card">
                <span class="action-icon">👁️</span>
                <h4>View Flat Details</h4>
                <p>See complete property information</p>
            </a>
            
            <a href="main.php?page=rent-flat&flat_id=<?php echo $flat_id; ?>" class="action-card">
                <span class="action-icon">🏠</span>
                <h4>Rent This Flat</h4>
                <p>Skip preview and rent directly</p>
            </a>
            
            <a href="main.php?page=messages" class="action-card">
                <span class="action-icon">💬</span>
                <h4>Check Messages</h4>
                <p>View appointment confirmations</p>
            </a>
            
            <a href="main.php?page=search" class="action-card">
                <span class="action-icon">🔍</span>
                <h4>Search More Flats</h4>
                <p>Browse other available properties</p>
            </a>
        </div>
    </div>
</div>