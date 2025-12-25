<?php
require_once 'includes/header.php';
require_once 'classes/mysqli.class.php';

$db = new SafeMySQLi(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (isset($_GET['delete_experience'])) {
    $experience_id = intval($_GET['delete_experience']);
    
    $db->query("DELETE FROM experience_weather WHERE experience_id = ?", [$experience_id], "i");
    $db->query("DELETE FROM experience_roads WHERE experience_id = ?", [$experience_id], "i");
    
    $db->query("DELETE FROM driving_experiences WHERE id = ?", [$experience_id], "i");
    
    $delete_message = '<div class="alert success">✅ Experience deleted successfully!</div>';
}

if (isset($_POST['add_weather']) && !empty($_POST['new_weather_name'])) {
    $new_weather = htmlspecialchars(trim($_POST['new_weather_name']));
    $weather_desc = htmlspecialchars(trim($_POST['new_weather_desc'] ?? ''));
    
    $db->insert('weather_conditions', [
        'condition_name' => $new_weather,
        'description' => $weather_desc
    ]);
    
    $weather_message = '<div class="alert success">✅ New weather condition added!</div>';
}

if (isset($_POST['add_road']) && !empty($_POST['new_road_type'])) {
    $new_road = htmlspecialchars(trim($_POST['new_road_type']));
    $road_desc = htmlspecialchars(trim($_POST['new_road_desc'] ?? ''));
    
    $db->insert('road_types', [
        'type_name' => $new_road,
        'description' => $road_desc
    ]);
    
    $road_message = '<div class="alert success">✅ New road type added!</div>';
}

if (isset($_GET['delete_weather'])) {
    $weather_id = intval($_GET['delete_weather']);
    
    $check = $db->query("SELECT COUNT(*) as count FROM experience_weather WHERE weather_id = ?", [$weather_id], "i");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        $db->query("DELETE FROM weather_conditions WHERE id = ?", [$weather_id], "i");
        $weather_message = '<div class="alert success">✅ Weather condition deleted!</div>';
    } else {
        $weather_message = '<div class="alert error">❌ Cannot delete: Weather condition is used in experiences</div>';
    }
}

if (isset($_GET['delete_road'])) {
    $road_id = intval($_GET['delete_road']);
    
    $check = $db->query("SELECT COUNT(*) as count FROM experience_roads WHERE road_type_id = ?", [$road_id], "i");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        $db->query("DELETE FROM road_types WHERE id = ?", [$road_id], "i");
        $road_message = '<div class="alert success">✅ Road type deleted!</div>';
    } else {
        $road_message = '<div class="alert error">❌ Cannot delete: Road type is used in experiences</div>';
    }
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$weather_filter = $_GET['weather_id'] ?? 'all';
$road_filter = $_GET['road_type_id'] ?? 'all';

$query = "SELECT de.* 
          FROM driving_experiences de
          WHERE de.start_date BETWEEN ? AND ?
          ";

$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = "ss";

if ($weather_filter !== 'all') {
    $query .= " AND EXISTS (
        SELECT 1 FROM experience_weather ew 
        WHERE ew.experience_id = de.id 
        AND ew.weather_id = ?
    )";
    $params[] = $weather_filter;
    $types .= "i";
}

if ($road_filter !== 'all') {
    $query .= " AND EXISTS (
        SELECT 1 FROM experience_roads er 
        WHERE er.experience_id = de.id 
        AND er.road_type_id = ?
    )";
    $params[] = $road_filter;
    $types .= "i";
}

$query .= " ORDER BY de.start_date DESC";
$experiences = $db->query($query, $params, $types);

$correct_stats_query = "SELECT 
                        COUNT(DISTINCT de.id) as total_experiences,
                        SUM(de.distance_km) as total_distance,
                        AVG(de.distance_km) as avg_distance,
                        AVG(de.duration_minutes) as avg_duration
                        FROM driving_experiences de
                        WHERE de.start_date BETWEEN ? AND ?
                        ";

$correct_stats_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$correct_stats_types = "ss";

if ($weather_filter !== 'all') {
    $correct_stats_query .= " AND EXISTS (
        SELECT 1 FROM experience_weather ew 
        WHERE ew.experience_id = de.id 
        AND ew.weather_id = ?
    )";
    $correct_stats_params[] = $weather_filter;
    $correct_stats_types .= "i";
}

if ($road_filter !== 'all') {
    $correct_stats_query .= " AND EXISTS (
        SELECT 1 FROM experience_roads er 
        WHERE er.experience_id = de.id 
        AND er.road_type_id = ?
    )";
    $correct_stats_params[] = $road_filter;
    $correct_stats_types .= "i";
}

