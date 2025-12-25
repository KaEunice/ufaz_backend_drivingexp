<?php
require_once 'includes/header.php';
?>

<div class="welcome-container">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Driving Experience Tracker</h1>
            <p class="hero-subtitle">Track. Analyze. Improve. Your journey to better driving starts here.</p>
            
            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-number">📊</span>
                    <span class="stat-text">Detailed Analytics</span>
                </div>
                <div class="stat">
                    <span class="stat-number">🌦️</span>
                    <span class="stat-text">Weather Tracking</span>
                </div>
                <div class="stat">
                    <span class="stat-number">🛣️</span>
                    <span class="stat-text">Road Type Analysis</span>
                </div>
                <div class="stat">
                    <span class="stat-number">🔒</span>
                    <span class="stat-text">Secure & Private</span>
                </div>
            </div>
        </div>
        
        <div class="hero-image">
            <div class="floating-card card-1">
                <span class="card-icon">🚗</span>
                <span class="card-text">Log Drives</span>
            </div>
            <div class="floating-card card-2">
                <span class="card-icon">📈</span>
                <span class="card-text">View Stats</span>
            </div>
            <div class="floating-card card-3">
                <span class="card-icon">⛈️</span>
                <span class="card-text">Track Weather</span>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="actions-section">
        <h2 class="section-title">Get Started</h2>
        <p class="section-subtitle">Choose what you'd like to do</p>
        
        <div class="action-cards">
            <a href="add_experience.php" class="action-card card-add">
                <div class="action-icon">➕</div>
                <h3>Add New Experience</h3>
                <p>Log your latest driving session with details about weather, road conditions, and more.</p>
                <span class="action-cta">Start Logging →</span>
            </a>
            
            <a href="dashboard.php" class="action-card card-dashboard">
                <div class="action-icon">📊</div>
                <h3>View Dashboard</h3>
                <p>Analyze your driving patterns with interactive charts and detailed statistics.</p>
                <span class="action-cta">View Analytics →</span>
            </a>
        </div>
    </section>

    <!-- Recent Activity -->
    <?php
    require_once 'classes/mysqli.class.php';
    $db = new SafeMySQLi(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $recent = $db->query("SELECT * FROM driving_experiences ORDER BY created_at DESC LIMIT 3");
    
    if ($recent && $recent->num_rows > 0):
    ?>
    <section class="recent-section">
        <h2 class="section-title">Recent Activity</h2>
        <p class="section-subtitle">Your latest driving experiences</p>
        
        <div class="recent-cards">
            <?php while ($exp = $recent->fetch_assoc()): 
                $weather_query = "SELECT wc.condition_name 
                                 FROM experience_weather ew
                                 JOIN weather_conditions wc ON ew.weather_id = wc.id
                                 WHERE ew.experience_id = ?";
                $weathers = $db->query($weather_query, [$exp['id']], "i");
                $weather_list = [];
                while ($w = $weathers->fetch_assoc()) {
                    $weather_list[] = $w['condition_name'];
                }
            ?>
            <div class="recent-card">
                <div class="recent-header">
                    <span class="recent-date"><?php echo date('M d', strtotime($exp['start_date'])); ?></span>
                    <span class="recent-distance"><?php echo number_format($exp['distance_km'], 1); ?> km</span>
                </div>
                <div class="recent-body">
                    <h4><?php echo date('g:i A', strtotime($exp['start_date'])); ?> Drive</h4>
                    <p class="recent-duration">⏱️ <?php echo $exp['duration_minutes']; ?> minutes</p>
                    <?php if (!empty($weather_list)): ?>
                    <p class="recent-weather">🌤️ <?php echo implode(', ', $weather_list); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($exp['notes'])): ?>
                    <p class="recent-notes">"<?php echo htmlspecialchars(substr($exp['notes'], 0, 60)); ?><?php echo strlen($exp['notes']) > 60 ? '...' : ''; ?>"</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <div class="view-all">
            <a href="dashboard.php" class="btn btn-outline">View All Experiences →</a>
        </div>
    </section>
    <?php 
    endif;
    $db->close();
    ?>
</div>

<?php
require_once 'includes/footer.php';
?>
