<?php
header('Content-Type: application/json');
require_once '../../../includes/db.php';
require_once '../../../includes/auth.class.php';

$auth = new Auth($conn);

if (!$auth->checkAuth()) {
    echo json_encode(['success' => false, 'error' => 'Niste prijavljeni']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $opcina_id = filter_var($_POST['opcina_id'], FILTER_VALIDATE_INT);
            $naziv = trim($_POST['naziv']);

            if (!$opcina_id || empty($naziv)) {
                throw new Exception('Sva polja su obavezna');
            }

            // Provjera duplikata
            $check = $conn->prepare("SELECT id FROM naselja WHERE naziv = ? AND opcina_id = ?");
            $check->bind_param("si", $naziv, $opcina_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Naselje s tim nazivom već postoji u odabranoj općini');
            }

            $stmt = $conn->prepare("INSERT INTO naselja (naziv, opcina_id, active, created_at, created_by) VALUES (?, ?, 1, ?, ?)");
            $stmt->bind_param("siss", $naziv, $opcina_id, CURRENT_TIME, CURRENT_USER);
            
            if (!$stmt->execute()) {
                throw new Exception($conn->error);
            }
            echo json_encode(['success' => true]);
            break;

        case 'get':
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            
            if (!$id) {
                throw new Exception('Nevažeći ID');
            }

            $stmt = $conn->prepare("
                SELECT n.*, o.kanton_id 
                FROM naselja n 
                JOIN opcine o ON n.opcina_id = o.id 
                WHERE n.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Naselje nije pronađeno');
            }

            echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
            break;

        case 'update':
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $opcina_id = filter_var($_POST['opcina_id'], FILTER_VALIDATE_INT);
            $naziv = trim($_POST['naziv']);
            $active = isset($_POST['active']) ? 1 : 0;

            if (!$id || !$opcina_id || empty($naziv)) {
                throw new Exception('Sva polja su obavezna');
            }

            // Provjera duplikata
            $check = $conn->prepare("SELECT id FROM naselja WHERE naziv = ? AND opcina_id = ? AND id != ?");
            $check->bind_param("sii", $naziv, $opcina_id, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Naselje s tim nazivom već postoji u odabranoj općini');
            }

            $stmt = $conn->prepare("
                UPDATE naselja 
                SET naziv = ?, opcina_id = ?, active = ?, modified_at = ?, modified_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("siissi", $naziv, $opcina_id, $active, CURRENT_TIME, CURRENT_USER, $id);
            
            if (!$stmt->execute()) {
                throw new Exception($conn->error);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            
            if (!$id) {
                throw new Exception('Nevažeći ID');
            }

            $stmt = $conn->prepare("
                UPDATE naselja 
                SET active = 0, modified_at = ?, modified_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", CURRENT_TIME, CURRENT_USER, $id);
            
            if (!$stmt->execute()) {
                throw new Exception($conn->error);
            }
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Nepoznata akcija');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
if (isset($check)) $check->close();
$conn->close();
?>