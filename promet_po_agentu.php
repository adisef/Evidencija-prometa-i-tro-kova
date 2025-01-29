<?php
session_start();

define('ROOT_PATH', '../../');
require_once ROOT_PATH . 'includes/db.php';
require_once ROOT_PATH . 'includes/auth.class.php';

// Provjera autentifikacije
$auth = new Auth($conn);
if (!$auth->checkAuth()) {
    header('Location: ' . ROOT_PATH . 'login.php');
    exit;
}

// Dohvat odabranog perioda
$period = $_GET['period'] ?? 'last30';

// Funkcija za određivanje datuma prema periodu
function getPeriodDates($period) {
    switch($period) {
        case 'last30':
            return [
                'start' => date('Y-m-d', strtotime('-30 days')),
                'end' => date('Y-m-d'),
                'label' => 'Posljednjih 30 dana'
            ];
        case 'this_month':
            return [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-d'),
                'label' => 'Ovaj mjesec'
            ];
        case 'last_month':
            return [
                'start' => date('Y-m-01', strtotime('last month')),
                'end' => date('Y-m-t', strtotime('last month')),
                'label' => 'Prošli mjesec'
            ];
        case 'last90':
            return [
                'start' => date('Y-m-d', strtotime('-90 days')),
                'end' => date('Y-m-d'),
                'label' => 'Posljednjih 90 dana'
            ];
        case 'this_year':
            return [
                'start' => date('Y-01-01'),
                'end' => date('Y-m-d'),
                'label' => 'Ova godina'
            ];
        case 'last_year':
            return [
                'start' => date('Y-01-01', strtotime('last year')),
                'end' => date('Y-12-31', strtotime('last year')),
                'label' => 'Prošla godina'
            ];
        default:
            if (preg_match('/^year_(\d{4})$/', $period, $matches)) {
                $year = $matches[1];
                return [
                    'start' => "$year-01-01",
                    'end' => "$year-12-31",
                    'label' => "Godina $year"
                ];
            }
            return [
                'start' => date('Y-m-d', strtotime('-30 days')),
                'end' => date('Y-m-d'),
                'label' => 'Posljednjih 30 dana'
            ];
    }
}

// Dohvat dostupnih godina
$years_sql = "SELECT DISTINCT YEAR(datum_ugovora) as godina 
              FROM promet 
              WHERE agent IS NOT NULL 
              ORDER BY godina DESC";
$years_result = $conn->query($years_sql);
$available_years = $years_result->fetch_all(MYSQLI_ASSOC);

$dates = getPeriodDates($period);

// Dohvat podataka za agente
$sql = "SELECT 
        agent,
        COUNT(*) as broj_ugovora,
        MIN(cijena) as min_cijena,
        AVG(cijena) as avg_cijena,
        MAX(cijena) as max_cijena,
        SUM(provizija) as ukupna_provizija
        FROM promet 
        WHERE agent IS NOT NULL 
        AND datum_ugovora BETWEEN ? AND ?
        GROUP BY agent 
        ORDER BY ukupna_provizija DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $dates['start'], $dates['end']);
$stmt->execute();
$result = $stmt->get_result();
$agents_stats = $result->fetch_all(MYSQLI_ASSOC);

// Sigurnije inicijaliziranje top rezultata
$top_commission = !empty($agents_stats) ? $agents_stats[0] : null;
$top_contracts = !empty($agents_stats) ? array_reduce($agents_stats, function($carry, $item) {
    return ($item['broj_ugovora'] > ($carry['broj_ugovora'] ?? 0)) ? $item : $carry;
}, ['broj_ugovora' => 0]) : null;
$top_property = !empty($agents_stats) ? array_reduce($agents_stats, function($carry, $item) {
    return ($item['max_cijena'] > ($carry['max_cijena'] ?? 0)) ? $item : $carry;
}, ['max_cijena' => 0]) : null;

