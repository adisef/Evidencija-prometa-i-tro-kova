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
            $naziv = trim($_POST['naziv']);

            if (empty($naziv)) {
                throw new Exception('Naziv kantona je obavezan');
            }

            // Provjera duplikata
            $check = $conn->prepare("SELECT id FROM kantoni WHERE naziv = ?");
            $check->bind_param("s", $naziv);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Kanton s tim nazivom već postoji');
            }

            $stmt = $conn->prepare("INSERT INTO kantoni (naziv, active, created_at, created_by) VALUES (?, 1, ?, ?)");
            $stmt->bind_param("sss", $naziv, CURRENT_TIME, CURRENT_USER);
            
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

            $stmt = $conn->prepare("SELECT * FROM kantoni WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Kanton nije pronađen');
            }

            echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
            break;

        case 'update':
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $naziv = trim($_POST['naziv']);
            $active = isset($_POST['active']) ? 1 : 0;

            if (!$id || empty($naziv)) {
                throw new Exception('Sva polja su obavezna');
            }

            // Provjera duplikata
            $check = $conn->prepare("SELECT id FROM kantoni WHERE naziv = ? AND id != ?");
            $check->bind_param("si", $naziv, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Kanton s tim nazivom već postoji');
            }

            $stmt = $conn->prepare("
                UPDATE kantoni 
                SET naziv = ?, active = ?, modified_at = ?, modified_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sissi", $naziv, $active, CURRENT_TIME, CURRENT_USER, $id);
            
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

            // Provjera postojanja povezanih općina
            $check = $conn->prepare("SELECT COUNT(*) as count FROM opcine WHERE kanton_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $result = $check->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception('Nije moguće obrisati kanton koji ima povezane općine');
            }

            $stmt = $conn->prepare("
                UPDATE kantoni 
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