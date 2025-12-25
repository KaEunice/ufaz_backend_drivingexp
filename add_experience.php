<?php
require_once 'includes/header.php';
require_once 'classes/mysqli.class.php';

$db = new SafeMySQLi(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $start_date = date('Y-m-d H:i:s', strtotime($_POST['start_date'] . ' ' . $_POST['start_time']));
        $end_date = date('Y-m-d H:i:s', strtotime($_POST['end_date'] . ' ' . $_POST['end_time']));
        $distance = floatval($_POST['distance']);
        $notes = htmlspecialchars(trim($_POST['notes'] ?? ''));
        
        $duration_minutes = round((strtotime($end_date) - strtotime($start_date)) / 60);
        
        if ($end_date <= $start_date) {
            throw new Exception("End date/time must be after start date/time");
        }
        
        if ($distance <= 0) {
            throw new Exception("Distance must be greater than 0");
        }
        
        if (!isset($_POST['weather_ids']) || empty($_POST['weather_ids'])) {
            throw new Exception("Please select at least one weather condition");
        }
        
        if (!isset($_POST['road_type_ids']) || empty($_POST['road_type_ids'])) {
            throw new Exception("Please select at least one road type");
        }
        
        $driver_id = SafeMySQLi::anonymize(session_id() . $_SERVER['REMOTE_ADDR']);
        
        $db->getConnection()->begin_transaction();
        
        $experience_data = [
            'driver_hash' => $driver_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'duration_minutes' => $duration_minutes,
            'distance_km' => $distance,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $insert_result = $db->insert('driving_experiences', $experience_data);
        
        if (!$insert_result) {
            throw new Exception("Failed to insert main experience: " . $db->getConnection()->error);
        }
        
        $experience_id = $db->getConnection()->insert_id;
        
        foreach ($_POST['weather_ids'] as $weather_id) {
            $weather_id = intval($weather_id);
            if ($weather_id > 0) {
                $db->query(
                    "INSERT INTO experience_weather (experience_id, weather_id) VALUES (?, ?)",
                    [$experience_id, $weather_id],
                    "ii"
                );
            }
        }
        
        foreach ($_POST['road_type_ids'] as $road_type_id) {
            $road_type_id = intval($road_type_id);
            if ($road_type_id > 0) {
                $db->query(
                    "INSERT INTO experience_roads (experience_id, road_type_id) VALUES (?, ?)",
                    [$experience_id, $road_type_id],
                    "ii"
                );
            }
        }
        
        $db->getConnection()->commit();
        
        $message = '<div class="alert success">✅ Driving experience added successfully!</div>';
        
    } catch (Exception $e) {
        if ($db->getConnection()->errno) {
            $db->getConnection()->rollback();
        }
        $message = '<div class="alert error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

$weather_options = $db->query("SELECT * FROM weather_conditions ORDER BY condition_name");

$road_type_options = $db->query("SELECT * FROM road_types ORDER BY type_name");
?>

<h2>Add New Driving Experience</h2>

<?php echo $message; ?>

<form method="POST" action="" class="experience-form" id="drivingForm">
    <!-- Start Date/Time -->
    <div class="form-row">
        <div class="form-group">
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" 
                   value="<?php echo date('Y-m-d'); ?>" 
                   required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="start_time">Start Time:</label>
            <input type="time" id="start_time" name="start_time" 
                   value="<?php echo date('H:i'); ?>" 
                   required class="form-control">
        </div>
    </div>
    
    <!-- End Date/Time -->
    <div class="form-row">
        <div class="form-group">
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" 
                   value="<?php echo date('Y-m-d'); ?>" 
                   required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="end_time">End Time:</label>
            <input type="time" id="end_time" name="end_time" 
                   value="<?php echo date('H:i', strtotime('+1 hour')); ?>" 
                   required class="form-control">
        </div>
    </div>
    
    <!-- Distance -->
    <div class="form-group">
        <label for="distance">Distance (km):</label>
        <input type="number" id="distance" name="distance" 
               step="0.1" min="0.1" max="10000" 
               placeholder="Enter distance in kilometers"
               required class="form-control">
        <small class="hint">Minimum 0.1 km</small>
    </div>
    
    <!-- Weather Conditions (Multiple Selection) -->
    <div class="form-group">
        <label for="weather_ids">Weather Conditions: <span class="required">*</span></label>
        <div class="checkbox-group" id="weatherCheckboxes">
            <?php if ($weather_options->num_rows > 0): ?>
                <?php while ($weather = $weather_options->fetch_assoc()): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="weather_ids[]" 
                           value="<?php echo $weather['id']; ?>"
                           class="weather-checkbox">
                    <span class="checkbox-text">
                        <strong><?php echo htmlspecialchars($weather['condition_name']); ?></strong>
                        <?php if (!empty($weather['description'])): ?>
                            <small><?php echo htmlspecialchars($weather['description']); ?></small>
                        <?php endif; ?>
                    </span>
                </label>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-options">No weather conditions available. 
                    <a href="dashboard.php">Add some first</a>.
                </p>
            <?php endif; ?>
        </div>
        <small class="hint">Select at least one weather condition</small>
        <div class="validation-error" id="weatherError" style="display: none;">
            ❌ Please select at least one weather condition
        </div>
    </div>
    
    <!-- Road Types (Multiple Selection) -->
    <div class="form-group">
        <label for="road_type_ids">Road Types: <span class="required">*</span></label>
        <div class="checkbox-group" id="roadCheckboxes">
            <?php if ($road_type_options->num_rows > 0): ?>
                <?php while ($road = $road_type_options->fetch_assoc()): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="road_type_ids[]" 
                           value="<?php echo $road['id']; ?>"
                           class="road-checkbox">
                    <span class="checkbox-text">
                        <strong><?php echo ucfirst(htmlspecialchars($road['type_name'])); ?></strong>
                        <?php if (!empty($road['description'])): ?>
                            <small><?php echo htmlspecialchars($road['description']); ?></small>
                        <?php endif; ?>
                    </span>
                </label>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-options">No road types available. 
                    <a href="dashboard.php">Add some first</a>.
                </p>
            <?php endif; ?>
        </div>
        <small class="hint">Select at least one road type</small>
        <div class="validation-error" id="roadError" style="display: none;">
            ❌ Please select at least one road type
        </div>
    </div>
    
    <!-- Notes -->
    <div class="form-group">
        <label for="notes">Notes:</label>
        <textarea id="notes" name="notes" 
                  placeholder="Additional notes about the experience (traffic, road conditions, observations)..."
                  rows="4" class="form-control"></textarea>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="submitBtn">Save Experience</button>
        <button type="reset" class="btn btn-secondary">Clear Form</button>
        <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('drivingForm');
    const submitBtn = document.getElementById('submitBtn');
    const weatherError = document.getElementById('weatherError');
    const roadError = document.getElementById('roadError');
    
    const timeInput = document.getElementById('start_time');
    if (timeInput && !timeInput.value) {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        timeInput.value = `${hours}:${minutes}`;
    }
    
    const endTimeInput = document.getElementById('end_time');
    if (endTimeInput) {
        const startTime = timeInput.value;
        if (startTime) {
            const [hours, minutes] = startTime.split(':').map(Number);
            let endHours = hours + 1;
            if (endHours >= 24) endHours -= 24;
            endTimeInput.value = `${endHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
        }
    }
    
    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        const weatherChecked = document.querySelectorAll('.weather-checkbox:checked').length > 0;
        if (!weatherChecked) {
            weatherError.style.display = 'block';
            isValid = false;
        } else {
            weatherError.style.display = 'none';
        }
        
        const roadChecked = document.querySelectorAll('.road-checkbox:checked').length > 0;
        if (!roadChecked) {
            roadError.style.display = 'block';
            isValid = false;
        } else {
            roadError.style.display = 'none';
        }
        
        const distance = document.getElementById('distance').value;
        if (distance <= 0) {
            alert('Distance must be greater than 0');
            isValid = false;
        }
        
        const startDate = document.getElementById('start_date').value;
        const startTime = document.getElementById('start_time').value;
        const endDate = document.getElementById('end_date').value;
        const endTime = document.getElementById('end_time').value;
        
        const startDateTime = new Date(startDate + 'T' + startTime);
        const endDateTime = new Date(endDate + 'T' + endTime);
        
        if (endDateTime <= startDateTime) {
            alert('End date/time must be after start date/time');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        } else {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';
        }
    });
    
    // Real-time validation for checkboxes
    document.querySelectorAll('.weather-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.weather-checkbox:checked').length;
            weatherError.style.display = checkedCount > 0 ? 'none' : 'block';
        });
    });
    
    document.querySelectorAll('.road-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.road-checkbox:checked').length;
            roadError.style.display = checkedCount > 0 ? 'none' : 'block';
        });
    });
    
    // Auto-calculating end time when start time changes
    document.getElementById('start_time').addEventListener('change', function() {
        const startTime = this.value;
        if (startTime) {
            const [hours, minutes] = startTime.split(':').map(Number);
            let endHours = hours + 1;
            if (endHours >= 24) endHours -= 24;
            document.getElementById('end_time').value = 
                `${endHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
        }
    });
});
</script>

<?php
$db->close();
require_once 'includes/footer.php';
?>
