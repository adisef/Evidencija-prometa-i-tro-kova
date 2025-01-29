<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.class.php';

$auth = new Auth($conn);

// Provjera autentifikacije
if (!$auth->checkAuth()) {
    header('Location: ../login.php');
    exit;
}

// Provjera CSRF tokena
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Nevažeći sigurnosni token');
}

// Provjera dozvola
if (!$auth->hasPermission('admin') && !$auth->hasPermission('agent')) {
    header('Location: ../unauthorized.php');
    exit;
}

// Dohvat podataka iz forme uz sanitizaciju
$created_by = filter_input(INPUT_POST, 'created_by', FILTER_SANITIZE_NUMBER_INT);
$created_at = filter_input(INPUT_POST, 'created_at', FILTER_SANITIZE_STRING);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input validation functions
    function validateDate($date) {
        return date('Y-m-d', strtotime($date)) === $date;
    }
    
    function validateNumeric($value, $min = 0) {
        return is_numeric($value) && $value >= $min;
    }

    // Validate and sanitize inputs
    $errors = [];
    
    // Required fields validation
    $required_fields = [
        'datum_ugovora' => 'Datum ugovora',
        'usluga' => 'Usluga',
        'vrsta_ugovora' => 'Vrsta ugovora',
        'agent' => 'Agent',
        'tip_nekretnine' => 'Tip nekretnine'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "Polje '{$label}' je obavezno.";
        }
    }

    // Date validation
    if (!empty($_POST['datum_ugovora']) && !validateDate($_POST['datum_ugovora'])) {
        $errors[] = "Neispravan format datuma. Koristite format YYYY-MM-DD.";
    }

    // Numeric fields validation
    $numeric_fields = [
        'povrsina' => 'Površina',
        'cijena' => 'Cijena',
        'provizija' => 'Provizija'
    ];
    
    foreach ($numeric_fields as $field => $label) {
        if (!empty($_POST[$field]) && !validateNumeric($_POST[$field])) {
            $errors[] = "{$label} mora biti pozitivan broj.";
        }
    }

    if (empty($errors)) {
        try {
            // Priprema podataka
            $data = [
                'datum_ugovora' => htmlspecialchars(trim($_POST['datum_ugovora'])),
                'usluga' => htmlspecialchars(trim($_POST['usluga'] ?? '')),
                'vrsta_ugovora' => htmlspecialchars(trim($_POST['vrsta_ugovora'] ?? '')),
                'agent' => htmlspecialchars(trim($_POST['agent'] ?? '')),
                'tip_nekretnine' => htmlspecialchars(trim($_POST['tip_nekretnine'] ?? '')),
                'opcina' => htmlspecialchars(trim($_POST['opcina'] ?? '')),
                'naselje' => htmlspecialchars(trim($_POST['naselje'] ?? '')),
                'ulica' => htmlspecialchars(trim($_POST['ulica'] ?? '')),
                'povrsina' => filter_var($_POST['povrsina'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                'cijena' => filter_var($_POST['cijena'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                'provizija' => filter_var($_POST['provizija'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                'nacin_akvizicije' => htmlspecialchars(trim($_POST['nacin_akvizicije'] ?? '')),
                'napomena' => htmlspecialchars(trim($_POST['napomena'] ?? ''))
            ];

            $sql = "INSERT INTO promet (
                datum_ugovora, usluga, vrsta_ugovora, agent, 
                tip_nekretnine, opcina, naselje, ulica, 
                povrsina, cijena, provizija, nacin_akvizicije, napomena
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Greška u pripremi upita: " . $conn->error);
            }

            $stmt->bind_param(
                "ssssssssdddss", 
                $data['datum_ugovora'],
                $data['usluga'],
                $data['vrsta_ugovora'],
                $data['agent'],
                $data['tip_nekretnine'],
                $data['opcina'],
                $data['naselje'],
                $data['ulica'],
                $data['povrsina'],
                $data['cijena'],
                $data['provizija'],
                $data['nacin_akvizicije'],
                $data['napomena']
            );

            if (!$stmt->execute()) {
                throw new Exception("Greška prilikom unosa podataka: " . $stmt->error);
            }

            $_SESSION['success_message'] = 'Podaci su uspješno uneseni!';
            header('Location: ../views/unos.php');
            exit;

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: ../views/unos.php');
            exit;
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
            if (isset($conn)) {
                $conn->close();
            }
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
        header('Location: ../views/unos.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Nevažeći zahtjev. Dozvoljen je samo POST metod.';
    header('Location: ../views/unos.php');
    exit;
}