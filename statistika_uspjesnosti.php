<?php
session_start();
require_once '../../includes/db.php';
require_once '../header.php';

// Dohvat i validacija perioda
$period = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_STRING) ?: 'tekuci_mjesec';

try {
    // Određivanje datuma prema odabranom periodu
    switch ($period) {
        case 'tekuci_mjesec':
            $pocetak = date('Y-m-01');
            $kraj = date('Y-m-t');
            $naziv_perioda = 'Tekući mjesec';
            break;
        case 'prosli_mjesec':
            $pocetak = date('Y-m-01', strtotime('first day of last month'));
            $kraj = date('Y-m-t', strtotime('last day of last month'));
            $naziv_perioda = 'Prošli mjesec';
            break;
        case 'tekuca_godina':
            $pocetak = date('Y-01-01');
            $kraj = date('Y-12-31');
            $naziv_perioda = 'Tekuća godina';
            break;
        case 'prosla_godina':
            $pocetak = date('Y-01-01', strtotime('-1 year'));
            $kraj = date('Y-12-31', strtotime('-1 year'));
            $naziv_perioda = 'Prošla godina';
            break;
        default:
            throw new Exception("Nevažeći period");
    }

    // SQL za detaljnu statistiku po agentima
    $sql = "SELECT 
                a.id,
                a.ime_prezime,
                a.procenat_bonusa,
                COUNT(p.id) as ukupno_transakcija,
                SUM(p.cijena) as ukupan_promet,
                SUM(p.provizija) as ukupna_provizija,
                COUNT(CASE WHEN p.vrsta_ugovora = 'Prodaja' THEN 1 END) as broj_prodaja,
                COUNT(CASE WHEN p.vrsta_ugovora = 'Izdavanje' THEN 1 END) as broj_izdavanja,
                AVG(p.cijena) as prosjecna_cijena,
                AVG(p.provizija) as prosjecna_provizija,
                MAX(p.cijena) as najveca_transakcija,
                MIN(p.cijena) as najmanja_transakcija,
                STD(p.cijena) as std_dev_cijena
            FROM 
                agenti a
                LEFT JOIN promet p ON a.id = p.agent 
                    AND p.datum_ugovora BETWEEN ? AND ?
            WHERE 
                a.status = 'aktivan'
            GROUP BY 
                a.id, a.ime_prezime
            ORDER BY 
                ukupna_provizija DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $pocetak, $kraj);
    $stmt->execute();
    $result = $stmt->get_result();

    // Prikupljanje podataka za rangiranje i statistiku
    $agenti = [];
    $ukupno_transakcija_svi = 0;
    $ukupan_promet_svi = 0;
    $ukupna_provizija_svi = 0;

    while ($row = $result->fetch_assoc()) {
        $agenti[] = $row;
        $ukupno_transakcija_svi += $row['ukupno_transakcija'];
        $ukupan_promet_svi += $row['ukupan_promet'];
        $ukupna_provizija_svi += $row['ukupna_provizija'];
    }

    // Računanje prosječnih vrijednosti
    $broj_agenata = count($agenti);
    $prosjecno_transakcija = $broj_agenata ? $ukupno_transakcija_svi / $broj_agenata : 0;
    $prosjecan_promet = $broj_agenata ? $ukupan_promet_svi / $broj_agenata : 0;
    $prosjecna_provizija = $broj_agenata ? $ukupna_provizija_svi / $broj_agenata : 0;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ../izvjestaji.php');
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-center">Statistika uspješnosti</h2>
            <h4 class="text-center text-muted"><?php echo $naziv_perioda; ?></h4>
        </div>
    </div>

    <!-- Filter forma -->
    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <select name="period" class="form-select">
                                <option value="tekuci_mjesec" <?php echo $period == 'tekuci_mjesec' ? 'selected' : ''; ?>>Tekući mjesec</option>
                                <option value="prosli_mjesec" <?php echo $period == 'prosli_mjesec' ? 'selected' : ''; ?>>Prošli mjesec</option>
                                <option value="tekuca_godina" <?php echo $period == 'tekuca_godina' ? 'selected' : ''; ?>>Tekuća godina</option>
                                <option value="prosla_godina" <?php echo $period == 'prosla_godina' ? 'selected' : ''; ?>>Prošla godina</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Prikaži</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Ukupna statistika -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Prosječan broj transakcija</h5>
                    <h3><?php echo number_format($prosjecno_transakcija, 1); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Prosječan promet</h5>
                    <h3><?php echo number_format($prosjecan_promet, 2); ?> KM</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Prosječna provizija</h5>
                    <h3><?php echo number_format($prosjecna_provizija, 2); ?> KM</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Ukupno aktivnih agenata</h5>
                    <h3><?php echo $broj_agenata; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela uspješnosti -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Detaljna statistika po agentima</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Agent</th>
                                    <th>Ukupno transakcija</th>
                                    <th>Prodaje</th>
                                    <th>Izdavanja</th>
                                    <th>Ukupan promet</th>
                                    <th>Ukupna provizija</th>
                                    <th>Prosječna provizija</th>
                                    <th>Najveća transakcija</th>
                                    <th>Efikasnost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agenti as $index => $agent): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($agent['ime_prezime']); ?></td>
                                    <td><?php echo $agent['ukupno_transakcija']; ?></td>
                                    <td><?php echo $agent['broj_prodaja']; ?></td>
                                    <td><?php echo $agent['broj_izdavanja']; ?></td>
                                    <td><?php echo number_format($agent['ukupan_promet'], 2); ?> KM</td>
                                    <td><?php echo number_format($agent['ukupna_provizija'], 2); ?> KM</td>
                                    <td><?php echo number_format($agent['prosjecna_provizija'], 2); ?> KM</td>
                                    <td><?php echo number_format($agent['najveca_transakcija'], 2); ?> KM</td>
                                    <td>
                                        <?php 
                                        $efikasnost = $prosjecno_transakcija > 0 ? 
                                            ($agent['ukupno_transakcija'] / $prosjecno_transakcija) * 100 : 0;
                                        echo number_format($efikasnost, 1) . '%';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafovi -->
    <div class="row mb-4">
        <!-- Prodaje vs Izdavanja po agentu -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Prodaje vs Izdavanja po agentu</h5>
                </div>
                <div class="card-body">
                    <canvas id="prodajeIzdavanjaGraph"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Raspodjela provizija -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Raspodjela ukupnih provizija</h5>
                </div>
                <div class="card-body">
                    <canvas id="provizijeGraph"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prodaje vs Izdavanja graf
    new Chart(document.getElementById('prodajeIzdavanjaGraph'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($agenti, 'ime_prezime')); ?>,
            datasets: [{
                label: 'Prodaje',
                data: <?php echo json_encode(array_column($agenti, 'broj_prodaja')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)'
            }, {
                label: 'Izdavanja',
                data: <?php echo json_encode(array_column($agenti, 'broj_izdavanja')); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.5)'
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true
                }
            }
        }
    });

    // Provizije pie chart
    new Chart(document.getElementById('provizijeGraph'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($agenti, 'ime_prezime')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($agenti, 'ukupna_provizija')); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)',
                    'rgba(255, 159, 64, 0.5)'
                ]
            }]
        }
    });
});
</script>

<?php include '../footer.php'; ?>