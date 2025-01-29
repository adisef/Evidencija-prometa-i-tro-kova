<?php
session_start();
require_once '../../includes/db.php';

// Inicijalizacija varijabli
$odabrani_mjesec = isset($_GET['mjesec']) ? $_GET['mjesec'] : date('Y-m');
$godina_mjesec = explode('-', $odabrani_mjesec);
$godina = $godina_mjesec[0];
$mjesec = $godina_mjesec[1];

// Inicijalizacija svih potrebnih varijabli s default vrijednostima
$ukupan_promet = 0;
$ukupna_provizija = 0;
$broj_transakcija = 0;
$broj_prodaja = 0;
$broj_izdavanja = 0;
$broj_ekskluzivnih = 0;
$broj_otvorenih = 0;
$procenat_ekskluzivnih = 0;
$procenat_otvorenih = 0;
$transakcije = array();
$top_agenti = array();

// Inicijalizacija najveće provizije
$najveca_provizija = array(
    'iznos' => 0,
    'datum' => '',
    'lokacija' => '',
    'tip' => '',
    'agent' => ''
);

// Inicijalizacija najskuplje nekretnine
$najskuplja_nekretnina = array(
    'cijena' => 0,
    'datum' => '',
    'lokacija' => '',
    'tip' => '',
    'agent' => ''
);

try {
    // SQL upit
    $sql = "SELECT * FROM promet WHERE DATE_FORMAT(datum_ugovora, '%Y-%m') = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $odabrani_mjesec);
    $stmt->execute();
    $result = $stmt->get_result();

    // Obrada rezultata
    while ($row = $result->fetch_assoc()) {
        $transakcije[] = $row;
        
        // Ažuriranje ukupnih vrijednosti
        $ukupan_promet += $row['cijena'];
        $ukupna_provizija += $row['provizija'];
        $broj_transakcija++;

        // Brojanje prema vrsti usluge
        if ($row['usluga'] === 'Prodaja') {
            $broj_prodaja++;
        } elseif ($row['usluga'] === 'Najam') {
            $broj_izdavanja++;
        }

        // Brojanje prema vrsti ugovora
        if ($row['vrsta_ugovora'] === 'Ekskluzivni') {
            $broj_ekskluzivnih++;
        } elseif ($row['vrsta_ugovora'] === 'Otvoreni') {
            $broj_otvorenih++;
        }

        // Praćenje top agenata
        if (!isset($top_agenti[$row['agent']])) {
            $top_agenti[$row['agent']] = 0;
        }
        $top_agenti[$row['agent']] += $row['provizija'];

        // Provjera najveće provizije
        if ($row['provizija'] > $najveca_provizija['iznos']) {
            $najveca_provizija = array(
                'iznos' => $row['provizija'],
                'datum' => $row['datum_ugovora'],
                'lokacija' => $row['opcina'] . ', ' . $row['naselje'] . ', ' . $row['ulica'],
                'tip' => $row['tip_nekretnine'],
                'agent' => $row['agent']
            );
        }

        // Provjera najskuplje nekretnine
        if ($row['cijena'] > $najskuplja_nekretnina['cijena']) {
            $najskuplja_nekretnina = array(
                'cijena' => $row['cijena'],
                'datum' => $row['datum_ugovora'],
                'lokacija' => $row['opcina'] . ', ' . $row['naselje'] . ', ' . $row['ulica'],
                'tip' => $row['tip_nekretnine'],
                'agent' => $row['agent']
            );
        }
    }

    // Izračun procenata
    if ($broj_transakcija > 0) {
        $procenat_ekskluzivnih = round(($broj_ekskluzivnih / $broj_transakcija) * 100, 1);
        $procenat_otvorenih = round(($broj_otvorenih / $broj_transakcija) * 100, 1);
    }

    // Sortiranje top agenata
    arsort($top_agenti);

} catch (Exception $e) {
    $_SESSION['error'] = "Došlo je do greške: " . $e->getMessage();
    header('Location: ../izvjestaji.php');
    exit;
}

require_once '../header.php';
?>

