<?php
session_start();
require_once '../../includes/db.php';

// Dohvat i validacija godine
$trenutna_godina = date('Y');
$odabrana_godina = isset($_GET['godina']) ? filter_input(INPUT_GET, 'godina', FILTER_SANITIZE_NUMBER_INT) : $trenutna_godina;
$filter_usluga = isset($_GET['usluga']) ? $_GET['usluga'] : 'sve';

// Inicijalizacija varijabli
$ukupan_promet = 0;
$ukupna_provizija = 0;
$broj_ugovora = 0;
$broj_prodaja = 0;
$broj_izdavanja = 0;
$broj_ekskluzivnih = 0;
$broj_otvorenih = 0;
$ukupno_kvadrata = 0;

// Glavni SQL upit za godišnju statistiku
$sql = "SELECT 
            SUM(cijena) as ukupan_promet,
            SUM(provizija) as ukupna_provizija,
            COUNT(*) as broj_ugovora,
            SUM(CASE WHEN usluga = 'Prodaja' THEN 1 ELSE 0 END) as broj_prodaja,
            SUM(CASE WHEN usluga = 'Najam' THEN 1 ELSE 0 END) as broj_izdavanja,
            SUM(CASE WHEN vrsta_ugovora = 'Ekskluzivni' THEN 1 ELSE 0 END) as broj_ekskluzivnih,
            SUM(CASE WHEN vrsta_ugovora = 'Otvoreni' THEN 1 ELSE 0 END) as broj_otvorenih,
            SUM(povrsina) as ukupno_kvadrata
        FROM promet 
        WHERE YEAR(datum_ugovora) = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$godisnja_statistika = $stmt->get_result()->fetch_assoc();

// Mjesečna statistika
$sql_mjesecno = "SELECT 
                    MONTH(datum_ugovora) as mjesec,
                    SUM(CASE WHEN usluga = 'Prodaja' THEN 1 ELSE 0 END) as broj_prodaja,
                    SUM(CASE WHEN usluga = 'Najam' THEN 1 ELSE 0 END) as broj_izdavanja,
                    COUNT(*) as ukupno_ugovora,
                    SUM(provizija) as ukupna_provizija,
                    SUM(cijena) as ukupan_promet
                FROM promet 
                WHERE YEAR(datum_ugovora) = ?
                GROUP BY MONTH(datum_ugovora)
                ORDER BY mjesec";

$stmt = $conn->prepare($sql_mjesecno);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$mjesecna_statistika = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Najveća provizija
$sql_max = "SELECT *
            FROM promet 
            WHERE YEAR(datum_ugovora) = ?
            ORDER BY provizija DESC
            LIMIT 1";

$stmt = $conn->prepare($sql_max);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$najveca_provizija = $stmt->get_result()->fetch_assoc();

// Najskuplja nekretnina
$sql_max_cijena = "SELECT *
                   FROM promet 
                   WHERE YEAR(datum_ugovora) = ?
                   ORDER BY cijena DESC
                   LIMIT 1";

$stmt = $conn->prepare($sql_max_cijena);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$najskuplja_nekretnina = $stmt->get_result()->fetch_assoc();

// Statistika po općinama
$sql_opcine = "SELECT 
                opcina,
                COUNT(*) as broj_ugovora,
                SUM(CASE WHEN usluga = 'Prodaja' THEN 1 ELSE 0 END) as broj_prodaja,
                SUM(CASE WHEN usluga = 'Najam' THEN 1 ELSE 0 END) as broj_izdavanja,
                SUM(provizija) as ukupna_provizija,
                SUM(cijena) as ukupan_promet
            FROM promet 
            WHERE YEAR(datum_ugovora) = ?
            GROUP BY opcina
            ORDER BY ukupna_provizija DESC";

