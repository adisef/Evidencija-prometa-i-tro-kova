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

// Provjera dozvola - možete prilagoditi potrebne uloge
if (!$auth->hasPermission('admin') && !$auth->hasPermission('agent')) {
    header('Location: ../unauthorized.php');
    exit;
}

// Dohvat trenutnog korisnika
$current_user = $auth->getCurrentUser();

// Uključivanje header-a
include 'header.php';

// Dohvat aktivnih agenata
$sql_agenti = "SELECT k.id, k.ime_prezime 
               FROM agenti k 
               WHERE k.status = 'aktivan' 
               ORDER BY k.ime_prezime ASC";

$result_agenti = $conn->query($sql_agenti);

// Provjera da li ima agenata
$agenti = [];
if ($result_agenti && $result_agenti->num_rows > 0) {
    while($row = $result_agenti->fetch_assoc()) {
        $agenti[] = $row;
    }
}

// Ako je korisnik agent, možda želite prikazati samo njegove podatke
if ($auth->hasPermission('agent') && !$auth->hasPermission('admin')) {
    // Filtriranje liste agenata da prikaže samo trenutnog korisnika
    $agenti = array_filter($agenti, function($agent) use ($current_user) {
        return $agent['ime_prezime'] === $current_user['username'];
    });
}

// Dodavanje CSRF zaštite
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-invoice me-2"></i>
                        Unos podataka o prometu
                    </h5>
                </div>
                
                <div class="card-body p-4">
                    <?php if(isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="../controllers/unos_data.php" method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="created_by" value="<?php echo htmlspecialchars($current_user['id']); ?>">
                        <input type="hidden" name="created_at" value="<?php echo date('Y-m-d H:i:s'); ?>">

                        <!-- Osnovni podaci -->
                        <div class="row g-4">
                            <!-- Osnovni podaci - Prva kolona -->
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Osnovni podaci</h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Postojeća polja za osnovne podatke -->
                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="far fa-calendar-alt me-2"></i>Datum ugovora *
                                            </label>
                                            <input type="date" class="form-control form-control-lg" id="datum_ugovora" name="datum_ugovora" required>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="fas fa-tags me-2"></i>Usluga *
                                            </label>
                                            <select class="form-select form-select-lg" id="usluga" name="usluga" required>
                                                <option value="">Odaberite uslugu</option>
                                                <option value="Prodaja">Prodaja</option>
                                                <option value="Iznajmljivanje">Iznajmljivanje</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-file-contract me-2"></i>Vrsta ugovora *
                                            </label>
                                            <select class="form-select" id="vrsta_ugovora" name="vrsta_ugovora" required>
                                                <option value="">Odaberite vrstu ugovora</option>
                                                <option value="Ekskluzivni">Ekskluzivni</option>
                                                <option value="Standardni">Standardni</option>
                                            </select>
                                        </div>

										<div class="mb-3">
											<label class="form-label">
												<i class="fas fa-user-tie me-2"></i>Agent *
											</label>
											<select class="form-select form-select-lg" id="agent" name="agent" required>
												<option value="">Odaberite agenta</option>
												<?php foreach($agenti as $agent): ?>
													<option value="<?php echo htmlspecialchars($agent['ime_prezime']); ?>">
														<?php echo htmlspecialchars($agent['ime_prezime']); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Podaci o nekretnini -->
                            <div class="col-md-4" style="border-right: 1px solid #f7f7f7; border-left: 1px solid #f7f7f7;">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-home me-2"></i>Podaci o nekretnini</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-building me-2"></i>Tip nekretnine *
                                            </label>
                                            <select class="form-select" id="tip_nekretnine" name="tip_nekretnine" required>
                                                <option value="">Odaberite tip nekretnine</option>
                                                <option value="Stan">Stan</option>
                                                <option value="Kuća">Kuća</option>
                                                <option value="Poslovni prostor">Poslovni prostor</option>
                                                <option value="Zemljište">Zemljište</option>
                                                <option value="Garaža">Garaža</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt me-2"></i>Lokacija *
                                            </label>
                                            <div class="row g-2">
                                                <div class="col-sm-6">
                                                    <input type="text" class="form-control" id="opcina" name="opcina" placeholder="Općina" required>
                                                </div>
                                                <div class="col-sm-6">
                                                    <input type="text" class="form-control" id="naselje" name="naselje" placeholder="Naselje" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-road me-2"></i>Ulica *
                                            </label>
                                            <input type="text" class="form-control" id="ulica" name="ulica" required>
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <div class="mb-3">
                                                    <label class="form-label">
                                                        <i class="fas fa-ruler me-2"></i>Površina (m²) *
                                                    </label>
                                                    <input type="text" class="form-control" id="povrsina" name="povrsina" pattern="^\d*\.?\d*$" required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-3">
                                                    <label class="form-label">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Cijena (KM) *
                                                    </label>
                                                    <input type="text" class="form-control" id="cijena" name="cijena" pattern="^\d*\.?\d*$" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dodatni podaci - Treća kolona -->
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Dodatni podaci</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="fas fa-percentage me-2"></i>Provizija (KM) *
                                            </label>
                                            <input type="text" class="form-control form-control-lg" id="provizija" name="provizija" pattern="^\d*\.?\d*$" required>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="fas fa-chart-line me-2"></i>Način akvizicije
                                            </label>
                                            <select class="form-select form-select-lg" id="nacin_akvizicije" name="nacin_akvizicije">
                                                <option value="">Odaberite način akvizicije</option>
                                                <option value="Teren">Teren</option>
                                                <option value="Preporuka">Preporuka</option>
                                                <option value="Web">Web</option>
                                                <option value="Društvene mreže">Društvene mreže</option>
                                                <option value="Baza">Baza</option>
                                            </select>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="fas fa-comment-alt me-2"></i>Napomena
                                            </label>
                                            <textarea class="form-control form-control-lg" id="napomena" name="napomena" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dugmad na dnu -->
                        <div class="row mt-4">
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-save me-2"></i>Spremi podatke
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg px-5 ms-2">
                                    <i class="fas fa-undo me-2"></i>Poništi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Postojeći stilovi ostaju, dodajemo nove */
.form-control,
.form-select {
    padding: 0.75rem 1rem;
}

.form-control:focus,
.form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.form-label {
    font-weight: 500;
    color: #6c757d;
    margin-bottom: 0.5rem;
}
.row.justify-content-center {
    max-width: 1400px;
    margin: 0 auto;
}
.card.h-100.border-0.shadow-sm {
    box-shadow: none !important;
}
.card-header.bg-light {
    background: #fff !important;
    padding: 18px 12px;
    border-bottom: none;
}
.card-header.bg-primary.text-white.py-3 {
    background: #fff !important;
    color: #333 !important;
    margin: 33px;
    border-bottom: 1px solid #f7f7f7;
    padding: 3px;
}
h5.card-title.mb-0 {
    font-size: 30px;
    padding: 12px 0;
}
.form-control, .form-select {
    font-size: 1rem;
}
h6.mb-0 {
    font-size: 19px;
}
.alert.alert-success.alert-dismissible.fade.show.m-3 {
    max-width: 1374px;
    margin: 0 auto !important;
}
button.btn.btn-primary.btn-lg.px-5 {
    max-width: 250px;
    border-radius: 30px;
    font-size: 17px;
    padding: 11px;
}
button.btn.btn-secondary.btn-lg.px-5.ms-2 {
    max-width: 250px;
    border-radius: 30px;
    font-size: 17px;
    padding: 11px;
}
.card-body.p-4 {
    padding: 1.5rem 1.5rem 4rem !important;
}
</style>

<script>
// Postojeća Bootstrap validacija
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
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
})()