require_once ROOT_PATH . 'views/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Lijeva kolona - Filter -->
        <div class="col-lg-4">
            <div class="period-menu card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Izaberi period
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="?period=last30" 
                           class="list-group-item list-group-item-action <?php echo $period === 'last30' ? 'active' : ''; ?>">
                            <i class="fas fa-clock me-2"></i>
                            Posljednjih 30 dana
                        </a>
                        <a href="?period=this_month" 
                           class="list-group-item list-group-item-action <?php echo $period === 'this_month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day me-2"></i>
                            Ovaj mjesec
                        </a>
                        <a href="?period=last_month" 
                           class="list-group-item list-group-item-action <?php echo $period === 'last_month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week me-2"></i>
                            Prošli mjesec
                        </a>
                        <a href="?period=last90" 
                           class="list-group-item list-group-item-action <?php echo $period === 'last90' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Posljednjih 90 dana
                        </a>
                        <a href="?period=this_year" 
                           class="list-group-item list-group-item-action <?php echo $period === 'this_year' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar me-2"></i>
                            Ova godina
                        </a>
                        <a href="?period=last_year" 
                           class="list-group-item list-group-item-action <?php echo $period === 'last_year' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check me-2"></i>
                            Prošla godina
                        </a>
                        <?php foreach ($available_years as $year): ?>
                            <a href="?period=year_<?php echo $year['godina']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $period === "year_{$year['godina']}" ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo $year['godina']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desna kolona - Sadržaj -->
        <div class="col-lg-8">
            <!-- Top kartice -->
            <?php if ($top_commission): ?>
            <div class="achievement-card commission-card mb-4">
                <div class="achievement-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="achievement-content">
                    <div class="achievement-title">
                        <h3>Najveća ukupna provizija</h3>
                        <p class="period"><?php echo $dates['label']; ?></p>
                    </div>
                    <div class="achievement-data">
                        <div class="agent-name">
                            <?php echo htmlspecialchars($top_commission['agent']); ?>
                        </div>
                        <div class="value">
                            <?php echo number_format($top_commission['ukupna_provizija'], 2, ',', '.'); ?> KM
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($top_contracts): ?>
            <div class="achievement-card contracts-card mb-4">
                <div class="achievement-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="achievement-content">
                    <div class="achievement-title">
                        <h3>Najveći broj ugovora</h3>
                        <p class="period"><?php echo $dates['label']; ?></p>
                    </div>
                    <div class="achievement-data">
                        <div class="agent-name">
                            <?php echo htmlspecialchars($top_contracts['agent']); ?>
                        </div>
                        <div class="value">
                            <?php echo number_format($top_contracts['broj_ugovora']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($top_property): ?>
            <div class="achievement-card property-card mb-4">
                <div class="achievement-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <div class="achievement-content">
                    <div class="achievement-title">
                        <h3>Najskuplja nekretnina</h3>
                        <p class="period"><?php echo $dates['label']; ?></p>
                    </div>
                    <div class="achievement-data">
                        <div class="agent-name">
                            <?php echo htmlspecialchars($top_property['agent']); ?>
                        </div>
                        <div class="value">
                            <?php echo number_format($top_property['max_cijena'], 2, ',', '.'); ?> KM
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabela agenata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>
                        Pregled po agentima
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Broj ugovora</th>
                                    <th>Min. cijena</th>
                                    <th>Prosj. cijena</th>
                                    <th>Max. cijena</th>
                                    <th>Ukupna provizija</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents_stats as $agent): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agent['agent']); ?></td>
                                        <td><?php echo number_format($agent['broj_ugovora']); ?></td>
                                        <td><?php echo number_format($agent['min_cijena'], 2, ',', '.'); ?> KM</td>
                                        <td><?php echo number_format($agent['avg_cijena'], 2, ',', '.'); ?> KM</td>
                                        <td><?php echo number_format($agent['max_cijena'], 2, ',', '.'); ?> KM</td>
                                        <td><?php echo number_format($agent['ukupna_provizija'], 2, ',', '.'); ?> KM</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.period-menu {
    position: sticky;
    top: 20px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.period-menu .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.period-menu .list-group-item {
    border: none;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
}

.period-menu .list-group-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.period-menu .list-group-item.active {
    background: linear-gradient(135deg, #3498db, #2980b9);
    border: none;
}

.achievement-card {
    position: relative;
    padding: 2rem 9%;
    border-radius: 3px;
    color: white;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.achievement-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    opacity: 0.1;
    background-size: 50%;
    background-repeat: no-repeat;
    background-position: right center;
}

.commission-card {
    background: linear-gradient(135deg, #3498db, #2980b9);
}
.commission-card::before {
    background-image: url('path/to/trophy-icon.svg');
}

.contracts-card {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
}
.contracts-card::before {
    background-image: url('path/to/medal-icon.svg');
}

.property-card {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}
.property-card::before {
    background-image: url('path/to/gem-icon.svg');
}

.achievement-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.achievement-icon {
    position: absolute;
    right: -2rem;
    top: 66%;
    font-size: 8rem;
    opacity: 0.2;
    transform: translateY(-49%) rotate(-11deg);
}

.achievement-title h3 {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
    opacity: 0.9;
}

.achievement-title .period {
    font-size: 0.9rem;
    margin: 0.5rem 0 0;
    opacity: 0.7;
}

.achievement-data {
    text-align: right;
}

.achievement-data .agent-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.achievement-data .value {
    font-size: 2rem;
    font-weight: 800;
}

/* Tabela stilovi */
.table {
    font-size: 0.9rem;
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.table tbody tr:hover {
    background-color: rgba(52, 152, 219, 0.05);
}

/* Responzivnost */
@media (max-width: 992px) {
    .period-menu {
        position: static;
        margin-bottom: 2rem;
    }

    .achievement-content {
        flex-direction: column;
        text-align: center;
    }

    .achievement-data {
        text-align: center;
        margin-top: 1rem;
    }

    .achievement-icon {
        display: none;
    }
}

@media (max-width: 768px) {
    .achievement-card {
        padding: 1.5rem;
    }

    .achievement-data .value {
        font-size: 1.5rem;
    }

    .table-responsive {
        font-size: 0.8rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animacija za achievement kartice
    const cards = document.querySelectorAll('.achievement-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 20px rgba(0,0,0,0.1)';
        });
    });

    // Sortiranje tabele
    document.querySelectorAll('th').forEach(headerCell => {
        headerCell.addEventListener('click', () => {
            const tableBody = document.querySelector('tbody');
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            const index = headerCell.cellIndex;
            
            const sortedRows = rows.sort((a, b) => {
                const aVal = a.cells[index].textContent.trim();
                const bVal = b.cells[index].textContent.trim();
                
                // Ako su brojevi
                if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                    return parseFloat(bVal.replace(/[^\d.-]/g, '')) - 
                           parseFloat(aVal.replace(/[^\d.-]/g, ''));
                }
                
                // Ako je tekst
                return bVal.localeCompare(aVal);
            });
            
            tableBody.append(...sortedRows);
        });
    });
});
</script>