$stmt = $conn->prepare($sql_opcine);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$statistika_opcina = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistika po tipovima nekretnina
$sql_tipovi = "SELECT 
                tip_nekretnine,
                COUNT(*) as broj_ugovora,
                SUM(CASE WHEN usluga = 'Prodaja' THEN 1 ELSE 0 END) as broj_prodaja,
                SUM(CASE WHEN usluga = 'Najam' THEN 1 ELSE 0 END) as broj_izdavanja,
                SUM(provizija) as ukupna_provizija,
                SUM(cijena) as ukupan_promet
            FROM promet 
            WHERE YEAR(datum_ugovora) = ?
            GROUP BY tip_nekretnine
            ORDER BY ukupna_provizija DESC";

$stmt = $conn->prepare($sql_tipovi);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$statistika_tipova = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistika po agentima
$sql_agenti = "SELECT 
                agent,
                COUNT(*) as broj_ugovora,
                SUM(CASE WHEN usluga = 'Prodaja' THEN 1 ELSE 0 END) as broj_prodaja,
                SUM(CASE WHEN usluga = 'Najam' THEN 1 ELSE 0 END) as broj_izdavanja,
                SUM(provizija) as ukupna_provizija,
                SUM(cijena) as ukupan_promet
            FROM promet 
            WHERE YEAR(datum_ugovora) = ?
            GROUP BY agent
            ORDER BY ukupna_provizija DESC";

$stmt = $conn->prepare($sql_agenti);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$statistika_agenata = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lista svih nekretnina
$sql_nekretnine = "SELECT *
                   FROM promet 
                   WHERE YEAR(datum_ugovora) = ?
                   ORDER BY datum_ugovora DESC";

