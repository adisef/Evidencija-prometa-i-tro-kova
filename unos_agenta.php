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
    header('Location: ../unauthorized.php');
    exit;
}

// Dohvat podataka o prijavljenom korisniku
$currentUser = $auth->getCurrentUser();
$loggedInUser = $currentUser['username'] ?? 'Nepoznat korisnik';

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Unos novog agenta</h2>
        <a href="prikaz_agenata.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Nazad na pregled
        </a>
    </div>

    <!-- Informacija o korisniku koji unosi -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <small class="text-muted">
                <i class="fas fa-user"></i> Unosi korisnik: <?php echo htmlspecialchars($loggedInUser); ?>
                <br>
                <i class="fas fa-clock"></i> Datum unosa: <?php echo date('d.m.Y. H:i'); ?>
            </small>
        </div>
    </div>

    <!-- Ostatak forme ostaje isti, samo uklanjamo dupli prikaz korisnika koji unosi -->
    <form action="../controllers/unos_agent_data.php" method="POST" class="needs-validation" novalidate>
        <!-- CSRF zaštita -->
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- Podaci o korisniku koji unosi -->
        <input type="hidden" name="created_by" value="<?php echo htmlspecialchars($currentUser['id']); ?>">
        <input type="hidden" name="created_at" value="<?php echo date('Y-m-d H:i:s'); ?>">

        <!-- Osnovni podaci -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Osnovni podaci</h5>
				<small class="text-muted">
					Unosi: <?php echo htmlspecialchars($auth->getCurrentUser()['username'] ?? 'Nepoznat korisnik'); ?>
				</small>
			</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="ime_prezime" class="form-label">Ime i prezime *</label>
                        <input type="text" class="form-control" id="ime_prezime" name="ime_prezime" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="telefon" class="form-label">Telefon *</label>
                        <input type="tel" class="form-control" id="telefon" name="telefon" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="adresa" class="form-label">Adresa</label>
                        <input type="text" class="form-control" id="adresa" name="adresa">
                    </div>
                </div>
            </div>
        </div>

        <!-- Podaci o zaposlenju -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Podaci o zaposlenju</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="datum_zaposlenja" class="form-label">Datum zaposlenja *</label>
                        <input type="date" class="form-control" id="datum_zaposlenja" name="datum_zaposlenja" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="kraj_zaposlenja" class="form-label">Kraj zaposlenja</label>
                        <input type="date" class="form-control" id="kraj_zaposlenja" name="kraj_zaposlenja">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="strucna_sprema" class="form-label">Stručna sprema *</label>
                        <select class="form-select" id="strucna_sprema" name="strucna_sprema" required>
                            <option value="">Odaberite stručnu spremu</option>
                            <option value="SSS">SSS</option>
                            <option value="VŠS">VŠS</option>
                            <option value="VSS">VSS</option>
                            <option value="MR">MR</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="pozicija" class="form-label">Pozicija *</label>
                        <select class="form-select" id="pozicija" name="pozicija" required>
                            <option value="">Odaberite poziciju</option>
                            <option value="Agent prodaje">Agent prodaje</option>
                            <option value="Agent za najam">Agent za najam</option>
                            <option value="Call Agent">Call Agent</option>
                            <option value="Fotograf">Fotograf</option>
                            <option value="Administrativni radnik">Administrativni radnik</option>
                            <option value="Direktor">Direktor</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="licenca" class="form-label">Licenca *</label>
                        <select class="form-select" id="licenca" name="licenca" required>
                            <option value="">Odaberite</option>
                            <option value="DA">DA</option>
                            <option value="NE">NE</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Finansijski podaci -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Finansijski podaci</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="platni_razred" class="form-label">Platni razred</label>
                        <input type="text" class="form-control" id="platni_razred" name="platni_razred">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="procenat_bonusa" class="form-label">Procenat bonusa</label>
                        <input type="number" step="0.01" class="form-control" id="procenat_bonusa" name="procenat_bonusa">
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-primary">Sačuvaj</button>
            <a href="../index.php" class="btn btn-secondary">Odustani</a>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict'

    // Fetch all the forms we want to apply custom validation styles to
    var forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
            }, false)
        })

    // Custom validation for procenat_bonusa
    const procenatBonusaInput = document.getElementById('procenat_bonusa')
    if (procenatBonusaInput) {
        procenatBonusaInput.addEventListener('input', function() {
            const value = parseFloat(this.value)
            if (value < 0) {
                this.setCustomValidity('Procenat bonusa ne može biti negativan.')
            } else {
                this.setCustomValidity('')
            }
        })
    }

    // Validacija datuma kraja zaposlenja
    const datumZaposlenjaInput = document.getElementById('datum_zaposlenja')
    const krajZaposlenjaInput = document.getElementById('kraj_zaposlenja')
    
    if (krajZaposlenjaInput && datumZaposlenjaInput) {
        krajZaposlenjaInput.addEventListener('change', function() {
            if (this.value && datumZaposlenjaInput.value) {
                if (new Date(this.value) <= new Date(datumZaposlenjaInput.value)) {
                    this.setCustomValidity('Datum kraja zaposlenja mora biti nakon datuma zaposlenja.')
                } else {
                    this.setCustomValidity('')
                }
            } else {
                this.setCustomValidity('')
            }
        })
    }
})()
</script>

<?php include 'footer.php'; ?>