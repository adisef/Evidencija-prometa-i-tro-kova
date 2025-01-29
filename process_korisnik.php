<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.class.php';

$auth = new Auth($conn);

// Provjera autentifikacije i admin prava
if (!$auth->checkAuth() || !$auth->hasPermission('admin')) {
    header('Location: ../unauthorized.php');
    exit;
}

// Provjera CSRF tokena
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Nevažeći sigurnosni token.";
    header('Location: korisnici.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'create';
        
        switch ($action) {
            case 'create':
                // Validacija obaveznih polja
                $required_fields = ['username', 'password', 'ime', 'prezime', 'email'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Sva polja su obavezna.");
                    }
                }

                // Provjera da li korisničko ime već postoji
                $check_stmt = $conn->prepare("SELECT id FROM korisnici WHERE username = ?");
                $check_stmt->bind_param("s", $_POST['username']);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception("Korisničko ime već postoji.");
                }
                $check_stmt->close();

                // Provjera lozinke
                if (strlen($_POST['password']) < 8) {
                    throw new Exception("Lozinka mora imati najmanje 8 karaktera.");
                }

                // Hashiranje lozinke
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                // Priprema SQL upita za insert
                $sql = "INSERT INTO korisnici (username, password, ime, prezime, email, uloga, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'aktivan')";
                
                $stmt = $conn->prepare($sql);
                
                // Postavljanje uloge (admin ako je checkbox označen)
                $uloga = isset($_POST['is_admin']) ? 'admin' : 'viewer';

                $stmt->bind_param("ssssss", 
                    $_POST['username'],
                    $hashed_password,
                    $_POST['ime'],
                    $_POST['prezime'],
                    $_POST['email'],
                    $uloga
                );

                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Novi korisnik je uspješno dodan.";
                } else {
                    throw new Exception("Greška pri dodavanju korisnika: " . $conn->error);
                }
                $stmt->close();
                break;
                
				case 'edit':
					try {
						// Logging za debugiranje
						error_log('Edit korisnika - POST podaci: ' . print_r($_POST, true));

						if (empty($_POST['id'])) {
							throw new Exception("ID korisnika je obavezan.");
						}
						
						// Provjera da li korisnik postoji
						$check_sql = "SELECT uloga, username FROM korisnici WHERE id = ?";
						$stmt = $conn->prepare($check_sql);
						$stmt->bind_param("i", $_POST['id']);
						$stmt->execute();
						$result = $stmt->get_result();
						$user = $result->fetch_assoc();
						
						if (!$user) {
							throw new Exception("Korisnik nije pronađen.");
						}

						if ($user['uloga'] === 'super_admin') {
							throw new Exception("Nije moguće uređivati super admin korisnika.");
						}

						// Provjera da li se username mijenja i da li već postoji
						if ($user['username'] !== $_POST['username']) {
							$check_username = $conn->prepare("SELECT id FROM korisnici WHERE username = ? AND id != ?");
							$check_username->bind_param("si", $_POST['username'], $_POST['id']);
							$check_username->execute();
							if ($check_username->get_result()->num_rows > 0) {
								throw new Exception("Korisničko ime već postoji.");
							}
							$check_username->close();
						}
						
						// Početak SQL upita
						$sql_parts = [];
						$types = "";
						$params = [];
						
						// Osnovni podaci
						$sql_parts[] = "username = ?";
						$sql_parts[] = "ime = ?";
						$sql_parts[] = "prezime = ?";
						$sql_parts[] = "email = ?";
						$types .= "ssss";
						array_push($params, 
							$_POST['username'],
							$_POST['ime'],
							$_POST['prezime'],
							$_POST['email']
						);
						
						// Lozinka ako je unesena
						if (!empty($_POST['password'])) {
							if (strlen($_POST['password']) < 8) {
								throw new Exception("Nova lozinka mora imati najmanje 8 karaktera.");
							}
							$sql_parts[] = "password = ?";
							$types .= "s";
							array_push($params, password_hash($_POST['password'], PASSWORD_DEFAULT));
						}
						
						// Status
						$status = isset($_POST['status']) && $_POST['status'] === 'aktivan' ? 'aktivan' : 'neaktivan';
						$sql_parts[] = "status = ?";
						$types .= "s";
						array_push($params, $status);
						
						// Uloga
						$uloga = isset($_POST['is_admin']) ? 'admin' : 'viewer';
						$sql_parts[] = "uloga = ?";
						$types .= "s";
						array_push($params, $uloga);
						
						// Dodavanje ID-a na kraj
						$types .= "i";
						array_push($params, $_POST['id']);
						
						// Kreiranje i izvršavanje upita
						$sql = "UPDATE korisnici SET " . implode(", ", $sql_parts) . " WHERE id = ?";
						error_log('SQL upit: ' . $sql); // Logging SQL upita
						
						$stmt = $conn->prepare($sql);
						if ($stmt === false) {
							throw new Exception("Greška u pripremi upita: " . $conn->error);
						}
						
						$stmt->bind_param($types, ...$params);
						
						if ($stmt->execute()) {
							$_SESSION['success_message'] = "Korisnik je uspješno ažuriran.";
							error_log('Korisnik uspješno ažuriran. ID: ' . $_POST['id']);
						} else {
							throw new Exception("Greška pri ažuriranju korisnika: " . $stmt->error);
						}
						
						$stmt->close();
						
					} catch (Exception $e) {
						error_log('Greška pri editovanju korisnika: ' . $e->getMessage());
						$_SESSION['error_message'] = $e->getMessage();
					}
					break;
                
				case 'delete':
					try {
						// Logging za debugiranje
						error_log('Delete korisnika - POST podaci: ' . print_r($_POST, true));

						if (empty($_POST['id'])) {
							throw new Exception("ID korisnika je obavezan.");
						}

						// Provjera da li korisnik postoji i njegova uloga
						$check_sql = "SELECT uloga, username FROM korisnici WHERE id = ?";
						$stmt = $conn->prepare($check_sql);
						$stmt->bind_param("i", $_POST['id']);
						$stmt->execute();
						$result = $stmt->get_result();
						$user = $result->fetch_assoc();

						if (!$user) {
							throw new Exception("Korisnik nije pronađen.");
						}

						// Provjera da li je super_admin
						if ($user['uloga'] === 'super_admin') {
							throw new Exception("Nije moguće obrisati super admin korisnika.");
						}

						// Provjera da li korisnik pokušava obrisati sam sebe
						if ($_POST['id'] == $_SESSION['user_id']) {
							throw new Exception("Ne možete obrisati svoj vlastiti korisnički račun.");
						}

						// Brisanje korisnika
						$sql = "DELETE FROM korisnici WHERE id = ?";
						$stmt = $conn->prepare($sql);
						if ($stmt === false) {
							throw new Exception("Greška u pripremi upita: " . $conn->error);
						}

						$stmt->bind_param("i", $_POST['id']);

						if ($stmt->execute()) {
							$_SESSION['success_message'] = "Korisnik {$user['username']} je uspješno obrisan.";
							error_log('Korisnik uspješno obrisan. ID: ' . $_POST['id']);
						} else {
							throw new Exception("Greška pri brisanju korisnika: " . $stmt->error);
						}

						$stmt->close();

					} catch (Exception $e) {
						error_log('Greška pri brisanju korisnika: ' . $e->getMessage());
						$_SESSION['error_message'] = $e->getMessage();
					}
					break;
                
            default:
                throw new Exception("Nepoznata akcija.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: korisnici.php');
    exit;
}
?>