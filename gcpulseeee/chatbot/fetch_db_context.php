<?php
session_start();
require_once('../db.php');

header('Content-Type: application/json');

try {
    $context = [];
    
    // Fetch recent appointments
    $stmt = $conn->prepare("
        SELECT title, description, appointment_date, status 
        FROM appointments 
        WHERE appointment_date >= NOW() 
        ORDER BY appointment_date ASC 
        LIMIT 2
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $context['upcoming_appointments'] = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch recent posts
 $stmt = $conn->prepare("
    SELECT p.title, p.content, p.category, p.created_at, p.image_path,
           u.firstname, u.lastname
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.created_at DESC
    LIMIT 2
");
    $stmt->execute();
    $result = $stmt->get_result();
    $context['recent_posts'] = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch events
    $stmt = $conn->prepare("
        SELECT title, content, created_at 
        FROM posts 
        WHERE category = 'event'
        ORDER BY created_at DESC
        LIMIT 2
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $context['upcoming_events'] = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch blocked dates
$stmt = $conn->prepare("
    SELECT id, date, reason, created_at 
    FROM blocked_dates 
    ORDER BY date ASC
");
$stmt->execute();
$result = $stmt->get_result();
$context['blocked_dates'] = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch announcements
    $stmt = $conn->prepare("
        SELECT title, content, created_at 
        FROM posts 
        WHERE category = 'announcement'
        ORDER BY created_at DESC
        LIMIT 2
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $context['recent_announcements'] = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $context
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();