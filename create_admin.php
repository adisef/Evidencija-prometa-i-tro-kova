<?php
require_once 'includes/db.php';

// Koristite ovo samo jednom za kreiranje prvog admin korisnika!
$admin_username = 'admin';
$admin_password = password_hash('vaša_sigurna_lozinka', PASSWORD_DEFAULT);
$admin_email = 'vas@email.com';

$sql = "INSERT INTO korisnici (username, password, ime, prezime, email, uloga, status) 
        VALUES (?, ?, 'Admin', 'Administrator', ?, 'admin', 'aktivan')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $admin_username, $admin_password, $admin_email);

if ($stmt->execute()) {
    echo "Admin korisnik uspješno kreiran!";
} else {
    echo "Greška: " . $conn->error;
}

$stmt->close();
$conn->close();
?>