// Formatiranje brojeva
document.querySelectorAll('input[type="number"]').forEach(function(input) {
    input.addEventListener('input', function() {
        if (this.value.length > 0) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });
});
// Automatski izračun provizije
// Poboljšano rukovanje numeričkim poljima
document.addEventListener('DOMContentLoaded', function() {
    const cijenaInput = document.getElementById('cijena');
    const uslugaSelect = document.getElementById('usluga');
    const provizijaInput = document.getElementById('provizija');
    const povrsinaInput = document.getElementById('povrsina');

    // Funkcija za formatiranje broja na dvije decimale
    function formatNumber(value) {
        return parseFloat(value).toFixed(2);
    }

    // Funkcija za izračun provizije
    function izracunajProviziju() {
        const cijena = parseFloat(cijenaInput.value) || 0;
        const usluga = uslugaSelect.value;
        let provizija = 0;

        if (usluga === 'Prodaja') {
            provizija = cijena * 0.03 * 1.17; // 3% + PDV (17%)
        } else if (usluga === 'Iznajmljivanje') {
            provizija = cijena; // 100% od cijene
        }

        if (provizija > 0) {
            provizijaInput.value = formatNumber(provizija);
        }
    }

    // Formatiranje brojeva samo kad polje izgubi fokus
    function handleNumericInput(input) {
        // Kada polje dobije fokus, prikaži originalnu vrijednost
        input.addEventListener('focus', function() {
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toString();
            }
        });

        // Formatiraj broj kada polje izgubi fokus
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = formatNumber(this.value);
            }
        });

        // Dozvoli samo brojeve i tačku tokom unosa
        input.addEventListener('input', function(e) {
            const value = this.value;
            if (value && !/^\d*\.?\d*$/.test(value)) {
                this.value = value.replace(/[^\d.]/g, '');
            }
        });
    }

    // Primijeni handleNumericInput na sva numerička polja
    handleNumericInput(cijenaInput);
    handleNumericInput(provizijaInput);
    handleNumericInput(povrsinaInput);

    // Događaji za izračun provizije
    cijenaInput.addEventListener('blur', izracunajProviziju);
    uslugaSelect.addEventListener('change', izracunajProviziju);
});
</script>

