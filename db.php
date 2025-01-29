<?php
// Database konfiguracija
$host = 'localhost';
$dbname = 'evidencija';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception("Greška pri povezivanju: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Nije moguće povezati se sa bazom: " . $e->getMessage());
}

// Postavljanje vremenskog pojasa
date_default_timezone_set('Europe/Sarajevo');

// Konstante za putanje
define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT']);
define('APP_ROOT', DOC_ROOT . '/evidencija');
define('ROOT_URL', '/evidencija/');

// Trenutno vrijeme i korisnik
define('CURRENT_TIME', '2025-01-29 18:39:57');
define('CURRENT_USER', 'adisef');

// Debug mode
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Logging funkcija
function logError($message) {
    if (DEBUG_MODE) {
        error_log(date('Y-m-d H:i:s') . " - " . $message);
    }
}

// CORS Headers za AJAX pozive
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Session start ako već nije pokrenut
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}