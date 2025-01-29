<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.class.php';

// Inicijalizacija Auth klase
$auth = new Auth($conn);

// Provjera autentifikacije
if (!$auth->checkAuth()) {
    header('Location: ../login.php');
    exit;
}

// Provjera admin prava
if (!$auth->hasPermission('admin')) {
    $_SESSION['error_message'] = "Nemate ovlaštenja za ovu akciju.";
    header('Location: ../views/prikaz_agenata.php');
    exit;
}

// Provjera metode i CSRF tokena
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Nevažeća metoda pristupa.";
    header('Location: ../views/prikaz_agenata.php');
    exit;
}

// Provjera CSRF tokena
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Nevažeći sigurnosni token.";
    header('Location: ../views/prikaz_agenata.php');
    exit;
}

// Provjera postojanja i validacija ID-a
if (!isset($_POST['agent_id']) || !is_numeric($_POST['agent_id'])) {
    $_SESSION['error_message'] = "Nevažeći ID agenta.";
    header('Location: ../views/prikaz_agenata.php');
    exit;
}

$agent_id = filter_var($_POST['agent_id'], FILTER_VALIDATE_INT);
if ($agent_id === false || $agent_id <= 0) {
    $_SESSION['error_message'] = "Nevažeći format ID-a agenta.";
    header('Location: ../views/prikaz_agenata.php');
    exit;
}

// Započni transakciju
$conn->begin_transaction();

try {
    // Prvo provjeri da li agent postoji
    $check_exists = "SELECT id, ime_prezime FROM agenti WHERE id = ? LIMIT 1";
    $exists_stmt = $conn->prepare($check_exists);
    $exists_stmt->bind_param("i", $agent_id);
    $exists_stmt->execute();
    $agent_result = $exists_stmt->get_result();
    
    if ($agent_result->num_rows === 0) {
        throw new Exception("Agent ne postoji.");
    }
    
    $agent_data = $agent_result->fetch_assoc();

    // Provjeri povezane zapise
    $check_sql = "SELECT COUNT(*) as count FROM promet WHERE agent = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $agent_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Ako agent ima povezane zapise u prometu, označi ga kao obrisan (soft delete)
        $update_sql = "UPDATE agenti SET 
                      deleted_at = NOW(), 
                      deleted_by = ?, 
                      status = 'neaktivan'
                      WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $current_user_id = $auth->getCurrentUser()['id'];
        $stmt->bind_param("ii", $current_user_id, $agent_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Greška prilikom brisanja agenta.");
        }
        
        $_SESSION['success_message'] = "Agent je uspješno arhiviran jer ima povezane zapise u prometu.";
    } else {
        // Ako agent nema povezanih zapisa, obriši ga potpuno
        $delete_sql = "DELETE FROM agenti WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $agent_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Greška prilikom brisanja agenta.");
        }
        
        $_SESSION['success_message'] = "Agent je uspješno obrisan.";
    }
    
    // Dodaj zapis u log
    $log_sql = "INSERT INTO activity_log (user_id, action, table_name, record_id, description) 
               VALUES (?, ?, 'agenti', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $current_user_id = $auth->getCurrentUser()['id'];
    $action = $row['count'] > 0 ? 'soft_delete' : 'delete';
    $description = "Obrisan agent: " . $agent_data['ime_prezime'];
    $log_stmt->bind_param("isis", $current_user_id, $action, $agent_id, $description);
    $log_stmt->execute();
    
    // Potvrdi transakciju
    $conn->commit();
    
} catch (Exception $e) {
    // Poništi transakciju u slučaju greške
    $conn->rollback();
    $_SESSION['error_message'] = $e->getMessage();
    error_log("Greška pri brisanju agenta (ID: $agent_id): " . $e->getMessage());
} finally {
    // Zatvori sve prepared statements
    if (isset($exists_stmt)) $exists_stmt->close();
    if (isset($check_stmt)) $check_stmt->close();
    if (isset($stmt)) $stmt->close();
    if (isset($log_stmt)) $log_stmt->close();
}

// Preusmjeri nazad na listu agenata
header('Location: ../views/prikaz_agenata.php');
exit;
?>