$stmt = $conn->prepare($sql_nekretnine);
$stmt->bind_param("i", $odabrana_godina);
$stmt->execute();
$sve_nekretnine = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once '../header.php';
?>
<div class="container" style="max-width: 1360px;">
    <!-- Zaglavlje i filtri -->
    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="godina" class="form-label">Odaberi godinu</label>
                            <select class="form-select" id="godina" name="godina">
                                <?php
                                $start_year = 2020;
                                for ($y = $trenutna_godina; $y >= $start_year; $y--) {
                                    $selected = ($y == $odabrana_godina) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
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

    <!-- Glavni pokazatelji -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Ukupan promet</h6>
                    <h3><?php echo number_format($godisnja_statistika['ukupan_promet'], 2); ?> KM</h3>
                    <div class="mt-3 small">
                        <i class="fas fa-chart-line me-2"></i>Godina <?php echo $odabrana_godina; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Ukupna provizija</h6>
                    <h3><?php echo number_format($godisnja_statistika['ukupna_provizija'], 2); ?> KM</h3>
                    <div class="mt-3 small">
                        <i class="fas fa-coins me-2"></i><?php echo $godisnja_statistika['broj_ugovora']; ?> ugovora
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Prodaja vs Najam</h6>
                    <h3><?php echo $godisnja_statistika['broj_prodaja']; ?> / <?php echo $godisnja_statistika['broj_izdavanja']; ?></h3>
                    <div class="mt-3 small">
                        <i class="fas fa-home me-2"></i>Prodaja/Najam
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning h-100">
                <div class="card-body">
                    <h6 class="card-title">Kvadratura</h6>
                    <h3><?php echo number_format($godisnja_statistika['ukupno_kvadrata'], 2); ?> m²</h3>
                    <div class="mt-3 small">
                        <i class="fas fa-ruler-combined me-2"></i>Ukupno prodato/izdato
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
                    <h5 class="card-title mb-0"><i class="fas fa-trophy me-2"></i>Najveća provizija godine</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="text-success mb-3"><?php echo number_format($najveca_provizija['provizija'], 2); ?> KM</h3>
                            <p><i class="fas fa-calendar me-2"></i><strong>Datum:</strong> <?php echo date('d.m.Y.', strtotime($najveca_provizija['datum_ugovora'])); ?></p>
                            <p><i class="fas fa-user me-2"></i><strong>Agent:</strong> <?php echo htmlspecialchars($najveca_provizija['agent']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><i class="fas fa-map-marker-alt me-2"></i><strong>Lokacija:</strong> <?php echo htmlspecialchars($najveca_provizija['opcina'] . ', ' . $najveca_provizija['naselje']); ?></p>
                            <p><i class="fas fa-home me-2"></i><strong>Tip:</strong> <?php echo htmlspecialchars($najveca_provizija['tip_nekretnine']); ?></p>
                            <p><i class="fas fa-ruler me-2"></i><strong>Površina:</strong> <?php echo number_format($najveca_provizija['povrsina'], 2); ?> m²</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0"><i class="fas fa-star me-2"></i>Najskuplja nekretnina godine</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="text-warning mb-3"><?php echo number_format($najskuplja_nekretnina['cijena'], 2); ?> KM</h3>
                            <p><i class="fas fa-calendar me-2"></i><strong>Datum:</strong> <?php echo date('d.m.Y.', strtotime($najskuplja_nekretnina['datum_ugovora'])); ?></p>
                            <p><i class="fas fa-user me-2"></i><strong>Agent:</strong> <?php echo htmlspecialchars($najskuplja_nekretnina['agent']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><i class="fas fa-map-marker-alt me-2"></i><strong>Lokacija:</strong> <?php echo htmlspecialchars($najskuplja_nekretnina['opcina'] . ', ' . $najskuplja_nekretnina['naselje']); ?></p>
                            <p><i class="fas fa-home me-2"></i><strong>Tip:</strong> <?php echo htmlspecialchars($najskuplja_nekretnina['tip_nekretnine']); ?></p>
                            <p><i class="fas fa-ruler me-2"></i><strong>Površina:</strong> <?php echo number_format($najskuplja_nekretnina['povrsina'], 2); ?> m²</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
	<!-- Mjesečna statistika -->
    <div class="row mb-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Mjesečna statistika <?php echo $odabrana_godina; ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Mjesec</th>
                                    <th>Prodaja</th>
                                    <th>Najam</th>
                                    <th>Ukupno</th>
                                    <th>Provizija</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $mjeseci = ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Juni', 
                                          'Juli', 'August', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];
                                foreach ($mjesecna_statistika as $mjesec) {
                                    echo "<tr>
                                        <td>{$mjeseci[$mjesec['mjesec']-1]}</td>
                                        <td>{$mjesec['broj_prodaja']}</td>
                                        <td>{$mjesec['broj_izdavanja']}</td>
                                        <td>{$mjesec['ukupno_ugovora']}</td>
                                        <td class='text-end'>" . number_format($mjesec['ukupna_provizija'], 2) . " KM</td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Grafički prikaz po mjesecima</h5>
                </div>
                <div class="card-body">
                    <canvas id="mjesecniGraf" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistika po tipovima i općinama -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistika po tipovima nekretnina</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tip nekretnine</th>
                                    <th>Prodaja</th>
                                    <th>Najam</th>
                                    <th>Ukupno</th>
                                    <th>Provizija</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statistika_tipova as $tip): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tip['tip_nekretnine']); ?></td>
                                        <td><?php echo $tip['broj_prodaja']; ?></td>
                                        <td><?php echo $tip['broj_izdavanja']; ?></td>
                                        <td><?php echo $tip['broj_ugovora']; ?></td>
                                        <td class="text-end"><?php echo number_format($tip['ukupna_provizija'], 2); ?> KM</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistika po općinama</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Općina</th>
                                    <th>Prodaja</th>
                                    <th>Najam</th>
                                    <th>Ukupno</th>
                                    <th>Provizija</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statistika_opcina as $opcina): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($opcina['opcina']); ?></td>
                                        <td><?php echo $opcina['broj_prodaja']; ?></td>
                                        <td><?php echo $opcina['broj_izdavanja']; ?></td>
                                        <td><?php echo $opcina['broj_ugovora']; ?></td>
                                        <td class="text-end"><?php echo number_format($opcina['ukupna_provizija'], 2); ?> KM</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
	<!-- Statistika agenata -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistika po agentima</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Agent</th>
                                    <th>Prodaja</th>
                                    <th>Najam</th>
                                    <th>Ukupno</th>
                                    <th>Provizija</th>
                                    <th>Udio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $ukupna_godisnja_provizija = $godisnja_statistika['ukupna_provizija'];
                                foreach ($statistika_agenata as $agent): 
                                    $udio = $ukupna_godisnja_provizija > 0 ? 
                                        ($agent['ukupna_provizija'] / $ukupna_godisnja_provizija) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agent['agent']); ?></td>
                                        <td><?php echo $agent['broj_prodaja']; ?></td>
                                        <td><?php echo $agent['broj_izdavanja']; ?></td>
                                        <td><?php echo $agent['broj_ugovora']; ?></td>
                                        <td class="text-end"><?php echo number_format($agent['ukupna_provizija'], 2); ?> KM</td>
                                        <td class="text-end"><?php echo number_format($udio, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Udio agenata u prometu</h5>
                </div>
                <div class="card-body">
                    <canvas id="agentiGraf" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista svih nekretnina -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Lista svih nekretnina u <?php echo $odabrana_godina; ?></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Datum</th>
                        <th>Agent</th>
                        <th>Tip</th>
                        <th>Lokacija</th>
                        <th>Površina</th>
                        <th>Usluga</th>
                        <th>Vrsta ugovora</th>
                        <th>Cijena</th>
                        <th>Cijena/m²</th>
                        <th>Provizija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sve_nekretnine as $nekretnina): ?>
                        <tr>
                            <td><?php echo date('d.m.Y.', strtotime($nekretnina['datum_ugovora'])); ?></td>
                            <td><?php echo htmlspecialchars($nekretnina['agent']); ?></td>
                            <td><?php echo htmlspecialchars($nekretnina['tip_nekretnine']); ?></td>
                            <td><?php echo htmlspecialchars($nekretnina['opcina'] . ', ' . $nekretnina['naselje']); ?></td>
                            <td class="text-end"><?php echo number_format($nekretnina['povrsina'], 2); ?> m²</td>
                            <td><?php echo htmlspecialchars($nekretnina['usluga']); ?></td>
                            <td><?php echo htmlspecialchars($nekretnina['vrsta_ugovora']); ?></td>
                            <td class="text-end"><?php echo number_format($nekretnina['cijena'], 2); ?> KM</td>
                            <td class="text-end"><?php echo number_format($nekretnina['cijena_po_m2'], 2); ?> KM</td>
                            <td class="text-end"><?php echo number_format($nekretnina['provizija'], 2); ?> KM</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

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

<!-- Print stilovi -->
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
    .bg-primary, .bg-success, .bg-info, .bg-warning {
        background-color: #fff !important;
        color: #000 !important;
    }
    .text-white {
        color: #000 !important;
    }
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Mjesečni graf
const ctx = document.getElementById('mjesecniGraf').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($m) use ($mjeseci) { 
            return $mjeseci[$m['mjesec']-1]; 
        }, $mjesecna_statistika)); ?>,
        datasets: [{
            label: 'Provizija',
            data: <?php echo json_encode(array_column($mjesecna_statistika, 'ukupna_provizija')); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgb(75, 192, 192)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' KM';
                    }
                }
            }
        }
    }
});

// Graf agenata
const ctxAgenti = document.getElementById('agentiGraf').getContext('2d');
new Chart(ctxAgenti, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($statistika_agenata, 'agent')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(function($agent) use ($ukupna_godisnja_provizija) {
                return $ukupna_godisnja_provizija > 0 ? 
                    round(($agent['ukupna_provizija'] / $ukupna_godisnja_provizija) * 100, 1) : 0;
            }, $statistika_agenata)); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.raw + '%';
                    }
                }
            }
        }
    }
});
</script>

<?php
$conn->close();
require_once '../footer.php';
?>