<div class="container mt-4">
    <!-- Forma za izbor mjeseca -->
    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="mjesec" class="form-label">Odaberi mjesec</label>
                            <input type="month" class="form-control" id="mjesec" name="mjesec" 
                                   value="<?php echo $odabrani_mjesec; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Prikaži
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Naslov -->
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-center">Mjesečni izvještaj</h2>
            <h4 class="text-center text-muted">
                <?php echo date('F Y', strtotime($odabrani_mjesec . '-01')); ?>
            </h4>
        </div>
    </div>

    <!-- Statistički podaci -->
    <div class="row mb-4">
        <!-- Prvi red - Promet i Provizija -->
        <div class="col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Ukupan promet</h5>
                    <h3><?php echo number_format($ukupan_promet, 2); ?> KM</h3>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Ukupna provizija</h5>
                    <h3><?php echo number_format($ukupna_provizija, 2); ?> KM</h3>
                </div>
            </div>
        </div>

        <!-- Drugi red - 4 kartice -->
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Broj prodaja</h5>
                    <h3><?php echo $broj_prodaja; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Broj izdavanja</h5>
                    <h3><?php echo $broj_izdavanja; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-purple text-white">
                <div class="card-body">
                    <h5 class="card-title">Ekskluzivni ugovori</h5>
                    <h3><?php echo $broj_ekskluzivnih; ?> (<?php echo $procenat_ekskluzivnih; ?>%)</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-orange text-white">
                <div class="card-body">
                    <h5 class="card-title">Otvoreni ugovori</h5>
                    <h3><?php echo $broj_otvorenih; ?> (<?php echo $procenat_otvorenih; ?>%)</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Top agenti i najveći uspjesi -->
    <!-- Top agenti i statistika -->
	<div class="row mb-4">
		<!-- Top agenti -->
		<div class="col-md-6">
			<div class="card">
				<div class="card-header bg-primary text-white">
					<h5 class="card-title mb-0">Top agenti mjeseca</h5>
				</div>
				<div class="card-body">
					<div class="list-group">
						<?php 
						$i = 0;
						foreach ($top_agenti as $agent => $provizija):
							$medal = '';
							if ($i == 0) $medal = '<i class="fas fa-medal text-warning" data-bs-toggle="tooltip" title="1. mjesto"></i>';
							elseif ($i == 1) $medal = '<i class="fas fa-medal text-secondary" data-bs-toggle="tooltip" title="2. mjesto"></i>';
							elseif ($i == 2) $medal = '<i class="fas fa-medal" style="color: #CD7F32;" data-bs-toggle="tooltip" title="3. mjesto"></i>';
						?>
							<div class="list-group-item d-flex justify-content-between align-items-center">
								<span>
									<?php echo $medal; ?> 
									<?php echo htmlspecialchars($agent); ?>
								</span>
								<span class="badge bg-primary rounded-pill">
									<?php echo number_format($provizija, 2); ?> KM
								</span>
							</div>
						<?php 
							$i++;
						endforeach; 
						?>
					</div>
				</div>
			</div>
		</div>

		<!-- Statistika prodaja vs najam -->
		<div class="col-md-6">
			<div class="card">
				<div class="card-header bg-info text-white">
					<h5 class="card-title mb-0">Odnos prodaje i najma</h5>
				</div>
				<div class="card-body">
					<div class="row text-center">
						<div class="col-6 border-end">
							<div class="mb-2">
								<i class="fas fa-home fa-2x text-primary"></i>
							</div>
							<h4>Prodaja</h4>
							<div class="display-6"><?php echo $broj_prodaja; ?></div>
							<p class="text-muted">
								<?php echo $broj_transakcija > 0 ? round(($broj_prodaja / $broj_transakcija) * 100) : 0; ?>% transakcija
							</p>
						</div>
						<div class="col-6">
							<div class="mb-2">
								<i class="fas fa-key fa-2x text-warning"></i>
							</div>
							<h4>Najam</h4>
							<div class="display-6"><?php echo $broj_izdavanja; ?></div>
							<p class="text-muted">
								<?php echo $broj_transakcija > 0 ? round(($broj_izdavanja / $broj_transakcija) * 100) : 0; ?>% transakcija
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Najveća provizija i najskuplja nekretnina -->
	<div class="row mb-4">
		<div class="col-md-6">
			<div class="card border-success">
				<div class="card-header bg-success text-white">
					<h5 class="card-title mb-0"><i class="fas fa-trophy me-2"></i>Najveća ostvarena provizija</h5>
				</div>
				<div class="card-body">
					<h3 class="text-success mb-3"><?php echo number_format($najveca_provizija['iznos'], 2); ?> KM</h3>
					<div class="row">
						<div class="col-md-6">
							<p><i class="fas fa-calendar me-2"></i><strong>Datum:</strong><br><?php echo date('d.m.Y.', strtotime($najveca_provizija['datum'])); ?></p>
							<p><i class="fas fa-map-marker-alt me-2"></i><strong>Lokacija:</strong><br><?php echo htmlspecialchars($najveca_provizija['lokacija']); ?></p>
						</div>
						<div class="col-md-6">
							<p><i class="fas fa-building me-2"></i><strong>Tip:</strong><br><?php echo htmlspecialchars($najveca_provizija['tip']); ?></p>
							<p><i class="fas fa-user me-2"></i><strong>Agent:</strong><br><?php echo htmlspecialchars($najveca_provizija['agent']); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="card border-warning">
				<div class="card-header bg-warning">
					<h5 class="card-title mb-0"><i class="fas fa-star me-2"></i>Najskuplja nekretnina</h5>
				</div>
				<div class="card-body">
					<h3 class="text-warning mb-3"><?php echo number_format($najskuplja_nekretnina['cijena'], 2); ?> KM</h3>
					<div class="row">
						<div class="col-md-6">
							<p><i class="fas fa-calendar me-2"></i><strong>Datum:</strong><br><?php echo date('d.m.Y.', strtotime($najskuplja_nekretnina['datum'])); ?></p>
							<p><i class="fas fa-map-marker-alt me-2"></i><strong>Lokacija:</strong><br><?php echo htmlspecialchars($najskuplja_nekretnina['lokacija']); ?></p>
						</div>
						<div class="col-md-6">
							<p><i class="fas fa-building me-2"></i><strong>Tip:</strong><br><?php echo htmlspecialchars($najskuplja_nekretnina['tip']); ?></p>
							<p><i class="fas fa-user me-2"></i><strong>Agent:</strong><br><?php echo htmlspecialchars($najskuplja_nekretnina['agent']); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Lista nekretnina - bez div efekata -->
	<table class="table table-striped table-bordered mt-4">
		<thead class="table-dark">
			<tr>
				<th>Datum</th>
				<th>Agent</th>
				<th>Vrsta usluge</th>
				<th>Vrsta ugovora</th>
				<th>Tip nekretnine</th>
				<th>Općina</th>
				<th>Naselje</th>
				<th>Ulica</th>
				<th>Površina</th>
				<th>Cijena</th>
				<th>Cijena po m²</th>
				<th>Provizija</th>
			</tr>
		</thead>
		<tbody>
			<?php if (count($transakcije) > 0): ?>
				<?php foreach ($transakcije as $row): ?>
					<tr>
						<td><?php echo date('d.m.Y.', strtotime($row['datum_ugovora'])); ?></td>
						<td><?php echo htmlspecialchars($row['agent']); ?></td>
						<td><?php echo htmlspecialchars($row['usluga']); ?></td>
						<td><?php echo htmlspecialchars($row['vrsta_ugovora']); ?></td>
						<td><?php echo htmlspecialchars($row['tip_nekretnine']); ?></td>
						<td><?php echo htmlspecialchars($row['opcina']); ?></td>
						<td><?php echo htmlspecialchars($row['naselje']); ?></td>
						<td><?php echo htmlspecialchars($row['ulica']); ?></td>
						<td class="text-end"><?php echo number_format($row['povrsina'], 2); ?> m²</td>
						<td class="text-end"><?php echo number_format($row['cijena'], 2); ?> KM</td>
						<td class="text-end"><?php echo number_format($row['cijena_po_m2'], 2); ?> KM</td>
						<td class="text-end"><?php echo number_format($row['provizija'], 2); ?> KM</td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<tr>
					<td colspan="12" class="text-center">Nema pronađenih transakcija za odabrani mjesec.</td>
				</tr>
			<?php endif; ?>
		</tbody>
		<tfoot class="table-dark">
			<tr>
				<th colspan="9" class="text-end">UKUPNO:</th>
				<th class="text-end"><?php echo number_format($ukupan_promet, 2); ?> KM</th>
				<th>-</th>
				<th class="text-end"><?php echo number_format($ukupna_provizija, 2); ?> KM</th>
			</tr>
		</tfoot>
	</table>

    <!-- Dugmad -->
    <div class="row mt-4 mb-5">
        <div class="col text-center">
            <button class="btn btn-primary me-2" onclick="window.print()">
                <i class="fas fa-print"></i> Štampaj izvještaj
            </button>
            <a href="../izvjestaji.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Nazad
            </a>
        </div>
    </div>
</div>

<style media="print">
    @page {
        size: landscape;
    }
    .btn, nav, .no-print {
        display: none !important;
    }
    .container {
        width: 100% !important;
        max-width: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
        margin-bottom: 1rem !important;
    }
    .bg-primary, .bg-success, .bg-info, .bg-warning, .bg-purple, .bg-orange {
        background-color: #fff !important;
        color: #000 !important;
    }
    .text-white {
        color: #000 !important;
    }
</style>
<style>
    .bg-purple {
        background-color: #6f42c1 !important;
    }
    .bg-orange {
        background-color: #fd7e14 !important;
    }
    /* Postojeći print stilovi ostaju... */
</style>

<?php
$stmt->close();
$conn->close();
require_once '../footer.php';
?>