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
        case 'getByKanton':
            $kanton_id = filter_var($_GET['kanton_id'], FILTER_VALIDATE_INT);
            
            if (!$kanton_id) {
                throw new Exception('Nevažeći ID kantona');
            }

            $stmt = $conn->prepare("SELECT id, naziv FROM opcine WHERE kanton_id = ? AND active = 1 ORDER BY naziv");
            $stmt->bind_param("i", $kanton_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $opcine = [];
            while ($row = $result->fetch_assoc()) {
                $opcine[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $opcine]);
            break;

        case 'save':
            $kanton_id = filter_var($_POST['kanton_id'], FILTER_VALIDATE_INT);
            $naziv = trim($_POST['naziv']);

            if (!$kanton_id || empty($naziv)) {
                throw new Exception('Sva polja su obavezna');
            }

            // Provjera duplikata
            $check = $conn->prepare("SELECT id FROM opcine WHERE naziv = ? AND kanton_id = ?");
            $check->bind_param("si", $naziv, $kanton_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Općina s tim nazivom već postoji u odabranom kantonu');
            }

            $stmt = $conn->prepare("INSERT INTO opcine (naziv, kanton_id, active, created_at, created_by) VALUES (?, ?, 1, ?, ?)");
            $stmt->bind_param("siss", $naziv, $kanton_id, CURRENT_TIME, CURRENT_USER);
            
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

            $stmt = $conn->prepare("SELECT * FROM opcine WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Općina nije pronađena');
            }

            echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
            break;

        case 'update':
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $kanton_id = filter_var($_POST['kanton_id'], FILTER_VALIDATE_INT);
            $naziv = trim($_POST['naziv']);
            $active = isset($_POST['active']) ? 1 : 0;

            if (!$id || !$kanton_id || empty($naziv)) {
                throw new Exception('Sva polja su obavezna');
            }

            // Provjera duplikata
            $check = $conn->prepare("SELECT id FROM opcine WHERE naziv = ? AND kanton_id = ? AND id != ?");
            $check->bind_param("sii", $naziv, $kanton_id, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Općina s tim nazivom već postoji u odabranom kantonu');
            }

            $stmt = $conn->prepare("
                UPDATE opcine 
                SET naziv = ?, kanton_id = ?, active = ?, modified_at = ?, modified_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("siissi", $naziv, $kanton_id, $active, CURRENT_TIME, CURRENT_USER, $id);
            
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

            // Provjera postojanja povezanih naselja
            $check = $conn->prepare("SELECT COUNT(*) as count FROM naselja WHERE opcina_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $result = $check->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception('Nije moguće obrisati općinu koja ima povezana naselja');
            }

            $stmt = $conn->prepare("
                UPDATE opcine 
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