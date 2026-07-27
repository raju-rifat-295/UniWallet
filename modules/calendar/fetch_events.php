<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';

// Tell the browser this script returns JSON data, not HTML!
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

try {
    // Advanced DBMS Feature: UNION ALL query combining 3 distinct tables!
    $sql = "
        SELECT event_id AS id, title, event_date AS start, event_type AS type, status, 'manual' AS source
        FROM Calender_Event 
        WHERE user_id = :u1
        
        UNION ALL
        
        SELECT goal_id AS id, CONCAT('🎯 Goal: ', goal_name) AS title, deadline AS start, 'Savings Deadline' AS type, status, 'savings' AS source
        FROM Savings_goal 
        WHERE user_id = :u2 AND status = 'In Progress'
        
        UNION ALL
        
        SELECT loan_id AS id, CONCAT('💸 Debt Due: ', person_name) AS title, due_date AS start, 'Loan Due' AS type, status, 'loan' AS source
        FROM Loan 
        WHERE user_id = :u3 AND due_date IS NOT NULL AND status != 'Completed'
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['u1' => $user_id, 'u2' => $user_id, 'u3' => $user_id]);
    $events = $stmt->fetchAll();

    // Format the database records into FullCalendar's required JSON structure
    $formatted_events = [];
    foreach ($events as $row) {
        $color = '#0d6efd'; // Default blue for general events
        if ($row['type'] === 'Bill') $color = '#dc3545';             // Red for bills
        elseif ($row['type'] === 'Expected Income') $color = '#198754'; // Green for income
        elseif ($row['type'] === 'Loan Due') $color = '#fd7e14';        // Orange for loans
        elseif ($row['type'] === 'Savings Deadline') $color = '#6f42c1'; // Purple for savings

        $formatted_events[] = [
            'id'              => $row['source'] . '_' . $row['id'],
            'title'           => $row['title'],
            'start'           => $row['start'],
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'allDay'          => true,
            'extendedProps'   => [
                'type'   => $row['type'],
                'status' => $row['status'],
                'source' => $row['source']
            ]
        ];
    }

    echo json_encode($formatted_events);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>