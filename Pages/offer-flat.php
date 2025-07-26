<?php
// Offer flat page - Multi-step flat listing for owners
requireUserType('owner');
require_once __DIR__ . '/../functions.php';

// Use user_id instead of owner_id for flat submission
$owner_sql = "SELECT owner_id FROM owners WHERE user_id = :user_id";
$owner_stmt = executeQuery($pdo, $owner_sql, [':user_id' => $_SESSION['user_id']]);
$owner = $owner_stmt->fetch();

if (!$owner) {
    die("Error: Owner not found. Please contact support.");
}

$owner_id = $owner['owner_id'];

$step = isset($_POST['step']) ? intval($_POST['step']) : 1;
$errors = [];
$flat_data = [];

// Initialize or get existing flat data from session
if (!isset($_SESSION['flat_data'])) {
    $_SESSION['flat_data'] = [];
}
$flat_data = $_SESSION['flat_data'];

// Add debug output
error_log("Offer flat page - User ID: " . $_SESSION['user_id'] . ", Owner ID: " . $owner_id);

function validateFlatDetails($data) {
    $errors = [];
    if (empty($data['location'])) {
        $errors['location'] = 'Location is required.';
    }
    if (empty($data['monthly_cost']) || !is_numeric($data['monthly_cost']) || $data['monthly_cost'] <= 0) {
        $errors['monthly_cost'] = 'Monthly rent must be a positive number.';
    }
    if (empty($data['address'])) {
        $errors['address'] = 'Address is required.';
    }
    if (empty($data['available_from'])) {
        $errors['available_from'] = 'Available From date is required.';
    }
    if (empty($data['available_to'])) {
        $errors['available_to'] = 'Available Until date is required.';
    }
    if (!empty($data['available_from']) && !empty($data['available_to']) && $data['available_to'] <= $data['available_from']) {
        $errors['available_to'] = 'Available Until date must be after Available From date.';
    }
    if (empty($data['bedrooms']) || !is_numeric($data['bedrooms']) || $data['bedrooms'] < 1) {
        $errors['bedrooms'] = 'Number of bedrooms must be at least 1.';
    }
    if (empty($data['bathrooms']) || !is_numeric($data['bathrooms']) || $data['bathrooms'] < 1) {
        $errors['bathrooms'] = 'Number of bathrooms must be at least 1.';
    }
    if (empty($data['size_sqm']) || !is_numeric($data['size_sqm']) || $data['size_sqm'] <= 0) {
        $errors['size_sqm'] = 'Size must be a positive number.';
    }
    if (empty($data['rental_conditions'])) {
        $errors['rental_conditions'] = 'Rental conditions are required.';
    }
    return $errors;
}

