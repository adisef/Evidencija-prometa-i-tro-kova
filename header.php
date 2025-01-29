<?php
// Postavljanje vremenske zone
date_default_timezone_set('Europe/Sarajevo');

// Definiranje apsolutne putanje do root direktorija
define('ROOT_DIR', realpath($_SERVER['DOCUMENT_ROOT'] . '/evidencija'));

// Definiranje base putanje i relativnih putanja
$script_path = $_SERVER['SCRIPT_NAME'];
$current_dir = dirname($script_path);

// Određivanje root putanje do evidencija foldera
$root_path = '/evidencija/';


// Definiranje ostalih putanja bez dupliranja
$admin_path = $root_path . 'admin/';
$views_path = $root_path . 'views/';
$includes_path = $root_path . 'includes/';

// Uključivanje potrebnih fajlova koristeći apsolutne putanje
if (!isset($conn)) {
    require_once ROOT_DIR . '/includes/db.php';
}

if (!isset($auth)) {
    require_once ROOT_DIR . '/includes/auth.class.php';
    $auth = new Auth($conn);
}

// Provjera autentifikacije
if (!$auth->checkAuth()) {
    header('Location: ' . $root_path . 'login.php');
    exit;
}

// Dohvat podataka o trenutnom korisniku
$current_user = $auth->getCurrentUser();
$current_time = date('Y-m-d H:i:s');

// Poboljšana funkcija za generiranje URL-a
function buildUrl($path) {
    global $root_path;
    return $root_path . ltrim($path, '/');
}

// Debug informacije (aktivirajte po potrebi)
if (isset($_GET['debug'])) {
    error_log('Current Directory: ' . $current_dir);
    error_log('Root Path: ' . $root_path);
    error_log('Script Path: ' . $script_path);
    error_log('Includes Path: ' . $includes_path);
}
?>
<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidencija</title>
    
    <?php
    // Dinamičko određivanje base patha za resurse
    $current_page = $_SERVER['PHP_SELF'];
    $is_in_views = strpos($current_page, '/views/') !== false;
    $is_in_izvjestaji = strpos($current_page, '/views/izvjestaji/') !== false;
    $base_path = $is_in_izvjestaji ? '../../' : ($is_in_views ? '../' : '');
    ?>

    <!-- Dodavanje ROOT_URL za JavaScript samo na stranici lokacija -->
    <?php if (strpos($_SERVER['PHP_SELF'], 'lokacije/index.php') !== false): ?>
    <script>
        const ROOT_URL = '<?php echo $root_path; ?>';
    </script>
    <?php endif; ?>
    
	<!-- CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

	<!-- DataTables CSS -->
	<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

	<!-- Custom CSS -->
	<?php
	$stylePath = $root_path . 'assets/css/style.css';
	?>
	<link rel="stylesheet" href="<?php echo $stylePath; ?>">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
    .navbar {
        margin-bottom: 20px;
    }
    .dropdown-item i {
        margin-right: 8px;
        width: 20px;
    }
    .navbar-dark .navbar-nav .nav-link {
        color: rgba(255,255,255,.8);
    }
    .navbar-dark .navbar-nav .nav-link:hover {
        color: rgba(255,255,255,1);
    }
    .dropdown-submenu {
        position: relative;
    }
    .dropdown-submenu .dropdown-menu {
        top: 0;
        left: 100%;
        margin-top: -1px;
    }
    </style>

    <!-- JavaScript za dropdown submenu -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enable all dropdowns
        var dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(dropdown => {
            new bootstrap.Dropdown(dropdown);
        });

        // Handle submenu dropdowns
        document.querySelectorAll('.dropdown-submenu > a').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var submenu = this.nextElementSibling;
                var openSubmenu = document.querySelector('.dropdown-submenu .show');
                
                if(openSubmenu && openSubmenu !== submenu) {
                    openSubmenu.classList.remove('show');
                }
                
                submenu.classList.toggle('show');
            });
        });

        // Close submenu when clicking outside
        document.addEventListener('click', function(e) {
            var openSubmenu = document.querySelector('.dropdown-submenu .show');
            if(openSubmenu && !openSubmenu.contains(e.target)) {
                openSubmenu.classList.remove('show');
            }
        });
    });
    </script>
	<link rel="stylesheet" href="<?php echo ROOT_URL; ?>assets/css/style.css">
</head>
<body>
<!-- Container za obavještenja -->
<div id="alertContainer"></div>
<!-- Glavni container -->
<div class="container-fluid py-4">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $root_path; ?>">Evidencija</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <!-- Lijeva strana navigacije -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $root_path; ?>">
                        <i class="fas fa-home"></i> Početna
                    </a>
                </li>

                <!-- Promet Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="prometDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-chart-line"></i> Promet
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="prometDropdown">
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>unos.php">
                                <i class="fas fa-plus"></i> Unos prometa
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>izvjestaji.php">
                                <i class="fas fa-file-alt"></i> Pregled prometa
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>izvjestaji/promet_po_agentu.php">
                                <i class="fas fa-user-tie"></i> Promet po agentima
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>izvjestaji/pregled_po_lokacijama.php">
                                <i class="fas fa-map-marker-alt"></i> Pregled po lokacijama
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>izvjestaji/godisnji_pregled.php">
                                <i class="fas fa-calendar-check"></i> Godišnji pregled
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Troškovi -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="troskoviDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-money-bill"></i> Troškovi
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="troskoviDropdown">
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>troskovi/unos.php">
                                <i class="fas fa-plus"></i> Unos troška
                            </a>
                        </li>
						<li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>troskovi/pregled.php">
                                <i class="fas fa-list"></i> Pregled troškova
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <!-- Desna strana navigacije -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($current_user['username']); ?>
                        <?php if ($auth->isSuperAdmin()): ?>
                            <span class="badge bg-danger">Super Admin</span>
                        <?php elseif ($auth->hasPermission('admin')): ?>
                            <span class="badge bg-primary">Admin</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>profile.php">
                                <i class="fas fa-user-circle"></i> Profil
                            </a>
                        </li>
                        <?php if ($auth->hasPermission('admin')): ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo $admin_path; ?>korisnici.php">
                                <i class="fas fa-users-cog"></i> Upravljanje korisnicima
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
						<li>
							<a class="dropdown-item" href="<?php echo $views_path; ?>lokacije/">
								<i class="fas fa-map-marked-alt"></i> Upravljanje lokacijama
							</a>
						</li>                        
						<li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>prikaz_agenata.php">
                                <i class="fas fa-list"></i> Pregled agenata
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $views_path; ?>unos_agenta.php">
                                <i class="fas fa-user-plus"></i> Unos agenta
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <?php if ($auth->isSuperAdmin()): ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo $admin_path; ?>postavke.php">
                                <i class="fas fa-cog"></i> Postavke
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $root_path; ?>logout.php">
                                <i class="fas fa-sign-out-alt"></i> Odjava
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

    <!-- Prikaz error i success poruka -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['info_message'];
            unset($_SESSION['info_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>