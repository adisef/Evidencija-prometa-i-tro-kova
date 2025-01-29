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

// Provjera admin prava
if (!$auth->hasPermission('admin')) {
    header('Location: ../unauthorized.php');
    exit;
}

// Provjera CSRF tokena
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Nevažeći sigurnosni token.';
    header('Location: ../views/unos_agenta.php');
    exit;
}

// Provjera da li su podaci poslani POST metodom
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Nevažeći način pristupa.';
    header('Location: ../views/unos_agenta.php');
    exit;
}

// Validacija da je korisnik koji je započeo unos isti onaj koji ga završava
if (!isset($_POST['created_by']) || $_POST['created_by'] != $auth->getCurrentUser()['id']) {
    $_SESSION['error_message'] = 'Nevažeća sesija unosa.';
    header('Location: ../views/unos_agenta.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Funkcije za validaciju
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    function validateDate($date) {
        return date('Y-m-d', strtotime($date)) === $date;
    }

    // Validacija obaveznih polja
    $required_fields = [
        'ime_prezime' => 'Ime i prezime',
        'telefon' => 'Telefon',
        'email' => 'Email',
        'datum_zaposlenja' => 'Datum zaposlenja',
        'strucna_sprema' => 'Stručna sprema',
        'licenca' => 'Licenca',
        'pozicija' => 'Pozicija'
    ];

    $errors = [];
    
    // Provjera obaveznih polja
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "Polje '{$label}' je obavezno.";
        }
    }

    // Validacija emaila
    if (!empty($_POST['email']) && !validateEmail($_POST['email'])) {
        $errors[] = "Email adresa nije validna.";
    }

    // Validacija datuma
    if (!empty($_POST['datum_zaposlenja']) && !validateDate($_POST['datum_zaposlenja'])) {
        $errors[] = "Datum zaposlenja nije validan.";
    }
    if (!empty($_POST['kraj_zaposlenja']) && !validateDate($_POST['kraj_zaposlenja'])) {
        $errors[] = "Datum kraja zaposlenja nije validan.";
    }

    // Validacija procenta bonusa
    if (!empty($_POST['procenat_bonusa']) && (!is_numeric($_POST['procenat_bonusa']) || $_POST['procenat_bonusa'] < 0)) {
        $errors[] = "Procenat bonusa mora biti pozitivan broj.";
    }

    if (empty($errors)) {
        try {
            // Priprema podataka za unos
            $sql = "INSERT INTO agenti (
                ime_prezime, telefon, email, adresa, 
                datum_zaposlenja, kraj_zaposlenja, strucna_sprema, 
                licenca, pozicija, platni_razred, procenat_bonusa, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Greška u pripremi upita: " . $conn->error);
            }

            // Postavljanje defaultne vrijednosti za status
            $status = 'aktivan';
            
            // Bind parametara
            $stmt->bind_param(
                "ssssssssssds", 
                $_POST['ime_prezime'],
                $_POST['telefon'],
                $_POST['email'],
                $_POST['adresa'],
                $_POST['datum_zaposlenja'],
                $_POST['kraj_zaposlenja'],
                $_POST['strucna_sprema'],
                $_POST['licenca'],
                $_POST['pozicija'],
                $_POST['platni_razred'],
                $_POST['procenat_bonusa'],
                $status
            );

            if (!$stmt->execute()) {
                throw new Exception("Greška prilikom unosa podataka: " . $stmt->error);
            }

            $_SESSION['success_message'] = "Agent je uspješno dodan u sistem!";
            header('Location: ../views/unos_agenta.php');
            exit;

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: ../views/unos_agenta.php');
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
        header('Location: ../views/unos_agenta.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Nevažeći zahtjev. Dozvoljen je samo POST metod.';
    header('Location: ../views/unos_agenta.php');
    exit;
}