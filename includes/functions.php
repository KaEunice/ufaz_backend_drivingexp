<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validate_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validate_time($time) {
    return preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $time);
}

function format_date($date_string) {
    return date('F j, Y', strtotime($date_string));
}

function format_datetime($datetime_string) {
    return date('M d, Y H:i', strtotime($datetime_string));
}

function calculate_total_distance($experiences) {
    $total = 0;
    foreach ($experiences as $exp) {
        $total += $exp['distance_km'];
    }
    return $total;
}
?>
