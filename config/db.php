<?php
// Database configuration settings
$host     = 'localhost';
$db_name  = 'uniwallet';
$username = 'root';      // Default XAMPP MySQL username is 'root'
$password = '';          // Default XAMPP MySQL password is empty (no password)
$charset  = 'utf8mb4';

// Set up the Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

// Set PDO options for optimal security and error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on database errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return data as associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native database prepared statements
];

try {
    // Create a new PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Uncomment the line below temporarily to test the connection!
    // echo "Successfully connected to the UniWallet database!";
} catch (PDOException $e) {
    // If connection fails, catch the error and display a clean message
    die("Database Connection Failed: " . $e->getMessage());
}
?>