$correct_stats = $db->query($correct_stats_query, $correct_stats_params, $correct_stats_types);
$stats_row = $correct_stats->fetch_assoc();

$total_km = $stats_row['total_distance'] ?? 0;
$total_experiences = $stats_row['total_experiences'] ?? 0;
$avg_distance = $stats_row['avg_distance'] ?? 0;
$avg_duration = $stats_row['avg_duration'] ?? 0;

$weather_stats_query = "SELECT 
                       wc.id,
                       wc.condition_name,
                       COUNT(DISTINCT de.id) as experience_count,
                       SUM(de.distance_km) as total_distance
                       FROM weather_conditions wc
                       LEFT JOIN experience_weather ew ON wc.id = ew.weather_id
                       LEFT JOIN driving_experiences de ON ew.experience_id = de.id
                       AND de.start_date BETWEEN ? AND ?
                       ";

$weather_stats_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$weather_stats_types = "ss";

if ($weather_filter !== 'all') {
    $weather_stats_query .= " WHERE ew.weather_id = ?";
    $weather_stats_params[] = $weather_filter;
    $weather_stats_types .= "i";
}

if ($road_filter !== 'all') {
    $weather_stats_query .= " AND EXISTS (
        SELECT 1 FROM experience_roads er2 
        WHERE er2.experience_id = de.id 
        AND er2.road_type_id = ?
    )";
    $weather_stats_params[] = $road_filter;
    $weather_stats_types .= "i";
}

$weather_stats_query .= " GROUP BY wc.id, wc.condition_name
                         HAVING experience_count > 0
                         ORDER BY experience_count DESC";

$weather_stats = $db->query($weather_stats_query, $weather_stats_params, $weather_stats_types);

$road_stats_query = "SELECT 
                     rt.id,
                     rt.type_name,
                     COUNT(DISTINCT de.id) as experience_count,
                     SUM(de.distance_km) as total_distance
                     FROM road_types rt
                     LEFT JOIN experience_roads er ON rt.id = er.road_type_id
                     LEFT JOIN driving_experiences de ON er.experience_id = de.id
                     AND de.start_date BETWEEN ? AND ?
                     ";

$road_stats_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$road_stats_types = "ss";

if ($weather_filter !== 'all') {
    $road_stats_query .= " AND EXISTS (
        SELECT 1 FROM experience_weather ew 
        WHERE ew.experience_id = de.id 
        AND ew.weather_id = ?
    )";
    $road_stats_params[] = $weather_filter;
    $road_stats_types .= "i";
}

if ($road_filter !== 'all') {
    $road_stats_query .= " WHERE er.road_type_id = ?";
    $road_stats_params[] = $road_filter;
    $road_stats_types .= "i";
}

$road_stats_query .= " GROUP BY rt.id, rt.type_name
                      HAVING experience_count > 0
                      ORDER BY experience_count DESC";

$road_stats = $db->query($road_stats_query, $road_stats_params, $road_stats_types);

$weather_options = $db->query("SELECT * FROM weather_conditions ORDER BY condition_name");

$road_type_options = $db->query("SELECT * FROM road_types ORDER BY type_name");
?>

<h2>Dashboard</h2>

<!-- Messages -->
<?php echo $delete_message ?? ''; ?>
<?php echo $weather_message ?? ''; ?>
<?php echo $road_message ?? ''; ?>