function handlePhotoUpload($files, $post) {
    $errors = [];
    if (!isset($files['flat_photos']) || empty($files['flat_photos']['name'][0])) {
        $errors['flat_photos'] = 'You must upload at least 3 photos.';
        return $errors;
    }
    $photo_count = count($files['flat_photos']['name']);
    if ($photo_count < 3) {
        $errors['flat_photos'] = 'You must upload at least 3 photos.';
    } elseif ($photo_count > 10) {
        $errors['flat_photos'] = 'You can upload a maximum of 10 photos.';
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    for ($i = 0; $i < $photo_count; $i++) {
        if ($files['flat_photos']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors['flat_photos'] = 'Error uploading photo ' . ($i + 1) . '.';
            break;
        }
        if (!in_array($files['flat_photos']['type'][$i], $allowed_types)) {
            $errors['flat_photos'] = 'Invalid file type for photo ' . ($i + 1) . '. Only JPG, PNG, and WebP are allowed.';
            break;
        }
        if ($files['flat_photos']['size'][$i] > 5 * 1024 * 1024) {
            $errors['flat_photos'] = 'Photo ' . ($i + 1) . ' exceeds the 5MB size limit.';
            break;
        }
    }
    return $errors;
}

function validateTimetables($data) {
    $errors = [];
    if (empty($data['timetable_dates']) || !is_array($data['timetable_dates'])) {
        $errors['timetables'] = 'At least one preview time slot is required.';
        return $errors;
    }
    $valid = false;
    foreach ($data['timetable_dates'] as $i => $date) {
        if (!empty($date) && !empty($data['timetable_times'][$i]) && !empty($data['timetable_contacts'][$i])) {
            $valid = true;
            if ($date < date('Y-m-d')) {
                $errors['timetables'] = 'Preview date must not be in the past.';
                break;
            }
            if (!preg_match('/^05[0-9]{8}$/', $data['timetable_contacts'][$i])) {
                $errors['timetables'] = 'Contact number must be a valid 10-digit Palestinian mobile number.';
                break;
            }
        }
    }
    if (!$valid) {
        $errors['timetables'] = 'At least one complete preview time slot is required.';
    }
    return $errors;
}

function processFlatSubmission($pdo, $flat_data, $owner_id) {
    $errors = [];
    // Basic validation: check all steps exist
    if (empty($flat_data['step1']) || empty($flat_data['step2']) || empty($flat_data['step3']) || empty($flat_data['step4'])) {
        $errors['general'] = 'Incomplete submission. Please complete all steps.';
        return $errors;
    }
    // Insert flat into database
    try {
        $stmt = $pdo->prepare("INSERT INTO flats (owner_id, location, monthly_cost, address, available_from, available_to, bedrooms, bathrooms, size_sqm, furnished, has_heating, has_air_conditioning, has_access_control, has_parking, has_playground, has_storage, has_backyard, rental_conditions, created_date) VALUES (:owner_id, :location, :monthly_cost, :address, :available_from, :available_to, :bedrooms, :bathrooms, :size_sqm, :furnished, :has_heating, :has_air_conditioning, :has_access_control, :has_parking, :has_playground, :has_storage, :has_backyard, :rental_conditions, NOW())");
        $step1 = $flat_data['step1'];
        $stmt->execute([
            ':owner_id' => $owner_id,
            ':location' => $step1['location'],
            ':monthly_cost' => $step1['monthly_cost'],
            ':address' => $step1['address'],
            ':available_from' => $step1['available_from'],
            ':available_to' => $step1['available_to'],
            ':bedrooms' => $step1['bedrooms'],
            ':bathrooms' => $step1['bathrooms'],
            ':size_sqm' => $step1['size_sqm'],
            ':furnished' => isset($step1['furnished']) ? 1 : 0,
            ':has_heating' => isset($step1['has_heating']) ? 1 : 0,
            ':has_air_conditioning' => isset($step1['has_air_conditioning']) ? 1 : 0,
            ':has_access_control' => isset($step1['has_access_control']) ? 1 : 0,
            ':has_parking' => isset($step1['has_parking']) ? 1 : 0,
            ':has_playground' => isset($step1['has_playground']) ? 1 : 0,
            ':has_storage' => isset($step1['has_storage']) ? 1 : 0,
            ':has_backyard' => $step1['has_backyard'] ?? 'none',
            ':rental_conditions' => $step1['rental_conditions'],
        ]);
        $flat_id = $pdo->lastInsertId();
        // Handle photo uploads
        if (isset($_FILES['flat_photos']) && !empty($_FILES['flat_photos']['name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $photo_count = count($_FILES['flat_photos']['name']);
            for ($i = 0; $i < $photo_count; $i++) {
                $file_name = $_FILES['flat_photos']['name'][$i];
                $file_tmp = $_FILES['flat_photos']['tmp_name'][$i];
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $is_main_photo = ($i === 0) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO flat_photos (flat_id, photo_path, is_main_photo) VALUES (:flat_id, :photo_path, :is_main_photo)");
                    $stmt->execute([
                        ':flat_id' => $flat_id,
                        ':photo_path' => 'uploads/' . $file_name,
                        ':is_main_photo' => $is_main_photo,
                    ]);
                } else {
                    $errors['photos'] = 'Failed to upload photo ' . ($i + 1);
                }
            }
        }
    } catch (Exception $e) {
        $errors['db'] = 'Database error: ' . $e->getMessage();
    }
    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Step 1: Basic flat details
            $errors = validateFlatDetails($_POST);
            if (empty($errors)) {
                $_SESSION['flat_data']['step1'] = $_POST;
                $step = 2;
            }
            break;
            
        case 2:
            // Step 2: Photo Upload
            $errors = handlePhotoUpload($_FILES, $_POST);
            if (empty($errors)) {
                $_SESSION['flat_data']['step2'] = $_POST;
                $step = 3;
            }
            break;
            
        case 3:
            // Step 3: Marketing information (optional)
            $_SESSION['flat_data']['step3'] = $_POST;
            $step = 4;
            break;
            
        case 4:
            // Step 4: Preview timetables
            $errors = validateTimetables($_POST);
            if (empty($errors)) {
                $_SESSION['flat_data']['step4'] = [
                    'timetable_dates' => $_POST['timetable_dates'],
                    'timetable_times' => $_POST['timetable_times'],
                    'timetable_contacts' => $_POST['timetable_contacts']
                ];
                $step = 5;
            }
            break;
            
        case 5:
            // Step 5: Final submission
            // Check if we have valid timetable data in session
            if (!isset($_SESSION['flat_data']['step4']) || 
                !isset($_SESSION['flat_data']['step4']['timetable_dates']) || 
                empty($_SESSION['flat_data']['step4']['timetable_dates'])) {
                $errors['timetables'] = 'At least one valid preview time slot is required';
                $step = 4; // Go back to timetable step
            } else {
                $errors = processFlatSubmission($pdo, $_SESSION['flat_data'], $owner_id);
                if (empty($errors)) {
                    $step = 6; // Success page
                }
            }
            break;
            
        case 'back':
            $current_step = isset($_POST['current_step']) ? intval($_POST['current_step']) : 1;
            $step = max(1, $current_step - 1);
            break;
            
        case 'restart':
            unset($_SESSION['flat_data']);
            header('Location: main.php?page=offer-flat');
            exit;
            break;
    }
}

// Determine counts for marketing items and timetable slots
$marketing_count = isset($_POST['add_marketing']) ? (int)$_POST['marketing_count'] + 1 : (isset($_POST['remove_marketing_btn']) ? max(1, (int)$_POST['marketing_count'] - count($_POST['remove_marketing'] ?? [])) : (isset($flat_data['step3']['marketing_titles']) ? count($flat_data['step3']['marketing_titles']) : 1));
$timetable_count = isset($_POST['add_timetable']) ? (int)$_POST['timetable_count'] + 1 : (isset($_POST['remove_timetable_btn']) ? max(1, (int)$_POST['timetable_count'] - count($_POST['remove_timetable'] ?? [])) : (isset($flat_data['step4']['timetable_dates']) ? count($flat_data['step4']['timetable_dates']) : 1));
?>

<div class="offer-flat-page">
    <div class="page-header">
        <h1>➕ List Your Flat for Rent</h1>
        <p>Add your property to our rental platform and connect with potential tenants</p>
    </div>

    <!-- Progress Steps -->
    <div class="listing-steps">
        <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
            <span class="step-number">1</span>
            <span class="step-title">Property Details</span>
        </div>
        <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
            <span class="step-number">2</span>
            <span class="step-title">Upload Photos</span>
        </div>
        <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
            <span class="step-number">3</span>
            <span class="step-title">Marketing Info</span>
        </div>
        <div class="step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
            <span class="step-number">4</span>
            <span class="step-title">Preview Schedule</span>
        </div>
        <div class="step <?php echo $step >= 5 ? 'active' : ''; ?> <?php echo $step > 5 ? 'completed' : ''; ?>">
            <span class="step-number">5</span>
            <span class="step-title">Review & Submit</span>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-messages">
            <h3>Please correct the following errors:</h3>
            <ul>
                <?php foreach ($errors as $field => $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <!-- Step 1: Property Details -->
        <div class="listing-form">
            <h2>Step 1: Property Details</h2>
            <p>Provide comprehensive information about your flat to attract quality tenants.</p>
            
            <form method="POST" class="step-form">
                <input type="hidden" name="step" value="1">
                
                <!-- Location & Address -->
                <fieldset>
                    <legend>📍 Location & Address</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location" class="required">City/Area</label>
                            <input type="text" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($flat_data['step1']['location'] ?? ''); ?>" 
                                   placeholder="e.g., Ramallah, Al-Bireh" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="monthly_cost" class="required">Monthly Rent (₪)</label>
                            <input type="number" id="monthly_cost" name="monthly_cost" 
                                   value="<?php echo htmlspecialchars($flat_data['step1']['monthly_cost'] ?? ''); ?>" 
                                   min="1" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address" class="required">Complete Address</label>
                        <textarea id="address" name="address" rows="3" required 
                                  placeholder="Enter the full address including building number, street name, and any additional details"><?php echo htmlspecialchars($flat_data['step1']['address'] ?? ''); ?></textarea>
                    </div>
                </fieldset>
                
                <!-- Availability -->
                <fieldset>
                    <legend>📅 Availability Period</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="available_from" class="required">Available From</label>
                            <input type="date" id="available_from" name="available_from" 
                                   value="<?php echo htmlspecialchars($flat_data['step1']['available_from'] ?? ''); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="available_to" class="required">Available Until</label>
                            <input type="date" id="available_to" name="available_to" 
                                   value="<?php echo htmlspecialchars($flat_data['step1']['available_to'] ?? ''); ?>" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Property Specifications -->
                <fieldset>
                    <legend>🏠 Property Specifications</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bedrooms" class="required">Number of Bedrooms</label>
                            <select id="bedrooms" name="bedrooms" required>
                                <option value="">Select...</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" 
                                            <?php echo (isset($flat_data['step1']['bedrooms']) && $flat_data['step1']['bedrooms'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> bedroom<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bathrooms" class="required">Number of Bathrooms</label>
                            <select id="bathrooms" name="bathrooms" required>
                                <option value="">Select...</option>
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <option value="<?php echo $i; ?>" 
                                            <?php echo (isset($flat_data['step1']['bathrooms']) && $flat_data['step1']['bathrooms'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> bathroom<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="size_sqm" class="required">Size (square meters)</label>
                            <input type="number" id="size_sqm" name="size_sqm" 
                                   value="<?php echo htmlspecialchars($flat_data['step1']['size_sqm'] ?? ''); ?>" 
                                   min="1" step="0.1" required>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Amenities & Features -->
                <fieldset>
                    <legend>✨ Amenities & Features</legend>
                    <div class="amenities-grid">
                        <label class="checkbox-label">
                            <input type="checkbox" name="furnished" 
                                   <?php echo isset($flat_data['step1']['furnished']) ? 'checked' : ''; ?>>
                            <span class="checkmark">🪑</span>
                            Furnished
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="has_heating" 
                                   <?php echo isset($flat_data['step1']['has_heating']) ? 'checked' : ''; ?>>
                            <span class="checkmark">🔥</span>
                            Heating System
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="has_air_conditioning" 
                                   <?php echo isset($flat_data['step1']['has_air_conditioning']) ? 'checked' : ''; ?>>
                            <span class="checkmark">❄️</span>
                            Air Conditioning
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="has_access_control" 
                                   <?php echo isset($flat_data['step1']['has_access_control']) ? 'checked' : ''; ?>>
                            <span class="checkmark">🔒</span>
                            Access Control
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="has_parking" 
                                   <?php echo isset($flat_data['step1']['has_parking']) ? 'checked' : ''; ?>>
                            <span class="checkmark">🚗</span>
                            Parking Available
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="has_playground" 
                                   <?php echo isset($flat_data['step1']['has_playground']) ? 'checked' : ''; ?>>
                            <span class="checkmark">🎮</span>
                            Playground
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="has_storage" 
                                   <?php echo isset($flat_data['step1']['has_storage']) ? 'checked' : ''; ?>>
                            <span class="checkmark">📦</span>
                            Storage Space
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="has_backyard">Backyard</label>
                        <select id="has_backyard" name="has_backyard">
                            <option value="none" <?php echo (isset($flat_data['step1']['has_backyard']) && $flat_data['step1']['has_backyard'] === 'none') ? 'selected' : ''; ?>>No Backyard</option>
                            <option value="individual" <?php echo (isset($flat_data['step1']['has_backyard']) && $flat_data['step1']['has_backyard'] === 'individual') ? 'selected' : ''; ?>>Individual Backyard</option>
                            <option value="shared" <?php echo (isset($flat_data['step1']['has_backyard']) && $flat_data['step1']['has_backyard'] === 'shared') ? 'selected' : ''; ?>>Shared Backyard</option>
                        </select>
                    </div>
                </fieldset>
                
                <!-- Rental Conditions -->
                <fieldset>
                    <legend>📋 Rental Conditions</legend>
                    <div class="form-group">
                        <label for="rental_conditions" class="required">Rental Terms & Conditions</label>
                        <textarea id="rental_conditions" name="rental_conditions" rows="5" required 
                                  placeholder="Describe your rental terms, policies, and any special conditions (e.g., no pets, no smoking, security deposit requirements, etc.)"><?php echo htmlspecialchars($flat_data['step1']['rental_conditions'] ?? ''); ?></textarea>
                    </div>
                </fieldset>
                
                <div class="form-actions">
                    <a href="main.php?page=my-flats" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Next: Upload Photos →</button>
                </div>
            </form>
        </div>
        
    <?php elseif ($step === 2): ?>
        <!-- Step 2: Photo Upload -->
        <div class="listing-form">
            <h2>Step 2: Upload Photos</h2>
            <p>Upload at least 3 high-quality photos of your flat. The first photo will be the main photo shown in listings.</p>
            
            <form method="POST" enctype="multipart/form-data" class="step-form">
                <input type="hidden" name="step" value="2">
                
                <fieldset>
                    <legend>📸 Flat Photos</legend>
                    <div class="form-group">
                        <label for="flat_photos" class="required">Upload Photos (Min: 3, Max: 10)</label>
                        <input type="file" id="flat_photos" name="flat_photos[]" accept="image/jpeg,image/png,image/webp" 
                               multiple required onchange="previewPhotos(this)">
                        <small class="form-text">
                            Accepted formats: JPG, PNG, WebP. Maximum size: 5MB per photo.
                        </small>
                    </div>
                    
                    <div id="photo-preview" class="photo-preview-grid"></div>
                    
                    <div class="form-group">
                        <label>Select Main Photo</label>
                        <div id="main-photo-selection" class="main-photo-selection"></div>
                    </div>
                </fieldset>
                
                <div class="form-actions">
                    <button type="submit" name="step" value="back" class="btn btn-secondary">← Back</button>
                    <button type="submit" class="btn btn-primary">Next: Marketing Info →</button>
                </div>
            </form>
        </div>
        
    <?php elseif ($step === 3): ?>
        <!-- Step 3: Marketing Information -->
        <div class="listing-form">
            <h2>Step 3: Marketing Information</h2>
            <p>Add marketing information to attract more tenants.</p>
            
            <form method="POST" class="step-form">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="marketing_count" value="<?php echo $marketing_count; ?>">
                <fieldset>
                    <legend>🗺️ Marketing Information</legend>
                    <div id="marketing-items">
                        <?php for ($i = 0; $i < $marketing_count; $i++): ?>
                            <div class="marketing-item">
                                <h4>Item <?php echo $i + 1; ?></h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="marketing_titles_<?php echo $i; ?>">Title</label>
                                        <input type="text" id="marketing_titles_<?php echo $i; ?>" name="marketing_titles[]" value="<?php echo htmlspecialchars($_POST['marketing_titles'][$i] ?? $flat_data['step3']['marketing_titles'][$i] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="marketing_urls_<?php echo $i; ?>">Website URL (Optional)</label>
                                        <input type="url" id="marketing_urls_<?php echo $i; ?>" name="marketing_urls[]" value="<?php echo htmlspecialchars($_POST['marketing_urls'][$i] ?? $flat_data['step3']['marketing_urls'][$i] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="marketing_descriptions_<?php echo $i; ?>">Description</label>
                                    <textarea id="marketing_descriptions_<?php echo $i; ?>" name="marketing_descriptions[]" rows="2"><?php echo htmlspecialchars($_POST['marketing_descriptions'][$i] ?? $flat_data['step3']['marketing_descriptions'][$i] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label><input type="checkbox" name="remove_marketing[]" value="<?php echo $i; ?>"> Remove this item</label>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_marketing" class="btn btn-secondary">+ Add Marketing Item</button>
                        <button type="submit" name="remove_marketing_btn" class="btn btn-danger">Remove Selected</button>
                    </div>
                </fieldset>
                <div class="form-actions">
                    <button type="submit" name="step" value="back" class="btn btn-secondary">← Back</button>
                    <button type="submit" class="btn btn-primary">Next: Preview Schedule →</button>
                </div>
            </form>
        </div>
        
    <?php elseif ($step === 4): ?>
        <!-- Step 4: Preview Schedule -->
        <div class="listing-form">
            <h2>Step 4: Preview Schedule</h2>
            <p>Set up your preview schedule to attract potential tenants. Add at least one time slot when you'll be available to show the flat.</p>
            
            <form method="POST" class="step-form" id="previewForm">
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="timetable_count" value="<?php echo $timetable_count; ?>">
                <div id="timetable-slots">
                    <?php for ($i = 0; $i < $timetable_count; $i++): ?>
                        <fieldset class="timetable-slot">
                            <legend>📅 Preview Time Slot <?php echo $i + 1; ?></legend>
                            <div class="form-group">
                                <label for="timetable_dates_<?php echo $i; ?>" class="required">Available Date</label>
                                <input type="date" id="timetable_dates_<?php echo $i; ?>" name="timetable_dates[]" value="<?php echo htmlspecialchars($_POST['timetable_dates'][$i] ?? $flat_data['step4']['timetable_dates'][$i] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="timetable_times_<?php echo $i; ?>" class="required">Available Time</label>
                                <input type="time" id="timetable_times_<?php echo $i; ?>" name="timetable_times[]" value="<?php echo htmlspecialchars($_POST['timetable_times'][$i] ?? $flat_data['step4']['timetable_times'][$i] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="timetable_contacts_<?php echo $i; ?>" class="required">Contact Number</label>
                                <input type="tel" id="timetable_contacts_<?php echo $i; ?>" name="timetable_contacts[]" value="<?php echo htmlspecialchars($_POST['timetable_contacts'][$i] ?? $flat_data['step4']['timetable_contacts'][$i] ?? ''); ?>" placeholder="05XXXXXXXX" pattern="[0-9]{10}" required>
                                <small class="form-text">Enter a 10-digit phone number</small>
                            </div>
                            <div class="form-group">
                                <label><input type="checkbox" name="remove_timetable[]" value="<?php echo $i; ?>"> Remove this slot</label>
                            </div>
                        </fieldset>
                    <?php endfor; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_timetable" class="btn btn-secondary">+ Add Timetable Slot</button>
                    <button type="submit" name="remove_timetable_btn" class="btn btn-danger">Remove Selected</button>
                </div>
                <div class="form-actions">
                    <button type="submit" name="step" value="back" class="btn btn-secondary">← Back</button>
                    <button type="submit" class="btn btn-primary">Next: Review & Submit →</button>
                </div>
            </form>
        </div>
        
    <?php elseif ($step === 5): ?>
        <!-- Step 5: Review & Submit -->
        <div class="listing-form">
            <h2>Step 5: Review & Submit</h2>
            <p>Please review all information before submitting your flat listing for approval.</p>
            
            <div class="review-sections">
                <!-- Property Details Review -->
                <div class="review-section">
                    <h3>🏠 Property Details</h3>
                    <div class="review-grid">
                        <div class="review-item">
                            <strong>Location:</strong>
                            <span><?php echo htmlspecialchars($flat_data['step1']['location']); ?></span>
                        </div>
                        <div class="review-item">
                            <strong>Address:</strong>
                            <span><?php echo htmlspecialchars($flat_data['step1']['address']); ?></span>
                        </div>
                        <div class="review-item">
                            <strong>Monthly Rent:</strong>
                            <span><?php echo formatCurrency($flat_data['step1']['monthly_cost']); ?></span>
                        </div>
                        <div class="review-item">
                            <strong>Available Period:</strong>
                            <span><?php echo formatDate($flat_data['step1']['available_from']) . ' to ' . formatDate($flat_data['step1']['available_to']); ?></span>
                        </div>
                        <div class="review-item">
                            <strong>Bedrooms:</strong>
                            <span><?php echo $flat_data['step1']['bedrooms']; ?></span>
                        </div>
                        <div class="review-item">
                            <strong>Bathrooms:</strong>
                            <span><?php echo $flat_data['step1']['bathrooms']; ?></span>
                        </div>
                        <div class="review-item">
                            <strong>Size:</strong>
                            <span><?php echo $flat_data['step1']['size_sqm']; ?> m²</span>
                        </div>
                    </div>
                    
                    <!-- Amenities -->
                    <div class="amenities-review">
                        <strong>Amenities:</strong>
                        <div class="amenities-list">
                            <?php if (isset($flat_data['step1']['furnished'])): ?><span class="amenity-tag">🪑 Furnished</span><?php endif; ?>
                            <?php if (isset($flat_data['step1']['has_heating'])): ?><span class="amenity-tag">🔥 Heating</span><?php endif; ?>
                            <?php if (isset($flat_data['step1']['has_air_conditioning'])): ?><span class="amenity-tag">❄️ AC</span><?php endif; ?>
                            <?php if (isset($flat_data['step1']['has_access_control'])): ?><span class="amenity-tag">🔒 Access Control</span><?php endif; ?>
                            <?php if (isset($flat_data['step1']['has_parking'])): ?><span class="amenity-tag">🚗 Parking</span><?php endif; ?>
                            <?php if ($flat_data['step1']['has_backyard'] !== 'none'): ?><span class="amenity-tag">🌳 <?php echo ucfirst($flat_data['step1']['has_backyard']); ?> Backyard</span><?php endif; ?>
                            <?php if (isset($flat_data['step1']['has_playground'])): ?><span class="amenity-tag">🎮 Playground</span><?php endif; ?>
                            <?php if (isset($flat_data['step1']['has_storage'])): ?><span class="amenity-tag">📦 Storage</span><?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="review-item">
                        <strong>Rental Conditions:</strong>
                        <div class="conditions-text"><?php echo nl2br(htmlspecialchars($flat_data['step1']['rental_conditions'])); ?></div>
                    </div>
                </div>
                
                <!-- Marketing Information Review -->
                <?php if (!empty($flat_data['step3']['marketing_titles'][0])): ?>
                    <div class="review-section">
                        <h3>🗺️ Marketing Information</h3>
                        <?php foreach ($flat_data['step3']['marketing_titles'] as $i => $title): ?>
                            <?php if (!empty($title)): ?>
                                <div class="marketing-review-item">
                                    <h4><?php echo htmlspecialchars($title); ?></h4>
                                    <?php if (!empty($flat_data['step3']['marketing_descriptions'][$i])): ?>
                                        <p><?php echo htmlspecialchars($flat_data['step3']['marketing_descriptions'][$i]); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($flat_data['step3']['marketing_urls'][$i])): ?>
                                        <a href="<?php echo htmlspecialchars($flat_data['step3']['marketing_urls'][$i]); ?>" target="_blank">🔗 Website</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Preview Schedule Review -->
                <div class="review-section">
                    <h3>📅 Preview Schedule</h3>
                    <?php if (isset($_SESSION['flat_data']['step4']) && isset($_SESSION['flat_data']['step4']['timetable_dates']) && is_array($_SESSION['flat_data']['step4']['timetable_dates'])): ?>
                        <?php foreach ($_SESSION['flat_data']['step4']['timetable_dates'] as $i => $date): ?>
                            <?php if (!empty($date) && !empty($_SESSION['flat_data']['step4']['timetable_times'][$i])): ?>
                                <div class="schedule-review-item">
                                    <span class="schedule-date">📅 <?php echo formatDate($date); ?></span>
                                    <span class="schedule-time">🕐 <?php echo $_SESSION['flat_data']['step4']['timetable_times'][$i]; ?></span>
                                    <span class="schedule-contact">📞 <?php echo htmlspecialchars($_SESSION['flat_data']['step4']['timetable_contacts'][$i]); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-schedule">No preview schedule has been set.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Submission Notice -->
            <div class="submission-notice">
                <h3>📋 Before You Submit</h3>
                <div class="notice-content">
                    <p><strong>Manager Approval Required:</strong> Your flat listing will be reviewed by our management team before becoming visible to potential tenants.</p>
                    
                    <div class="approval-process">
                        <div class="process-step">
                            <span class="process-icon">1️⃣</span>
                            <div>
                                <strong>Submission</strong>
                                <p>Your listing is submitted for review</p>
                            </div>
                        </div>
                        
                        <div class="process-step">
                            <span class="process-icon">2️⃣</span>
                            <div>
                                <strong>Review</strong>
                                <p>Manager reviews your flat details</p>
                            </div>
                        </div>
                        
                        <div class="process-step">
                            <span class="process-icon">3️⃣</span>
                            <div>
                                <strong>Approval</strong>
                                <p>Your listing goes live upon approval</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Form -->
            <form method="POST" class="listing-form">
                <input type="hidden" name="current_step" value="5">
                <div class="form-actions">
                    <button type="submit" name="step" value="back" class="btn btn-secondary">← Back</button>
                    <button type="submit" name="step" value="5" class="btn btn-primary">Submit for Approval</button>
                </div>
            </form>
        </div>
        
    <?php elseif ($step === 5): ?>
        <!-- Step 5: Success -->
        <div class="success-submission">
            <div class="success-header">
                <h2>🎉 Flat Listing Submitted Successfully!</h2>
                <p>Your flat has been submitted for review and will be available for rent once approved.</p>
            </div>
            
            <div class="success-details">
                <h3>📋 What Happens Next?</h3>
                <div class="next-steps">
                    <div class="step-item">
                        <span class="step-icon">1️⃣</span>
                        <div>
                            <strong>Manager Review</strong>
                            <p>Our team will review your flat listing within 24-48 hours to ensure it meets our quality standards.</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <span class="step-icon">2️⃣</span>
                        <div>
                            <strong>Approval Notification</strong>
                            <p>You'll receive a message once your flat is approved. Your flat will then get a unique 6-digit reference number.</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <span class="step-icon">3️⃣</span>
                        <div>
                            <strong>Live Listing</strong>
                            <p>Your flat will appear in search results and customers can start booking preview appointments.</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <span class="step-icon">4️⃣</span>
                        <div>
                            <strong>Manage Bookings</strong>
                            <p>You'll receive notifications for preview requests and rental applications through your messages.</p>
                        </div>
                    </div>
                </div>
                
                <div class="submission-summary">
                    <h4>📊 Submission Summary</h4>
                    <div class="summary-details">
                        <div><strong>Location:</strong> <?php echo htmlspecialchars($_SESSION['submitted_location']); ?></div>
                        <div><strong>Submitted:</strong> <?php echo formatDateTime(date('Y-m-d H:i:s')); ?></div>
                        <div><strong>Status:</strong> Pending Approval</div>
                    </div>
                </div>
            </div>
            
            <div class="success-actions">
                <a href="main.php?page=my-flats" class="btn btn-primary">📋 View My Properties</a>
                <a href="main.php?page=messages" class="btn btn-secondary">💬 Check Messages</a>
                <a href="main.php?page=offer-flat" class="btn btn-success">➕ List Another Flat</a>
                <a href="main.php" class="btn btn-secondary">🏠 Go Home</a>
            </div>
        </div>
        
        <?php 
        unset($_SESSION['submitted_flat_id']);
        unset($_SESSION['submitted_location']);
        ?>
    <?php endif; ?>
</div>