<?php
session_start();
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validacija ID-a
    if (!isset($_POST['agent_id']) || !is_numeric($_POST['agent_id'])) {
        $_SESSION['error_message'] = "Nevažeći ID agenta.";
        header('Location: ../views/prikaz_agenata.php');
        exit;
    }

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
        'pozicija' => 'Pozicija',
        'status' => 'Status'
    ];

    $errors = [];
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
			// Automatsko postavljanje statusa na neaktivan ako postoji datum kraja zaposlenja
			if (!empty($_POST['kraj_zaposlenja']) && validateDate($_POST['kraj_zaposlenja'])) {
				$_POST['status'] = 'neaktivan';
			}

			$sql = "UPDATE agenti SET 
					ime_prezime = ?,
					telefon = ?,
					email = ?,
					adresa = ?,
					datum_zaposlenja = ?,
					kraj_zaposlenja = ?,
					strucna_sprema = ?,
					licenca = ?,
					pozicija = ?,
					platni_razred = ?,
					procenat_bonusa = ?,
					status = ?
					WHERE id = ?";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Greška u pripremi upita: " . $conn->error);
            }

            // Priprema null vrijednosti za kraj_zaposlenja ako je prazan
            $kraj_zaposlenja = !empty($_POST['kraj_zaposlenja']) ? $_POST['kraj_zaposlenja'] : null;
            $procenat_bonusa = !empty($_POST['procenat_bonusa']) ? $_POST['procenat_bonusa'] : null;
            
            $stmt->bind_param(
                "ssssssssssdsi",
                $_POST['ime_prezime'],
                $_POST['telefon'],
                $_POST['email'],
                $_POST['adresa'],
                $_POST['datum_zaposlenja'],
                $kraj_zaposlenja,
                $_POST['strucna_sprema'],
                $_POST['licenca'],
                $_POST['pozicija'],
                $_POST['platni_razred'],
                $procenat_bonusa,
                $_POST['status'],
                $_POST['agent_id']
            );

            if (!$stmt->execute()) {
                throw new Exception("Greška prilikom ažuriranja podataka: " . $stmt->error);
            }

            $_SESSION['success_message'] = "Podaci o agentu su uspješno ažurirani!";
            header('Location: ../views/prikaz_agenata.php');
            exit;

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: ../views/uredi_agenta.php?id=' . $_POST['agent_id']);
            exit;
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
        header('Location: ../views/uredi_agenta.php?id=' . $_POST['agent_id']);
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Nevažeći zahtjev. Dozvoljen je samo POST metod.';
    header('Location: ../views/prikaz_agenata.php');
    exit;} // Zatvaranje else bloka

$stmt->close();
$conn->close();
?>