<!-- Add New Weather/Road Forms -->
<div class="add-variables-section">
    <div class="variable-form">
        <h4>➕ Add New Weather Condition</h4>
        <form method="POST" class="inline-form">
            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="new_weather_name" 
                           placeholder="Weather name (e.g., Misty)" 
                           required class="form-control">
                </div>
                <div class="form-group">
                    <input type="text" name="new_weather_desc" 
                           placeholder="Description (optional)" 
                           class="form-control">
                </div>
                <div class="form-group">
                    <button type="submit" name="add_weather" class="btn btn-success">
                        Add Weather
                    </button>
                </div>
            </div>
        </form>
        
        <!-- List existing weather conditions with delete option -->
        <div class="existing-items">
            <h5>Existing Weather Conditions:</h5>
            <?php 
            $weather_list = $db->query("SELECT * FROM weather_conditions ORDER BY condition_name");
            if ($weather_list->num_rows > 0): ?>
                <ul class="items-list">
                    <?php while ($weather = $weather_list->fetch_assoc()): 
                        // Check if used
                        $used = $db->query("SELECT COUNT(*) as count FROM experience_weather WHERE weather_id = ?", 
                                          [$weather['id']], "i");
                        $used_row = $used->fetch_assoc();
                        $is_used = $used_row['count'] > 0;
                    ?>
                    <li>
                        <span><?php echo htmlspecialchars($weather['condition_name']); ?></span>
                        <?php if (!$is_used): ?>
                            <a href="?delete_weather=<?php echo $weather['id']; ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Delete this weather condition?')">×</a>
                        <?php else: ?>
                            <span class="used-badge">in use</span>
                        <?php endif; ?>
                    </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="variable-form">
        <h4>➕ Add New Road Type</h4>
        <form method="POST" class="inline-form">
            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="new_road_type" 
                           placeholder="Road type (e.g., desert)" 
                           required class="form-control">
                    <small class="hint">Single word recommended</small>
                </div>
                <div class="form-group">
                    <input type="text" name="new_road_desc" 
                           placeholder="Description (optional)" 
                           class="form-control">
                </div>
                <div class="form-group">
                    <button type="submit" name="add_road" class="btn btn-success">
                        Add Road Type
                    </button>
                </div>
            </div>
        </form>
        
        <!-- List existing road types with delete option -->
        <div class="existing-items">
            <h5>Existing Road Types:</h5>
            <?php 
            $road_list = $db->query("SELECT * FROM road_types ORDER BY type_name");
            if ($road_list->num_rows > 0): ?>
                <ul class="items-list">
                    <?php while ($road = $road_list->fetch_assoc()): 
                        // Check if used
                        $used = $db->query("SELECT COUNT(*) as count FROM experience_roads WHERE road_type_id = ?", 
                                          [$road['id']], "i");
                        $used_row = $used->fetch_assoc();
                        $is_used = $used_row['count'] > 0;
                    ?>
                    <li>
                        <span><?php echo htmlspecialchars($road['type_name']); ?></span>
                        <?php if (!$is_used): ?>
                            <a href="?delete_road=<?php echo $road['id']; ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Delete this road type?')">×</a>
                        <?php else: ?>
                            <span class="used-badge">in use</span>
                        <?php endif; ?>
                    </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" class="filter-form">
        <div class="form-row">
            <div class="form-group">
                <label for="start_date">From:</label>
                <input type="date" id="start_date" name="start_date" 
                       value="<?php echo $start_date; ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="end_date">To:</label>
                <input type="date" id="end_date" name="end_date" 
                       value="<?php echo $end_date; ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="weather_id">Weather:</label>
                <select id="weather_id" name="weather_id" class="form-control">
                    <option value="all">All Weather Conditions</option>
                    <?php 
                    $weather_options->data_seek(0);
                    while ($weather = $weather_options->fetch_assoc()): ?>
                        <option value="<?php echo $weather['id']; ?>" 
                            <?php echo $weather_filter == $weather['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($weather['condition_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="road_type_id">Road Type:</label>
                <select id="road_type_id" name="road_type_id" class="form-control">
                    <option value="all">All Road Types</option>
                    <?php 
                    $road_type_options->data_seek(0);
                    while ($road = $road_type_options->fetch_assoc()): ?>
                        <option value="<?php echo $road['id']; ?>" 
                            <?php echo $road_filter == $road['id'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($road['type_name'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="dashboard.php" class="btn btn-secondary">Reset Filters</a>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div class="stats-cards">
    <div class="stat-card">
        <h3>Total Distance</h3>
        <p class="stat-value"><?php echo number_format($total_km, 2); ?> km</p>
    </div>
    
    <div class="stat-card">
        <h3>Total Experiences</h3>
        <p class="stat-value"><?php echo $total_experiences; ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Avg Distance</h3>
        <p class="stat-value">
            <?php echo $total_experiences > 0 ? number_format($avg_distance, 2) : '0'; ?> km
        </p>
    </div>
    
    <div class="stat-card">
        <h3>Avg Duration</h3>
        <p class="stat-value">
            <?php echo number_format($avg_duration, 0); ?> min
        </p>
    </div>
</div>

<!-- Charts Section -->
<div class="charts-section">
    <div class="chart-container">
        <canvas id="weatherChart"></canvas>
    </div>
    
    <div class="chart-container">
        <canvas id="roadTypeChart"></canvas>
    </div>
</div>

<!-- Weather Statistics -->
<div class="stats-section">
    <h3>Weather Statistics</h3>
    <div class="weather-stats">
        <?php if ($weather_stats->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Weather Condition</th>
                        <th>Experiences</th>
                        <th>Total Distance</th>
                        <th>Avg Distance per Experience</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($weather = $weather_stats->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($weather['condition_name']); ?></td>
                        <td><?php echo $weather['experience_count']; ?></td>
                        <td><?php echo number_format($weather['total_distance'], 2); ?> km</td>
                        <td>
                            <?php echo $weather['experience_count'] > 0 
                                ? number_format($weather['total_distance'] / $weather['experience_count'], 2) 
                                : '0'; ?> km
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No weather data available for selected filters.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Road Type Statistics -->
<div class="stats-section">
    <h3>Road Type Breakdown</h3>
    <div class="road-stats">
        <?php if ($road_stats->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Road Type</th>
                        <th>Experiences</th>
                        <th>Total Distance</th>
                        <th>Avg Distance per Experience</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($road = $road_stats->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo ucfirst(htmlspecialchars($road['type_name'])); ?></td>
                        <td><?php echo $road['experience_count']; ?></td>
                        <td><?php echo number_format($road['total_distance'], 2); ?> km</td>
                        <td>
                            <?php echo $road['experience_count'] > 0 
                                ? number_format($road['total_distance'] / $road['experience_count'], 2) 
                                : '0'; ?> km
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No road type data available for selected filters.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Experiences Table -->
<div class="table-section">
    <h3>Driving Experiences</h3>
    
    <?php if ($experiences->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Start Date/Time</th>
                        <th>End Date/Time</th>
                        <th>Duration</th>
                        <th>Distance (km)</th>
                        <th>Weather</th>
                        <th>Road Types</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($exp = $experiences->fetch_assoc()): 
                        $weather_query = "SELECT wc.condition_name 
                                         FROM experience_weather ew
                                         JOIN weather_conditions wc ON ew.weather_id = wc.id
                                         WHERE ew.experience_id = ?";
                        $weathers = $db->query($weather_query, [$exp['id']], "i");
                        
                        $roads_query = "SELECT rt.type_name 
                                       FROM experience_roads er
                                       JOIN road_types rt ON er.road_type_id = rt.id
                                       WHERE er.experience_id = ?";
                        $roads = $db->query($roads_query, [$exp['id']], "i");
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($exp['start_date'])); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($exp['end_date'])); ?></td>
                        <td><?php echo $exp['duration_minutes']; ?> min</td>
                        <td><?php echo number_format($exp['distance_km'], 2); ?></td>
                        <td>
                            <?php 
                            $weather_list = [];
                            while ($w = $weathers->fetch_assoc()) {
                                $weather_list[] = $w['condition_name'];
                            }
                            echo implode(', ', $weather_list);
                            ?>
                        </td>
                        <td>
                            <?php 
                            $road_list = [];
                            while ($r = $roads->fetch_assoc()) {
                                $road_list[] = ucfirst($r['type_name']);
                            }
                            echo implode(', ', $road_list);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($exp['notes']); ?></td>
                        <td>
                            <a href="?delete_experience=<?php echo $exp['id']; ?>" 
                               class="btn-delete" 
                               onclick="return confirm('Delete this driving experience? This action cannot be undone.')">
                                🗑️ Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="no-data">No driving experiences found for the selected filters.</p>
    <?php endif; ?>
</div>

<script>
// Weather Chart
const weatherCtx = document.getElementById('weatherChart').getContext('2d');

<?php
$weather_stats->data_seek(0);
$weather_labels = [];
$weather_data = [];
while ($weather = $weather_stats->fetch_assoc()) {
    $weather_labels[] = $weather['condition_name'];
    $weather_data[] = $weather['experience_count'];
}
?>

new Chart(weatherCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($weather_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($weather_data); ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                '#9966FF', '#FF9F40', '#8AC926', '#1982C4',
                '#6A4C93', '#F25C54', '#7BDCB5', '#00D4AA',
                '#8B5CF6', '#EC4899', '#14B8A6'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Weather Conditions Distribution'
            },
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Road Type Chart
const roadCtx = document.getElementById('roadTypeChart').getContext('2d');

<?php
$road_stats->data_seek(0);
$road_labels = [];
$road_data = [];
while ($road = $road_stats->fetch_assoc()) {
    $road_labels[] = ucfirst($road['type_name']);
    $road_data[] = $road['experience_count'];
}
?>

new Chart(roadCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($road_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($road_data); ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                '#9966FF', '#FF9F40', '#8AC926', '#1982C4',
                '#6A4C93', '#F25C54', '#7BDCB5', '#00D4AA'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Road Type Distribution'
            },
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php
$db->close();
require_once 'includes/footer.php';
?>
