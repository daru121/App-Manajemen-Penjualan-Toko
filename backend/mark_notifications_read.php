<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $query = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
} 