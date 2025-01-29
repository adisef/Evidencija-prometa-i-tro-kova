<?php
require_once 'includes/db.php';
require_once 'includes/auth.class.php';

$auth = new Auth($conn);

// Provjeravamo je li korisnik ulogovan
if (!$auth->checkAuth()) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pristup zabranjen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="text-danger"><i class="fas fa-exclamation-triangle"></i></h1>
                        <h2>Pristup zabranjen</h2>
                        <p>Nemate potrebne dozvole za pristup ovoj stranici.</p>
                        <a href="index.php" class="btn btn-primary">Nazad na početnu</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>