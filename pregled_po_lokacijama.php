<?php
session_start();

define('ROOT_PATH', '../../');
require_once ROOT_PATH . 'includes/db.php';
require_once ROOT_PATH . 'includes/auth.class.php';

// Provjera autentifikacije
$auth = new Auth($conn); // Promijenjeno iz $mysqli u $conn
if (!$auth->checkAuth()) {
    header('Location: ' . ROOT_PATH . 'login.php');
    exit;
}

class PrometReport {
    private $conn;
    private $datum_od;
    private $datum_do;
    private $tip_nekretnine;
    private $agent;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeParameters();
    }
	
    private function initializeParameters() {
        $this->datum_od = filter_input(INPUT_GET, 'datum_od', FILTER_SANITIZE_STRING) ?? date('Y-m-01');
        $this->datum_do = filter_input(INPUT_GET, 'datum_do', FILTER_SANITIZE_STRING) ?? date('Y-m-t');
        $this->tip_nekretnine = filter_input(INPUT_GET, 'tip_nekretnine', FILTER_SANITIZE_STRING) ?? '';
        $this->agent = filter_input(INPUT_GET, 'agent', FILTER_SANITIZE_STRING) ?? '';
    }

    private function prepareWhereClause() {
        $where = ["datum_ugovora BETWEEN ? AND ?"];
        $params = [$this->datum_od, $this->datum_do];
        $types = "ss";
        
        if (!empty($this->tip_nekretnine)) {
            $where[] = "tip_nekretnine = ?";
            $params[] = $this->tip_nekretnine;
            $types .= "s";
        }
        
        if (!empty($this->agent)) {
            $where[] = "agent = ?";
            $params[] = $this->agent;
            $types .= "s";
        }

        return [
            'where' => implode(" AND ", $where),
            'params' => $params,
            'types' => $types
        ];
    }

    public function getAgenti() {
        $sql = "SELECT DISTINCT agent FROM promet WHERE agent IS NOT NULL ORDER BY agent";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function getTipoviNekretnina() {
        $sql = "SELECT DISTINCT tip_nekretnine FROM promet WHERE tip_nekretnine IS NOT NULL ORDER BY tip_nekretnine";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function getStatistikePoOpcinama() {
        $whereData = $this->prepareWhereClause();
        
        $sql = "SELECT 
                    opcina,
                    COUNT(*) as broj_ugovora,
                    MIN(cijena_po_m2) as min_cijena_m2,
                    AVG(cijena_po_m2) as avg_cijena_m2,
                    MAX(cijena_po_m2) as max_cijena_m2,
                    SUM(provizija) as ukupna_provizija,
                    SUM(cijena) as ukupan_promet
                FROM promet
                WHERE {$whereData['where']}
                GROUP BY opcina
                HAVING opcina IS NOT NULL AND opcina != ''
                ORDER BY broj_ugovora DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($whereData['types'], ...$whereData['params']);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getStatistikePoNaseljima() {
        $whereData = $this->prepareWhereClause();
        
        $sql = "SELECT 
                    naselje,
                    opcina,
                    COUNT(*) as broj_ugovora,
                    MIN(cijena_po_m2) as min_cijena_m2,
                    AVG(cijena_po_m2) as avg_cijena_m2,
                    MAX(cijena_po_m2) as max_cijena_m2,
                    SUM(provizija) as ukupna_provizija,
                    SUM(cijena) as ukupan_promet
                FROM promet
                WHERE {$whereData['where']}
                GROUP BY naselje, opcina
                HAVING naselje IS NOT NULL AND naselje != ''
                ORDER BY broj_ugovora DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($whereData['types'], ...$whereData['params']);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getParameters() {
        return [
            'datum_od' => $this->datum_od,
            'datum_do' => $this->datum_do,
            'tip_nekretnine' => $this->tip_nekretnine,
            'agent' => $this->agent
        ];
    }
}

// Kreiranje instance izvještaja s proslijeđenom konekcijom
$report = new PrometReport($conn); // Promijenjeno iz $mysqli u $conn

try {
    // Dohvatanje podataka
    $params = $report->getParameters();
    $agenti = $report->getAgenti();
    $tipovi_nekretnina = $report->getTipoviNekretnina();
    $opcine_stats = $report->getStatistikePoOpcinama();
    $naselja_stats = $report->getStatistikePoNaseljima();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Greška pri dohvatu podataka: " . $e->getMessage();
    header('Location: ' . ROOT_PATH . 'error.php');
    exit;
}

require_once ROOT_PATH . 'views/header.php';
?>



<style>
/* Dodajte ove stilove u postojeći style tag ili css fajl */
.filter-section {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

/* Novi stilovi za karticu */
.municipality-card {
    position: relative;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    background: white;
    border-radius: 3px;
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;
}

/* Stil za broj ugovora u gornjem desnom uglu */
.contract-count {
    position: absolute;
    top: 1rem;
    right: 1.5rem;
    text-align: right;
}

.count-label {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 0.2rem;
    position: absolute;
    right: 40px;
    top: 24px;
    width: 150px;
}

.count-value {
    font-size: 2.9rem;
    font-weight: 600;
    color: var(--primary);
    line-height: 1;
    position: absolute;
    top: 0;
    right: 0;
}
/* Stil za naziv općine */
.municipality-name {
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
    padding-right: 120px; /* prostor za broj ugovora */
}

/* Stil za ukupnu proviziju */
.total-commission {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
}

.commission-label {
    color: #666;
    margin-right: 1rem;
}
.card {
    border: none;
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;
}
.card-header:first-child {
    border-radius: 3px 3px 0 0;
    border-bottom: 1px solid #eee;
    box-shadow: 0px -1px 1px #eeeeee4f;
    padding: 25px 15px 15px;
}
.commission-value {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--success);
}

/* Stil za red sa cijenama */
.prices-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
}

.price-item {
    flex: 1;
    text-align: left;
    padding: 0 1rem;
    transition: transform 0.2s ease;
    border-left: 1px solid #eee;
}
.price-label i {
    margin-right: 6px;
    font-size: 16px;
}

.price-item:hover {
    transform: translateY(-2px);
}

.price-icon {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.price-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.price-value {
    font-weight: 600;
    font-size: 1.1rem;
}
.brug span {
    font-weight: bold;
}
thead th {
    color: #222 !important;
}

table#settlementsTable {
    box-shadow: none !important;
}
.table>:not(caption)>*>* {
    padding: .7rem .5rem;
    background-color: var(--bs-table-bg);
    border-bottom-width: 1px;
}

/* Dodatne boje za pozadine ikona */
.bg-primary-light {
    background-color: rgba(33, 150, 243, 0.1);
}

.bg-success-light {
    background-color: rgba(76, 175, 80, 0.1);
}

.bg-danger-light {
    background-color: rgba(244, 67, 54, 0.1);
}

/* Responzivnost */
@media (max-width: 768px) {
    .prices-row {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .price-item {
        padding: 0.75rem;
    }
    
    .contract-count {
        position: static;
        text-align: left;
        margin-bottom: 1rem;
    }
    
    .municipality-name {
        padding-right: 0;
    }
    
    .total-commission {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    
    .commission-value {
        margin-top: 0.5rem;
    }
}
.municipality-name {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.stat-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.stat-icon {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    margin-right: 1rem;
}
.main-stats-row {
    display: flex;
    justify-content: space-between;
    gap: 2rem;
    margin-top: 1.5rem;
}

.main-stat {
    flex: 1;
    text-align: center;
}
.stat-value-large {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
    margin-top: 0.5rem;
}

.settlement-name {
    font-weight: 600;
    font-size: 1.1rem;
}

/* Dodajte nove stilove */
.search-container {
    position: relative;
    margin-bottom: 1rem;
}

.input-group {
    box-shadow: 0 0px 2px rgba(0,0,0,0.05);
}

.input-group-text {
    background-color: white;
    border-right: none;
}

#settlementSearch {
    border-left: none;
    padding-left: 0.5rem;
    padding-top: 12px;
    padding-bottom: 12px;
}

#settlementSearch:focus {
    box-shadow: none;
    border-color: #ced4da;
}

#settlementSearch.active-filter {
    background-color: #f8f9fa;
}

.btn-outline-secondary {
    border-color: #ced4da;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #ced4da;
    color: #dc3545;
}

.no-results-message td {
    background-color: #f8f9fa;
    color: #6c757d;
}

/* Animacija za filtriranje */
tbody tr {
    transition: all 0.3s ease;
}

/* Responsive prilagođavanje */
@media (max-width: 768px) {
    .search-container {
        margin-top: 1rem;
    }
}
.stat-info {
    flex-grow: 1;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.stat-value {
    font-weight: 600;
    color: #333;
    font-size: 1.1rem;
}

.settlements-table {
    width: 100%;
}

.settlements-table th {
    cursor: pointer;
    position: relative;
    padding-right: 20px;
}

.settlements-table th:after {
    content: '↕';
    position: absolute;
    right: 5px;
    opacity: 0.5;
}

.settlements-table th.asc:after {
    content: '↑';
    opacity: 1;
}

.settlements-table th.desc:after {
    content: '↓';
    opacity: 1;
}

.action-buttons {
    position: fixed;
    bottom: 30px;
    right: 30px;
    display: flex;
    gap: 10px;
}

.action-button {
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

@media print {
    .filter-section, .action-buttons {
        display: none;
    }
}
/* Stilovi za kartice */
.stats-cards {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    flex: 1;
    background: white;
    border-radius: 3px;
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card-content {
    padding: 1.5rem;
}

.stat-card-header i {
    font-size: 1.3rem;
    margin-right: 1rem;
}

.stat-card-header h6 {
    margin: 0;
    font-size: 1.1rem;
    color: #333333;
    font-weight: 500;
    display: inline;
}
.stat-card-header {
    margin-bottom: 15px;
}

.stat-card-body {
    text-align: center;
}

.stat-value {
    font-size: 1.4rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: left;
    margin-top: 30px;
    width: 50%;
}

.stat-metric {
    color: #666;
    font-size: 1rem;
    text-align: right;
    width: 42%;
    float: right;
    margin-top: -40px;
}

.stat-metric strong {
    color: #333;
    font-size: 1.2rem;
    font-weight: 700;
}

/* Responzivnost */
@media (max-width: 992px) {
    .stats-cards {
        flex-direction: column;
    }

    .stat-card {
        width: 100%;
    }
}

/* Boje za ikone */
.text-primary {
    color: #3498db;
}

.text-success {
    color: #2ecc71;
}

.text-warning {
    color: #f1c40f;
}
</style>

<div class="container-fluid px-4 py-4">
    <!-- Filter Section -->
    <div class="filter-section">
        <form id="filters-form" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">
                    <i class="far fa-calendar-alt me-2"></i>Period
                </label>
                <div class="input-group">
                    <input type="date" class="form-control" name="datum_od" 
                           value="<?php echo $params['datum_od']; ?>">
                    <input type="date" class="form-control" name="datum_do" 
                           value="<?php echo $params['datum_do']; ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">
                    <i class="fas fa-home me-2"></i>Tip nekretnine
                </label>
                <select class="form-select" name="tip_nekretnine">
                    <option value="">Svi tipovi</option>
                    <?php foreach ($tipovi_nekretnina as $tip): ?>
                        <option value="<?php echo htmlspecialchars($tip['tip_nekretnine']); ?>"
                                <?php echo $params['tip_nekretnine'] === $tip['tip_nekretnine'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tip['tip_nekretnine']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">
                    <i class="fas fa-user-tie me-2"></i>Agent
                </label>
                <select class="form-select" name="agent">
                    <option value="">Svi agenti</option>
                    <?php foreach ($agenti as $agent): ?>
                        <option value="<?php echo htmlspecialchars($agent['agent']); ?>"
                                <?php echo $params['agent'] === $agent['agent'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['agent']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Primijeni
                </button>
            </div>
        </form>
    </div>

    <!-- Content Area -->
    <div class="row">
        <!-- Left Column - Municipalities -->
        <div class="col-lg-4">
            <?php foreach ($opcine_stats as $opcina): ?>
				<!-- Kartica općine -->
				<div class="municipality-card">
					<!-- Broj ugovora u gornjem desnom uglu -->
					<div class="contract-count">
						<div class="count-label">Broj ugovora</div>
						<div class="count-value"><?php echo number_format($opcina['broj_ugovora']); ?></div>
					</div>

					<!-- Naziv općine -->
					<div class="municipality-name">
						<i class="fas fa-building me-2"></i>
						<?php echo htmlspecialchars($opcina['opcina']); ?>
					</div>
					
					<!-- Ukupna provizija -->
					<div class="total-commission">
						<i class="fas fa-money-bill-wave me-2 text-success"></i>
						<span class="commission-label">Ukupna ostvarena provizija:</span>
						<span class="commission-value"><?php echo number_format($opcina['ukupna_provizija'], 2, ',', '.'); ?> KM</span>
					</div>
					
					<!-- Cijene po m2 u jednom redu -->
					<div class="prices-row">
						<div class="price-item">
							<div class="price-label"><i class="fas fa-arrow-down text-danger price-icon"></i> Min cijena/m²</div>
							<div class="price-value text-danger">
								<?php echo number_format($opcina['min_cijena_m2'], 2, ',', '.'); ?> KM
							</div>
						</div>
						<div class="price-item">
							<div class="price-label"><i class="fas fa-equals text-primary price-icon"></i> Prosj. cijena/m²</div>
							<div class="price-value text-primary">
								<?php echo number_format($opcina['avg_cijena_m2'], 2, ',', '.'); ?> KM
							</div>
						</div>
						<div class="price-item">
							<div class="price-label"><i class="fas fa-arrow-up text-success price-icon"></i> Max cijena/m²</div>
							<div class="price-value text-success">
								<?php echo number_format($opcina['max_cijena_m2'], 2, ',', '.'); ?> KM
							</div>
						</div>
					</div>
				</div>
            <?php endforeach; ?>

            <!-- Grafikoni -->
			<div class="municipality-card">
				<canvas id="contractsPieChart"></canvas>
			</div>
			<div class="municipality-card">
				<canvas id="commissionPieChart"></canvas>
			</div>
        </div>

        <!-- Right Column - Settlements -->
        <div class="col-lg-8">
            <div class="card">
				<div class="card-header">
					<h5 class="card-title mb-3">
						<i class="fas fa-map-marker-alt me-2"></i>
						Pregled po naseljima
					</h5>
					<div class="search-container">
						<div class="input-group">
							<span class="input-group-text">
								<i class="fas fa-search"></i>
							</span>
							<input type="text" 
								   id="settlementSearch" 
								   class="form-control" 
								   placeholder="Pretraži naselje...">
							<button class="btn btn-outline-secondary" type="button" id="clearSearch">
								<i class="fas fa-times"></i>
							</button>
						</div>
					</div>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-hover settlements-table" id="settlementsTable">
							<thead>
								<tr>
									<th data-sort="naselje">Naselje</th>
                                    <th data-sort="opcina">Općina</th>
                                    <th data-sort="broj_ugovora" class="text-center">Broj ugovora</th>
                                    <th data-sort="min_cijena_m2" class="text-end">Min. cijena/m²</th>
                                    <th data-sort="avg_cijena_m2" class="text-end">Prosj. cijena/m²</th>
                                    <th data-sort="max_cijena_m2" class="text-end">Max. cijena/m²</th>
                                    <th data-sort="ukupna_provizija" class="text-end">Ukupna provizija</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($naselja_stats as $naselje): ?>
                                    <tr>
										<td>
											<i class="fas fa-map-marker-alt me-2 text-primary"></i>
											<span class="settlement-name">
												<?php echo htmlspecialchars($naselje['naselje']); ?>
											</span>
										</td>									
                                        <td><?php echo htmlspecialchars($naselje['opcina']); ?></td>
                                        <td class="text-center brug"><span><?php echo number_format($naselje['broj_ugovora']); ?></span></td>
                                        <td class="text-end"><?php echo number_format($naselje['min_cijena_m2'], 2, ',', '.'); ?> KM</td>
                                        <td class="text-end"><?php echo number_format($naselje['avg_cijena_m2'], 2, ',', '.'); ?> KM</td>
                                        <td class="text-end"><?php echo number_format($naselje['max_cijena_m2'], 2, ',', '.'); ?> KM</td>
                                        <td class="text-end"><?php echo number_format($naselje['ukupna_provizija'], 2, ',', '.'); ?> KM</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
			
			<!-- Najbolji rezultati po naseljima -->
			<div class="row mt-4">
				<div class="col-12">
					<div class="stats-cards">
						<?php
						// Pronalazak naselja s najvećim brojem ugovora
						$max_ugovora = array_reduce($naselja_stats, function($carry, $item) {
							return ($item['broj_ugovora'] > $carry['broj_ugovora']) ? $item : $carry;
						}, ['broj_ugovora' => 0]);

						// Pronalazak naselja s najvećom prosječnom cijenom
						$max_cijena = array_reduce($naselja_stats, function($carry, $item) {
							return ($item['avg_cijena_m2'] > $carry['avg_cijena_m2']) ? $item : $carry;
						}, ['avg_cijena_m2' => 0]);

						// Pronalazak naselja s najvećom provizijom
						$max_provizija = array_reduce($naselja_stats, function($carry, $item) {
							return ($item['ukupna_provizija'] > $carry['ukupna_provizija']) ? $item : $carry;
						}, ['ukupna_provizija' => 0]);
						?>

						<!-- Kartica za najveći broj ugovora -->
						<div class="stat-card">
							<div class="stat-card-content">
								<div class="stat-card-header">
									<i class="fas fa-file-signature text-primary"></i>
									<h6>Najveći broj ugovora</h6>
								</div>
								<div class="stat-card-body">
									<div class="stat-value">
										<?php echo htmlspecialchars($max_ugovora['naselje']); ?>
									</div>
									<div class="stat-metric">
										<strong><?php echo number_format($max_ugovora['broj_ugovora']); ?></strong>
										ugovora
									</div>
								</div>
							</div>
						</div>

						<!-- Kartica za najveću prosječnu cijenu -->
						<div class="stat-card">
							<div class="stat-card-content">
								<div class="stat-card-header">
									<i class="fas fa-chart-line text-success"></i>
									<h6>Najveća prosječna cijena</h6>
								</div>
								<div class="stat-card-body">
									<div class="stat-value">
										<?php echo htmlspecialchars($max_cijena['naselje']); ?>
									</div>
									<div class="stat-metric">
										<strong><?php echo number_format($max_cijena['avg_cijena_m2'], 2, ',', '.'); ?></strong>
										KM/m²
									</div>
								</div>
							</div>
						</div>

						<!-- Kartica za najveću proviziju -->
						<div class="stat-card">
							<div class="stat-card-content">
								<div class="stat-card-header">
									<i class="fas fa-money-bill-wave text-warning"></i>
									<h6>Najveća ukupna provizija</h6>
								</div>
								<div class="stat-card-body">
									<div class="stat-value">
										<?php echo htmlspecialchars($max_provizija['naselje']); ?>
									</div>
									<div class="stat-metric">
										<strong><?php echo number_format($max_provizija['ukupna_provizija'], 2, ',', '.'); ?></strong>
										KM
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>			
			
        </div>
    </div>

	<!-- Gumbi na dnu -->
	<div class="mt-4 mb-3 text-end">
		<button class="btn btn-secondary me-2" onclick="history.back()">
			<i class="fas fa-arrow-left me-2"></i>Nazad
		</button>
		<button class="btn btn-primary" onclick="window.print()">
			<i class="fas fa-print me-2"></i>Print
		</button>
	</div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Automatsko slanje forme pri promjeni filtera
    document.querySelectorAll('#filters-form select, #filters-form input[type="date"]')
        .forEach(element => {
            element.addEventListener('change', () => {
                document.getElementById('filters-form').submit();
            });
        });

    // Inicijalizacija pretrage naselja
    initializeSearchFilter();
    
    // Inicijalizacija sortiranja tabele
    initializeTableSorting();
    
    // Inicijalizacija grafikona
    initializeCharts();
});

// Funkcija za pretragu naselja
function initializeSearchFilter() {
    const searchInput = document.getElementById('settlementSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById('settlementsTable');
        if (!table) return;

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        
        rows.forEach(row => {
            const naseljeCell = row.querySelector('td:first-child');
            if (naseljeCell) {
                const text = naseljeCell.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            }
        });
    });
}

// Funkcija za sortiranje tabele
function initializeTableSorting() {
    document.querySelectorAll('.settlements-table th[data-sort]').forEach(th => {
        th.addEventListener('click', () => {
            const table = th.closest('table');
            if (!table) return;

            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const column = th.cellIndex;
            const isNumeric = column > 1;
            const isAsc = !th.classList.contains('asc');

            // Ažuriranje klasa za sortiranje
            table.querySelectorAll('th').forEach(header => {
                header.classList.remove('asc', 'desc');
            });
            th.classList.add(isAsc ? 'asc' : 'desc');

            // Sortiranje redova
            rows.sort((a, b) => {
                let aVal = a.cells[column].textContent.trim();
                let bVal = b.cells[column].textContent.trim();

                if (isNumeric) {
                    aVal = parseFloat(aVal.replace(/[^\d,-]/g, '').replace(',', '.')) || 0;
                    bVal = parseFloat(bVal.replace(/[^\d,-]/g, '').replace(',', '.')) || 0;
                }

                return isAsc ? 
                    (aVal > bVal ? 1 : -1) : 
                    (aVal < bVal ? 1 : -1);
            });

            // Ponovno postavljanje redova
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

// Funkcija za inicijalizaciju grafikona
function initializeCharts() {
    const opcineStats = <?php echo json_encode($opcine_stats); ?>;
    if (!opcineStats || !opcineStats.length) return;

    // Paleta za broj ugovora (toplije boje)
    const contractColors = [
        '#3498db', // plava
        '#2ecc71', // zelena
        '#9b59b6', // ljubičasta
        '#f1c40f', // žuta
        '#e74c3c', // crvena
        '#1abc9c', // tirkizna
        '#34495e', // tamno plava
        '#e67e22', // narandžasta
        '#16a085', // tamno tirkizna
        '#8e44ad'  // tamno ljubičasta
    ];

    // Nova paleta za provizije (hladnije, profesionalnije boje)
    const commissionColors = [
        '#0099cc', // svijetlo plava
        '#00acc1', // cyan
        '#5c6bc0', // indigo
        '#7e57c2', // ljubičasta
        '#26a69a', // teal
        '#4db6ac', // svijetli teal
        '#66bb6a', // zelena
        '#4caf50', // tamno zelena
        '#43a047', // još tamnija zelena
        '#2e7d32'  // najt. zelena
    ];

    // Zajednička konfiguracija za grafikone
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    font: {
                        family: "'Inter', 'Helvetica Neue', 'Arial', sans-serif",
                        size: 12,
                        weight: '500'
                    },
                    padding: 15,
                    usePointStyle: true, // koristi točke umjesto kvadrata za legende
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#333',
                titleFont: {
                    family: "'Inter', 'Helvetica Neue', 'Arial', sans-serif",
                    size: 14,
                    weight: '600'
                },
                bodyColor: '#666',
                bodyFont: {
                    family: "'Inter', 'Helvetica Neue', 'Arial', sans-serif",
                    size: 13
                },
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 12,
                boxPadding: 6,
                usePointStyle: true
            }
        }
    };

    // Grafikon broja ugovora
    const contractsCtx = document.getElementById('contractsPieChart');
    if (contractsCtx) {
        new Chart(contractsCtx, {
            type: 'doughnut',
            data: {
                labels: opcineStats.map(stat => stat.opcina),
                datasets: [{
                    data: opcineStats.map(stat => stat.broj_ugovora),
                    backgroundColor: contractColors, // Prva paleta
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                ...commonOptions,
                cutout: '60%', // veličina rupe u sredini
                plugins: {
                    ...commonOptions.plugins,
                    title: {
                        display: true,
                        text: 'Broj ugovora po općinama',
                        font: {
                            family: "'Inter', 'Helvetica Neue', 'Arial', sans-serif",
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            top: 20,
                            bottom: 20
                        },
                        color: '#333'
                    }
                }
            }
        });
    }

    // Grafikon provizija
    const commissionCtx = document.getElementById('commissionPieChart');
    if (commissionCtx) {
        new Chart(commissionCtx, {
            type: 'doughnut',
            data: {
                labels: opcineStats.map(stat => stat.opcina),
                datasets: [{
                    data: opcineStats.map(stat => stat.ukupna_provizija),
                    backgroundColor: commissionColors, // Druga paleta
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                ...commonOptions,
                cutout: '60%',
                plugins: {
                    ...commonOptions.plugins,
                    title: {
                        display: true,
                        text: 'Ukupna provizija po općinama',
                        font: {
                            family: "'Inter', 'Helvetica Neue', 'Arial', sans-serif",
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            top: 20,
                            bottom: 20
                        },
                        color: '#333'
                    },
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw.toLocaleString('ba-BA', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                return `${context.label}: ${value} KM`;
                            }
                        }
                    }
                }
            }
        });
    }
}

// Funkcija za printanje
function handlePrint() {
    window.print();
}
</script>

<style media="print">
@page {
    size: A4 landscape;
    margin: 1cm;
}

.action-buttons,
.filter-section {
    display: none !important;
}

.municipality-card {
    page-break-inside: avoid;
    break-inside: avoid;
}

.table {
    font-size: 10px;
}

.stat-value {
    font-size: 12px;
}

.municipality-name {
    font-size: 14px;
}

.card {
    border: none !important;
    box-shadow: none !important;
}

.table > :not(caption) > * > * {
    padding: 4px 8px;
}
</style>

<?php require_once ROOT_PATH . 'views/footer.php'; ?>