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
    header('Location: prikaz_agenata.php');
    exit;
}

// Validacija ID-a
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Nevažeći ID agenta.";
    header('Location: prikaz_agenata.php');
    exit;
}

$agent_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($agent_id === false || $agent_id <= 0) {
    $_SESSION['error_message'] = "Nevažeći format ID-a agenta.";
    header('Location: prikaz_agenata.php');
    exit;
}

try {
    // Prvo provjerimo da li tabela ima sve potrebne kolone
    $sql = "SELECT a.*, 
            COALESCE(u1.username, 'N/A') as created_by_user,
            COALESCE(u2.username, 'N/A') as modified_by_user
            FROM agenti a 
            LEFT JOIN korisnici u1 ON a.created_by = u1.id
            LEFT JOIN korisnici u2 ON a.modified_by = u2.id
            WHERE a.id = ?";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Greška pri pripremi upita: " . $conn->error);
    }

    $stmt->bind_param("i", $agent_id);
    if (!$stmt->execute()) {
        throw new Exception("Greška pri izvršavanju upita: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception("Greška pri dohvaćanju rezultata: " . $stmt->error);
    }

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Agent nije pronađen.";
        header('Location: prikaz_agenata.php');
        exit;
    }

    $agent = $result->fetch_assoc();

    // Dodajemo detaljnije logiranje
    error_log("Dohvaćeni podaci agenta ID $agent_id: " . print_r($agent, true));

    // Provjera obaveznih polja
    $required_fields = ['ime_prezime', 'telefon', 'email', 'datum_zaposlenja', 'status'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($agent[$field]) || $agent[$field] === '') {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        throw new Exception("Nedostaju obavezna polja: " . implode(", ", $missing_fields));
    }

    // Postavljanje defaultnih vrijednosti za opcionalna polja
    $optional_fields = [
        'adresa' => '',
        'kraj_zaposlenja' => null,
        'strucna_sprema' => '',
        'licenca' => 'NE',
        'platni_razred' => '',
        'procenat_bonusa' => '0',
        'created_at' => date('Y-m-d H:i:s'),
        'modified_at' => null
    ];

    foreach ($optional_fields as $field => $default) {
        if (!isset($agent[$field])) {
            $agent[$field] = $default;
        }
    }

} catch (Exception $e) {
    error_log("Greška pri dohvatu agenta (ID: $agent_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Došlo je do greške pri učitavanju podataka: " . $e->getMessage();
    header('Location: prikaz_agenata.php');
    exit;
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}

// Generiranje CSRF tokena
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'header.php';
?>

<!-- Ostatak HTML koda -->

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Uređivanje podataka agenta</h2>
        <a href="prikaz_agenata.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Nazad na pregled
        </a>
    </div>

    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo htmlspecialchars($_SESSION['error_message']); 
            unset($_SESSION['error_message']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Audit informacije -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="fas fa-user-clock"></i> Kreirao: 
                    <?php echo htmlspecialchars($agent['created_by_user'] ?? 'N/A'); ?> 
                    (<?php echo date('d.m.Y. H:i', strtotime($agent['created_at'])); ?>)
                </small>
                <?php if ($agent['modified_by_user']): ?>
                <small class="text-muted">
                    <i class="fas fa-edit"></i> Zadnja izmjena: 
                    <?php echo htmlspecialchars($agent['modified_by_user']); ?> 
                    (<?php echo date('d.m.Y. H:i', strtotime($agent['modified_at'])); ?>)
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form action="../controllers/uredi_agenta_data.php" method="POST" class="needs-validation" novalidate>
        <!-- CSRF zaštita -->
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="agent_id" value="<?php echo htmlspecialchars($agent_id); ?>">
        
        <div class="row">
            <!-- Osobni podaci -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Osobni podaci</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="ime_prezime" class="form-label">Ime i prezime *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="ime_prezime" 
                                   name="ime_prezime" 
                                   value="<?php echo htmlspecialchars($agent['ime_prezime']); ?>" 
                                   required 
                                   pattern="[A-Za-zČčĆćŽžŠšĐđ\s]{2,100}"
                                   maxlength="100"
                                   title="Unesite ispravno ime i prezime (samo slova i razmaci)">
                            <div class="invalid-feedback">Obavezno polje - unesite ispravno ime i prezime</div>
                        </div>

                        <div class="mb-3">
                            <label for="telefon" class="form-label">Telefon *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="telefon" 
                                       name="telefon" 
                                       value="<?php echo htmlspecialchars($agent['telefon']); ?>" 
                                       required 
                                       pattern="[0-9+\-\s()]{6,20}"
                                       maxlength="20"
                                       title="Unesite ispravan broj telefona">
                            </div>
                            <div class="invalid-feedback">Unesite ispravan broj telefona</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($agent['email']); ?>" 
                                       required
                                       maxlength="100"
                                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            </div>
                            <div class="invalid-feedback">Unesite ispravnu email adresu</div>
                        </div>

                        <div class="mb-3">
                            <label for="adresa" class="form-label">Adresa</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-home"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="adresa" 
                                       name="adresa" 
                                       value="<?php echo htmlspecialchars($agent['adresa']); ?>"
                                       maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profesionalni podaci -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-briefcase"></i> Profesionalni podaci</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="datum_zaposlenja" class="form-label">Datum zaposlenja *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                <input type="date" 
                                       class="form-control" 
                                       id="datum_zaposlenja" 
                                       name="datum_zaposlenja" 
                                       value="<?php echo htmlspecialchars($agent['datum_zaposlenja']); ?>" 
                                       required
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="invalid-feedback">Odaberite datum zaposlenja</div>
                        </div>

                        <div class="mb-3">
                            <label for="kraj_zaposlenja" class="form-label">Kraj zaposlenja</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-times"></i></span>
                                <input type="date" 
                                       class="form-control" 
                                       id="kraj_zaposlenja" 
                                       name="kraj_zaposlenja" 
                                       value="<?php echo htmlspecialchars($agent['kraj_zaposlenja']); ?>"
                                       min="<?php echo htmlspecialchars($agent['datum_zaposlenja']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="strucna_sprema" class="form-label">Stručna sprema *</label>
                            <select class="form-select" id="strucna_sprema" name="strucna_sprema" required>
                                <option value="">Odaberite stručnu spremu</option>
                                <?php
                                $strucne_spreme = ['SSS', 'VŠS', 'VSS', 'MR'];
                                foreach ($strucne_spreme as $sprema) {
                                    $selected = ($agent['strucna_sprema'] === $sprema) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($sprema) . "\" $selected>" . 
                                         htmlspecialchars($sprema) . "</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Odaberite stručnu spremu</div>
                        </div>

                        <div class="mb-3">
                            <label for="licenca" class="form-label">Licenca *</label>
                            <select class="form-select" id="licenca" name="licenca" required>
                                <option value="DA" <?php echo $agent['licenca'] === 'DA' ? 'selected' : ''; ?>>DA</option>
                                <option value="NE" <?php echo $agent['licenca'] === 'NE' ? 'selected' : ''; ?>>NE</option>
                            </select>
                            <div class="invalid-feedback">Odaberite status licence</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pozicija i finansije -->
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Pozicija i finansije</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="pozicija" class="form-label">Pozicija *</label>
                                    <select class="form-select" id="pozicija" name="pozicija" required>
                                        <option value="">Odaberite poziciju</option>
                                        <?php
                                        $pozicije = [
                                            'Agent prodaje', 'Agent za najam', 'Call Agent', 
                                            'Fotograf', 'Administrativni radnik', 'Direktor'
                                        ];
                                        foreach ($pozicije as $poz) {
                                            $selected = ($agent['pozicija'] === $poz) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($poz) . "\" $selected>" . 
                                                 htmlspecialchars($poz) . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Odaberite poziciju</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="platni_razred" class="form-label">Platni razred</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="platni_razred" 
                                               name="platni_razred" 
                                               value="<?php echo htmlspecialchars($agent['platni_razred']); ?>"
                                               maxlength="20">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="procenat_bonusa" class="form-label">Procenat bonusa (%)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-percentage"></i></span>
                                        <input type="number" 
                                               step="0.01" 
                                               class="form-control" 
                                               id="procenat_bonusa" 
                                               name="procenat_bonusa" 
                                               value="<?php echo htmlspecialchars($agent['procenat_bonusa']); ?>"
                                               min="0"
                                               max="100">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="aktivan" <?php echo $agent['status'] === 'aktivan' ? 'selected' : ''; ?>>
                                            Aktivan
                                        </option>
                                        <option value="neaktivan" <?php echo $agent['status'] === 'neaktivan' ? 'selected' : ''; ?>>
                                            Neaktivan
                                        </option>
                                    </select>
                                    <div class="invalid-feedback">Odaberite status</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3 mb-5">
            <div class="col-12 text-center">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Sačuvaj izmjene
                </button>
                <a href="prikaz_agenata.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Odustani
                </a>
            </div>
        </div>
    </form>
</div>

<!-- JavaScript validacija -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Postojeća Bootstrap validacija
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()

    // Dodatna validacija
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        // Provjera datuma
        const datumZaposlenja = document.getElementById('datum_zaposlenja').value;
        const krajZaposlenja = document.getElementById('kraj_zaposlenja').value;
        
        if (krajZaposlenja && new Date(krajZaposlenja) < new Date(datumZaposlenja)) {
            event.preventDefault();
            alert('Datum kraja zaposlenja ne može biti prije datuma zaposlenja.');
            return false;
        }

        // Provjera email formata
        const email = document.getElementById('email').value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            event.preventDefault();
            alert('Unesite ispravnu email adresu.');
            return false;
        }

        // Provjera telefona
        const telefon = document.getElementById('telefon').value;
        const telefonRegex = /^[0-9+\-\s()]{6,20}$/;
        if (!telefonRegex.test(telefon)) {
            event.preventDefault();
            alert('Unesite ispravan broj telefona.');
            return false;
        }
    });

    // Automatsko postavljanje statusa na neaktivan
    const krajZaposlenjaInput = document.getElementById('kraj_zaposlenja');
    const statusSelect = document.getElementById('status');

    krajZaposlenjaInput.addEventListener('change', function() {
        if (this.value) {
            statusSelect.value = 'neaktivan';
            statusSelect.disabled = true;
        } else {
            statusSelect.disabled = false;
        }
    });
});
</script>

<?php 
if (isset($stmt)) $stmt->close();
$conn->close();
include 'footer.php'; 
?>