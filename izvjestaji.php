<?php
session_start();
require_once 'header.php';

// Inicijalizacija filtera s defaultnim vrijednostima
$filters = [
    'datum_od' => filter_input(INPUT_GET, 'datum_od') ?: date('Y-m-01'),
    'datum_do' => filter_input(INPUT_GET, 'datum_do') ?: date('Y-m-t'),
    'usluga' => filter_input(INPUT_GET, 'usluga'),
    'vrsta_ugovora' => filter_input(INPUT_GET, 'vrsta_ugovora'),
    'agent' => filter_input(INPUT_GET, 'agent'),
    'opcina' => filter_input(INPUT_GET, 'opcina'),
    'naselje' => filter_input(INPUT_GET, 'naselje'),
    'tip_nekretnine' => filter_input(INPUT_GET, 'tip_nekretnine')
];

// Inicijalizacija statistike
$statistika = [
    'ukupno_promet' => 0.0,
    'ukupno_provizija' => 0.0,
    'ukupna_povrsina' => 0.0,
    'broj_transakcija' => 0,
    'broj_prodaja' => 0,
    'broj_izdavanja' => 0,
    'broj_ekskluzivnih' => 0,
    'broj_otvorenih' => 0,
    'prosjecna_cijena_po_m2' => 0.0
];

// Inicijalizacija TOP vrijednosti
$top_rezultati = [
    'najveca_provizija' => [
        'iznos' => 0.0,
        'datum' => '',
        'lokacija' => '',
        'tip_nekretnine' => '',
        'usluga' => '',
        'agent' => '',
        'procenat' => 0.0,
        'povrsina' => 0.0  // Dodano
    ],
    'najskuplja_nekretnina' => [
        'cijena' => 0.0,
        'datum' => '',
        'lokacija' => '',
        'tip_nekretnine' => '',
        'povrsina' => 0.0,
        'cijena_po_m2' => 0.0,
        'agent' => '',
        'usluga' => ''
    ]
];

// Priprema parametara za upite
$params = [];
$types = "";

// Priprema WHERE uslova
$base_where = "WHERE 1=1";

if (!empty($filters['datum_od'])) {
    $base_where .= " AND p.datum_ugovora >= ?";
    $params[] = $filters['datum_od'];
    $types .= "s";
}

if (!empty($filters['datum_do'])) {
    $base_where .= " AND p.datum_ugovora <= ?";
    $params[] = $filters['datum_do'];
    $types .= "s";
}

if (!empty($filters['usluga'])) {
    $base_where .= " AND p.usluga = ?";
    $params[] = $filters['usluga'];
    $types .= "s";
}

if (!empty($filters['vrsta_ugovora'])) {
    $base_where .= " AND p.vrsta_ugovora = ?";
    $params[] = $filters['vrsta_ugovora'];
    $types .= "s";
}

if (!empty($filters['agent'])) {
    $base_where .= " AND p.agent = ?";
    $params[] = $filters['agent'];
    $types .= "s";
}

if (!empty($filters['tip_nekretnine'])) {
    $base_where .= " AND p.tip_nekretnine = ?";
    $params[] = $filters['tip_nekretnine'];
    $types .= "s";
}

if (!empty($filters['opcina'])) {
    $base_where .= " AND p.opcina = ?";
    $params[] = $filters['opcina'];
    $types .= "s";
}

if (!empty($filters['naselje'])) {
    $base_where .= " AND p.naselje = ?";
    $params[] = $filters['naselje'];
    $types .= "s";
}

// Statistika po agentima
$agent_stats = [];
try {
    $agent_sql = "SELECT 
                    p.agent as agent,
                    COUNT(*) as broj_ugovora,
                    SUM(p.cijena) as promet,
                    SUM(p.provizija) as provizija
                  FROM promet p
                  $base_where
                  GROUP BY p.agent
                  ORDER BY promet DESC";
    
    $stmt = $conn->prepare($agent_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $agent_stats[$row['agent']] = [
            'broj_ugovora' => $row['broj_ugovora'],
            'promet' => $row['promet'],
            'provizija' => $row['provizija']
        ];
    }
} catch (Exception $e) {
    error_log("Greška u statistici agenata: " . $e->getMessage());
}

// Statistika po općinama
$opcina_stats = [];
try {
    $opcina_sql = "SELECT 
                     opcina,
                     COUNT(*) as broj_ugovora,
                     SUM(cijena) as promet,
                     SUM(provizija) as provizija
                   FROM promet p
                   $base_where
                   AND opcina IS NOT NULL
                   GROUP BY opcina
                   ORDER BY promet DESC";
    
    $stmt = $conn->prepare($opcina_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $opcina_stats[$row['opcina']] = [
            'broj_ugovora' => $row['broj_ugovora'],
            'promet' => $row['promet'],
            'provizija' => $row['provizija']
        ];
    }
} catch (Exception $e) {
    error_log("Greška u statistici općina: " . $e->getMessage());
}

// Glavni upit za sve transakcije
$sql = "SELECT 
            p.*,
            p.agent as agent_ime,
            ROUND(p.cijena / NULLIF(p.povrsina, 0), 2) as cijena_po_m2
        FROM promet p
        $base_where
        ORDER BY p.datum_ugovora DESC";

// Izvršavanje glavnog upita
try {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $transakcije = [];
    $ukupna_povrsina_za_prosjek = 0;

    // Obrada rezultata glavnog upita
    while ($row = $result->fetch_assoc()) {
        $transakcije[] = $row;
        
        // Ažuriranje statistike
        $statistika['ukupno_promet'] += floatval($row['cijena']);
        $statistika['ukupno_provizija'] += floatval($row['provizija']);
        $statistika['ukupna_povrsina'] += floatval($row['povrsina']);
        $statistika['broj_transakcija']++;
        
        // Brojači za vrste usluga
        if ($row['usluga'] == 'Prodaja') {
            $statistika['broj_prodaja']++;
        } else if ($row['usluga'] == 'Najam') {
            $statistika['broj_izdavanja']++;
        }
        
        // Brojači za vrste ugovora
        if ($row['vrsta_ugovora'] == 'Ekskluzivni') {
            $statistika['broj_ekskluzivnih']++;
        } else {
            $statistika['broj_otvorenih']++;
        }
        
		// Ažuriranje najveće provizije
		if (floatval($row['provizija']) > $top_rezultati['najveca_provizija']['iznos']) {
			$top_rezultati['najveca_provizija'] = [
				'iznos' => floatval($row['provizija']),
				'datum' => $row['datum_ugovora'],
				'lokacija' => $row['opcina'] . ' - ' . $row['naselje'],
				'tip_nekretnine' => $row['tip_nekretnine'],
				'usluga' => $row['usluga'],
				'agent' => $row['agent'],
				'procenat' => (floatval($row['provizija']) / floatval($row['cijena'])) * 100,
				'povrsina' => floatval($row['povrsina']) // Dodano
			];
		}
        
        // Ažuriranje najskuplje nekretnine
        if (floatval($row['cijena']) > $top_rezultati['najskuplja_nekretnina']['cijena']) {
            $top_rezultati['najskuplja_nekretnina'] = [
                'cijena' => floatval($row['cijena']),
                'datum' => $row['datum_ugovora'],
                'lokacija' => $row['opcina'] . ' - ' . $row['naselje'],
                'tip_nekretnine' => $row['tip_nekretnine'],
                'povrsina' => floatval($row['povrsina']),
                'cijena_po_m2' => floatval($row['cijena_po_m2']),
                'agent' => $row['agent'],
                'usluga' => $row['usluga']
            ];
        }
        
        // Dodavanje u sumu za prosječnu cijenu po m2
        if (floatval($row['povrsina']) > 0) {
            $ukupna_povrsina_za_prosjek += floatval($row['povrsina']);
        }
    }

    // Izračun prosječne cijene po m2
    if ($ukupna_povrsina_za_prosjek > 0) {
        $statistika['prosjecna_cijena_po_m2'] = $statistika['ukupno_promet'] / $ukupna_povrsina_za_prosjek;
    }

} catch (Exception $e) {
    error_log("Greška pri dohvatu podataka: " . $e->getMessage());
}
?>


	<div class="container-fluid py-4">
		<div class="page-wrapper d-flex">
			<!-- Filter Section -->
			<div class="filter-sekcija">
				<div class="filter-section mb-4">
					<!-- Zaglavlje - ostaje isto -->
					<div class="d-flex justify-content-between align-items-center mb-4">
						<h2 class="m-0">
							<i class="fas fa-chart-line text-primary me-2"></i>
							Detaljni izvještaj
						</h2>
						<div class="filter-date-info text-muted">
							<i class="far fa-calendar-alt me-1"></i>
							Period: <?php echo date('d.m.Y.', strtotime($filters['datum_od'])); ?> - 
								   <?php echo date('d.m.Y.', strtotime($filters['datum_do'])); ?>
						</div>
					</div>

					<!-- Forma -->
					<form method="GET" class="row g-3">
						<!-- Prvi red - datumi -->
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">
									<i class="far fa-calendar-alt me-1"></i>
									Period od:
								</label>
								<input type="date" class="form-control" name="datum_od" 
									   value="<?php echo htmlspecialchars($filters['datum_od']); ?>">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">
									<i class="far fa-calendar-alt me-1"></i>
									Period do:
								</label>
								<input type="date" class="form-control" name="datum_do" 
									   value="<?php echo htmlspecialchars($filters['datum_do']); ?>">
							</div>
						</div>

						<!-- Vrsta usluge -->
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">
									<i class="fas fa-tasks me-1"></i>
									Vrsta usluge:
								</label>
								<select class="form-select" name="usluga">
									<option value="">Sve usluge</option>
									<option value="Prodaja" <?php echo $filters['usluga'] == 'Prodaja' ? 'selected' : ''; ?>>
										Prodaja
									</option>
									<option value="Najam" <?php echo $filters['usluga'] == 'Najam' ? 'selected' : ''; ?>>
										Najam
									</option>
								</select>
							</div>
						</div>

						<!-- Vrsta ugovora -->
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">
									<i class="fas fa-file-contract me-1"></i>
									Vrsta ugovora:
								</label>
								<select class="form-select" name="vrsta_ugovora">
									<option value="">Svi ugovori</option>
									<option value="Ekskluzivni" <?php echo $filters['vrsta_ugovora'] == 'Ekskluzivni' ? 'selected' : ''; ?>>
										Ekskluzivni
									</option>
									<option value="Otvoreni" <?php echo $filters['vrsta_ugovora'] == 'Otvoreni' ? 'selected' : ''; ?>>
										Otvoreni
									</option>
								</select>
							</div>
						</div>

						<!-- NOVI RED - Tip nekretnine i Agent -->
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">
									<i class="fas fa-home me-1"></i>
									Tip nekretnine:
								</label>
								<select class="form-select" name="tip_nekretnine">
									<option value="">Svi tipovi</option>
									<?php
									try {
										$tip_query = "SELECT DISTINCT tip_nekretnine FROM promet 
													WHERE tip_nekretnine IS NOT NULL 
													ORDER BY tip_nekretnine";
										$tip_result = $conn->query($tip_query);
										
										while ($tip = $tip_result->fetch_assoc()):
											$selected = ($filters['tip_nekretnine'] == $tip['tip_nekretnine']) ? 'selected' : '';
									?>
											<option value="<?php echo htmlspecialchars($tip['tip_nekretnine']); ?>" <?php echo $selected; ?>>
												<?php echo htmlspecialchars($tip['tip_nekretnine']); ?>
											</option>
									<?php 
										endwhile;
									} catch (Exception $e) {
										echo '<option value="">Greška pri učitavanju</option>';
									}
									?>
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">
									<i class="fas fa-user-tie me-1"></i>
									Agent:
								</label>
								<select class="form-select" name="agent">
									<option value="">Svi agenti</option>
									<?php
									$agents_query = "SELECT DISTINCT agent FROM promet WHERE agent IS NOT NULL ORDER BY agent";
									$agents_result = $conn->query($agents_query);
									while ($agent = $agents_result->fetch_assoc()):
										$selected = ($filters['agent'] === $agent['agent']) ? 'selected' : '';
									?>
										<option value="<?php echo htmlspecialchars($agent['agent']); ?>" <?php echo $selected; ?>>
											<?php echo htmlspecialchars($agent['agent']); ?>
										</option>
									<?php endwhile; ?>
								</select>
							</div>
						</div>

						<!-- Prazne kolone za poravnanje -->
						<div class="col-md-6"></div>

						<!-- Submit dugmad (ostaje isto) -->
						<div class="col-12 text-center mt-4">
							<button type="submit" class="btn btn-modern btn-primary">
								<i class="fas fa-filter me-2"></i>
								Primijeni filtere
							</button>
							<a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-modern btn-secondary ms-2">
								<i class="fas fa-undo me-2"></i>
								Resetuj filtere
							</a>
						</div>
					</form>
				</div>
			</div>
			<!-- Glavne statističke kartice -->
			<div class="statistika">
				<div class="row g-4 mb-4">
					<!-- Ukupan promet -->
					<div class="col-xl-3 col-md-6">
						<div class="stats-card" style="background: var(--primary-gradient);">
							<i class="fas fa-money-bill-wave big-icon"></i>
							<div class="stats-info">
								<div class="title">Ukupan promet</div>
								<div class="value"><?php echo number_format($statistika['ukupno_promet'], 2, ',', '.'); ?> KM</div>
								<div class="mt-2 small">
									<i class="fas fa-calculator me-1"></i>
									<?php echo number_format($statistika['prosjecna_cijena_po_m2'], 2, ',', '.'); ?> KM/m²
								</div>
							</div>
						</div>
					</div>

					<!-- Ukupna provizija -->
					<div class="col-xl-3 col-md-6">
						<div class="stats-card" style="background: var(--success-gradient);">
							<i class="fas fa-percentage big-icon"></i>
							<div class="stats-info">
								<div class="title">Ukupna provizija</div>
								<div class="value"><?php echo number_format($statistika['ukupno_provizija'], 2, ',', '.'); ?> KM</div>
								<div class="mt-2 small">
									<i class="fas fa-chart-line me-1"></i>
									<?php 
										$procenat_provizije = ($statistika['ukupno_promet'] > 0) 
											? ($statistika['ukupno_provizija'] / $statistika['ukupno_promet'] * 100) 
											: 0;
										echo number_format($procenat_provizije, 2, ',', '.'); 
									?>%
								</div>
							</div>
						</div>
					</div>

					<!-- Ukupna površina -->
					<div class="col-xl-3 col-md-6">
						<div class="stats-card" style="background: var(--info-gradient);">
							<i class="fas fa-ruler-combined big-icon"></i>
							<div class="stats-info">
								<div class="title">Ukupna površina</div>
								<div class="value"><?php echo number_format($statistika['ukupna_povrsina'], 2, ',', '.'); ?> m²</div>
								<div class="mt-2 small">
									<i class="fas fa-home me-1"></i>
									Prosječno: <?php 
										$prosjek_povrsine = ($statistika['broj_transakcija'] > 0) 
											? ($statistika['ukupna_povrsina'] / $statistika['broj_transakcija']) 
											: 0;
										echo number_format($prosjek_povrsine, 2, ',', '.'); 
									?> m²
								</div>
							</div>
						</div>
					</div>

					<!-- Broj transakcija -->
					<div class="col-xl-3 col-md-6">
						<div class="stats-card" style="background: var(--warning-gradient);">
							<i class="fas fa-file-signature big-icon"></i>
							<div class="stats-info">
								<div class="title">Broj transakcija</div>
								<div class="value"><?php echo $statistika['broj_transakcija']; ?></div>
								<div class="mt-2 small">
									<span class="me-2">
										<i class="fas fa-shopping-cart me-1"></i>
										Prodaja: <?php echo $statistika['broj_prodaja']; ?>
									</span>
									<span>
										<i class="fas fa-key me-1"></i>
										Najam: <?php echo $statistika['broj_izdavanja']; ?>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Dodatne statističke kartice -->
					<!-- Broj prodaja vs izdavanja -->
				<div class="row g-4 mb-4">
					<div class="col-xl-6 col-md-12">
						<div class="card h-100">
							<div class="card-body">
								<h5 class="card-title mb-4">Struktura transakcija</h5>
								<div class="row">
									<!-- Lijeva strana s brojčanim podacima -->
									<div class="col-6">
										<div class="mb-4">
											<div class="p-3 rounded" style="background: var(--primary-light);">
												<div class="d-flex align-items-center">
													<i class="fas fa-key fa-2x text-primary me-3"></i>
													<div class="d-flex align-items-center">
														<h3 class="text-primary mb-0 me-2"><?php echo $statistika['broj_prodaja']; ?></h3>
														<div>
															<div class="text-muted">Prodaja</div>
															<?php if ($statistika['broj_transakcija'] > 0): ?>
																<div class="small">
																	<?php echo number_format(($statistika['broj_prodaja'] / $statistika['broj_transakcija'] * 100), 1); ?>%
																</div>
															<?php endif; ?>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="p-3 rounded" style="background: var(--success-light);">
											<div class="d-flex align-items-center">
												<i class="fas fa-undo fa-2x text-success me-3"></i>
												<div class="d-flex align-items-center">
													<h3 class="text-success mb-0 me-2"><?php echo $statistika['broj_izdavanja']; ?></h3>
													<div>
														<div class="text-muted">Najam</div>
														<?php if ($statistika['broj_transakcija'] > 0): ?>
															<div class="small">
																<?php echo number_format(($statistika['broj_izdavanja'] / $statistika['broj_transakcija'] * 100), 1); ?>%
															</div>
														<?php endif; ?>
													</div>
												</div>
											</div>
										</div>
									</div>
									
									<!-- Desna strana s grafičkim prikazom -->
									<div class="col-6 d-flex align-items-center justify-content-center">
										<div style="width: 200px; height: 200px;">
											<canvas id="transactionsPieChart"></canvas>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Ekskluzivni vs otvoreni -->
					<div class="col-xl-6 col-md-12">
						<div class="card h-100">
							<div class="card-body">
								<h5 class="card-title mb-4">Vrste ugovora</h5>
								<div class="row">
									<!-- Lijeva strana s brojčanim podacima -->
									<div class="col-6">
										<div class="mb-4">
											<div class="p-3 rounded" style="background: var(--info-light);">
												<div class="d-flex align-items-center">
													<i class="fas fa-star fa-2x text-info me-3"></i>
													<div class="d-flex align-items-center">
														<h3 class="text-info mb-0 me-2"><?php echo $statistika['broj_ekskluzivnih']; ?></h3>
														<div>
															<div class="text-muted">Ekskluzivni</div>
															<?php if ($statistika['broj_transakcija'] > 0): ?>
																<div class="small">
																	<?php echo number_format(($statistika['broj_ekskluzivnih'] / $statistika['broj_transakcija'] * 100), 1); ?>%
																</div>
															<?php endif; ?>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="p-3 rounded" style="background: var(--warning-light);">
											<div class="d-flex align-items-center">
												<i class="fas fa-lock-open fa-2x text-warning me-3"></i>
												<div class="d-flex align-items-center">
													<h3 class="text-warning mb-0 me-2"><?php echo $statistika['broj_otvorenih']; ?></h3>
													<div>
														<div class="text-muted">Otvoreni</div>
														<?php if ($statistika['broj_transakcija'] > 0): ?>
															<div class="small">
																<?php echo number_format(($statistika['broj_otvorenih'] / $statistika['broj_transakcija'] * 100), 1); ?>%
															</div>
														<?php endif; ?>
													</div>
												</div>
											</div>
										</div>
									</div>
									
									<!-- Desna strana s grafičkim prikazom -->
									<div class="col-6 d-flex align-items-center justify-content-center">
										<div style="width: 200px; height: 200px;">
											<canvas id="contractTypesPieChart"></canvas>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					</div>	
				<!-- Top rezultati -->
				<div class="row g-4 mb-4">
					<!-- Najveća provizija -->
					<div class="col-md-6">
						<div class="card h-100 border-0 shadow-sm">
							<div class="card-header" style="background: #fff;color: #222;padding: 15px;border: none;">
								<h5 class="card-title m-0">
									<i class="fas fa-trophy me-2"></i>
									Najveća ostvarena provizija
								</h5>
							</div>
							<div class="card-body">
								<div class="row align-items-center">
									<div class="col-auto">
										<div class="rounded-circle p-3" style="background: var(--purple-light);">
											<i class="fas fa-percentage fa-2x text-purple"></i>
										</div>
									</div>
									<div class="col">
										<h3 class="text-purple mb-3">
											<?php echo number_format($top_rezultati['najveca_provizija']['iznos'], 2, ',', '.'); ?> KM
											<small class="text-muted fs-6">
												(<?php echo number_format($top_rezultati['najveca_provizija']['procenat'], 1); ?>%)
											</small>
										</h3>
										
										<!-- Prvi red podataka -->
										<div class="row mb-3">
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="far fa-calendar-alt me-2"></i>
													<span><?php echo date('d.m.Y.', strtotime($top_rezultati['najveca_provizija']['datum'])); ?></span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-map-marker-alt me-2"></i>
													<span><?php echo htmlspecialchars($top_rezultati['najveca_provizija']['lokacija']); ?></span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-home me-2"></i>
													<span><?php echo htmlspecialchars($top_rezultati['najveca_provizija']['tip_nekretnine']); ?></span>
												</div>
											</div>
										</div>

										<!-- Drugi red podataka -->
										<div class="row">
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-ruler me-2"></i>
													<span><?php echo number_format($top_rezultati['najveca_provizija']['povrsina'], 2, ',', '.'); ?> m²</span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-user-tie me-2"></i>
													<span><?php echo htmlspecialchars($top_rezultati['najveca_provizija']['agent']); ?></span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-tag me-2"></i>
													<span class="badge <?php echo $top_rezultati['najveca_provizija']['usluga'] == 'Prodaja' ? 'bg-primary' : 'bg-success'; ?>">
														<?php echo htmlspecialchars($top_rezultati['najveca_provizija']['usluga']); ?>
													</span>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Najskuplja nekretnina -->
					<div class="col-md-6">
						<div class="card h-100 border-0 shadow-sm">
							<div class="card-header" style="background: #fff;color: #222;padding: 15px;border: none;">
								<h5 class="card-title m-0">
									<i class="fas fa-gem me-2"></i>
									Najskuplja nekretnina
								</h5>
							</div>
							<div class="card-body">
								<div class="row align-items-center">
									<div class="col-auto">
										<div class="rounded-circle p-3" style="background: var(--danger-light);">
											<i class="fas fa-home fa-2x text-danger"></i>
										</div>
									</div>
									<div class="col">
										<h3 class="text-danger mb-3">
											<?php echo number_format($top_rezultati['najskuplja_nekretnina']['cijena'], 2, ',', '.'); ?> KM
										</h3>
										
										<!-- Prvi red podataka -->
										<div class="row mb-3">
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="far fa-calendar-alt me-2"></i>
													<span><?php echo date('d.m.Y.', strtotime($top_rezultati['najskuplja_nekretnina']['datum'])); ?></span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-map-marker-alt me-2"></i>
													<span><?php echo htmlspecialchars($top_rezultati['najskuplja_nekretnina']['lokacija']); ?></span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-home me-2"></i>
													<span><?php echo htmlspecialchars($top_rezultati['najskuplja_nekretnina']['tip_nekretnine']); ?></span>
												</div>
											</div>
										</div>

										<!-- Drugi red podataka -->
										<div class="row">
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-ruler me-2"></i>
													<span>
														<?php echo number_format($top_rezultati['najskuplja_nekretnina']['povrsina'], 2, ',', '.'); ?> m²
														<small class="text-muted ms-1">
															(<?php echo number_format($top_rezultati['najskuplja_nekretnina']['cijena_po_m2'], 2, ',', '.'); ?> KM/m²)
														</small>
													</span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-user-tie me-2"></i>
													<span><?php echo htmlspecialchars($top_rezultati['najskuplja_nekretnina']['agent']); ?></span>
												</div>
											</div>
											<div class="col-4">
												<div class="d-flex align-items-center">
													<i class="fas fa-tag me-2"></i>
													<span class="badge <?php echo $top_rezultati['najskuplja_nekretnina']['usluga'] == 'Prodaja' ? 'bg-primary' : 'bg-success'; ?>">
														<?php echo htmlspecialchars($top_rezultati['najskuplja_nekretnina']['usluga']); ?>
													</span>
												</div>
											</div>

										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
					<div class="row g-4 mb-4">
						<!-- Statistika po agentima -->
						<div class="col-md-6">
							<div class="card h-100 border-0 shadow-sm">
								<div class="card-header" style="background: #fff;color: #222;padding: 15px;border: none;">
									<h5 class="card-title mb-0">
										<i class="fas fa-user-tie me-2"></i>
										Promet po agentima
									</h5>
								</div>
								<div class="card-body p-0">
									<div class="table-responsive">
										<table class="table table-hover table-custom mb-0">
											<thead>
												<tr>
													<th class="border-0">Agent</th>
													<th class="border-0 text-center">Ugovori</th>
													<th class="border-0 text-end">Promet</th>
													<th class="border-0 text-end">Provizija</th>
												</tr>
											</thead>
											<tbody>
												<?php if (!empty($agent_stats)): ?>
													<?php foreach ($agent_stats as $agent => $stat): ?>
														<?php $procenat = ($stat['promet'] > 0) ? ($stat['provizija'] / $stat['promet'] * 100) : 0; ?>
														<tr>
															<td>
																<div class="d-flex align-items-center">
																	<?php echo htmlspecialchars($agent); ?>
																</div>
															</td>
															<td class="text-center">
																<span class="badge bg-primary"><?php echo $stat['broj_ugovora']; ?></span>
															</td>
															<td class="text-end"><?php echo number_format($stat['promet'], 2, ',', '.'); ?> KM</td>
															<td class="text-end text-success"><?php echo number_format($stat['provizija'], 2, ',', '.'); ?> KM</td>
														</tr>
													<?php endforeach; ?>
												<?php else: ?>
													<tr>
														<td colspan="5" class="text-center">
															<div class="alert alert-info mb-0">Nema podataka za prikaz</div>
														</td>
													</tr>
												<?php endif; ?>
											</tbody>
											<tfoot class="bg-light">
												<tr>
													<td colspan="2"><strong>UKUPNO:</strong></td>
													<td class="text-end">
														<strong><?php echo number_format($statistika['ukupno_promet'], 2, ',', '.'); ?> KM</strong>
													</td>
													<td class="text-end text-success">
														<strong><?php echo number_format($statistika['ukupno_provizija'], 2, ',', '.'); ?> KM</strong>
													</td>
												</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
						<!-- Statistika po općinama -->
						<div class="col-md-6">
							<div class="card h-100 border-0 shadow-sm">
								<div class="card-header" style="background: #fff;color: #222;padding: 15px;border: none;">
									<h5 class="card-title mb-0">
										<i class="fas fa-map-marked-alt me-2"></i>
										Promet po općinama
									</h5>
								</div>
								<div class="card-body p-0">
									<div class="table-responsive">
										<table class="table table-hover table-custom mb-0">
											<thead>
												<tr>
													<th class="border-0">Općina</th>
													<th class="border-0 text-center">Ugovori</th>
													<th class="border-0 text-end">Promet</th>
													<th class="border-0 text-end">Provizija</th>
												</tr>
											</thead>
											<tbody>
												<?php if (!empty($opcina_stats)): ?>
													<?php foreach ($opcina_stats as $opcina => $stat): ?>
														<?php $procenat = ($stat['promet'] > 0) ? ($stat['provizija'] / $stat['promet'] * 100) : 0; ?>
														<tr>
															<td>
																<div class="d-flex align-items-center">
																	<div class="icon-circle-sm bg-forest-light text-forest me-2">
																		<i class="fas fa-map-marker-alt"></i>
																	</div>
																	<?php echo htmlspecialchars($opcina); ?>
																</div>
															</td>
															<td class="text-center">
																<span class="badge bg-primary"><?php echo $stat['broj_ugovora']; ?></span>
															</td>
															<td class="text-end"><?php echo number_format($stat['promet'], 2, ',', '.'); ?> KM</td>
															<td class="text-end text-success"><?php echo number_format($stat['provizija'], 2, ',', '.'); ?> KM</td>
														</tr>
													<?php endforeach; ?>
												<?php else: ?>
													<tr>
														<td colspan="5" class="text-center">
															<div class="alert alert-info mb-0">Nema podataka za prikaz</div>
														</td>
													</tr>
												<?php endif; ?>
											</tbody>
											<tfoot class="bg-light">
												<tr>
													<td colspan="2"><strong>UKUPNO:</strong></td>
													<td class="text-end">
														<strong><?php echo number_format($statistika['ukupno_promet'], 2, ',', '.'); ?> KM</strong>
													</td>
													<td class="text-end text-success">
														<strong><?php echo number_format($statistika['ukupno_provizija'], 2, ',', '.'); ?> KM</strong>
													</td>
												</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
				</div>
				<!-- Lista svih transakcija -->
				<div class="card border-0 shadow-sm mb-4">
					<div class="" style="border: none; padding: 20px;">
						<div class="d-flex justify-content-between align-items-center">
							<h5 class="card-title mb-0">
								<i class="fas fa-list me-2"></i>
								Lista svih transakcija
							</h5>
							<div class="text-muted small">
								Ukupno prikazano: <?php echo count($transakcije); ?> transakcija
							</div>
						</div>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-hover table-custom mb-0" id="transakcije-table">
								<thead>
									<tr>
										<th>Datum</th>
										<th>Tip nekretnine</th>
										<th>Lokacija</th>
										<th>Vrsta</th>
										<th>Ugovor</th>
										<th>Agent</th>										
										<th class="text-end">Površina</th>
										<th class="text-end">Cijena</th>
										<th class="text-end">Cijena/m²</th>
										<th class="text-end">Provizija</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($transakcije as $t): ?>
									<tr>
										<td><?php echo date('d.m.Y.', strtotime($t['datum_ugovora'])); ?></td>
										<td>
											<span class="badge bg-light text-dark">
												<?php echo htmlspecialchars($t['tip_nekretnine']); ?>
											</span>
										</td>
										<td>
											<div class="d-flex flex-column">
												<span class="text-muted small">
													<?php echo htmlspecialchars($t['opcina']); ?>
												</span>
												<span>
													<?php echo htmlspecialchars($t['naselje'] . ', ' . $t['ulica']); ?>
												</span>
											</div>
										</td>
										<td>
											<span class="badge <?php echo $t['usluga'] == 'Prodaja' ? 'bg-primary' : 'bg-success'; ?>">
												<?php echo htmlspecialchars($t['usluga']); ?>
											</span>
										</td>
										<td>
											<span class="badge <?php echo $t['vrsta_ugovora'] == 'Ekskluzivni' ? 'bg-info' : 'bg-warning'; ?>">
												<?php echo htmlspecialchars($t['vrsta_ugovora']); ?>
											</span>
										</td>
										<td>
											<div class="d-flex align-items-center">
												<?php echo htmlspecialchars($t['agent_ime']); ?>
											</div>
										</td>										
										<td class="text-end">
											<?php echo number_format($t['povrsina'], 2, ',', '.'); ?> m²
										</td>
										<td class="text-end fw-bold">
											<?php echo number_format($t['cijena'], 2, ',', '.'); ?> KM
										</td>
										<td class="text-end">
											<?php echo number_format($t['cijena_po_m2'], 2, ',', '.'); ?> KM
										</td>
										<td class="text-end text-success fw-bold">
											<?php echo number_format($t['provizija'], 2, ',', '.'); ?> KM
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
								<tfoot class="bg-light fw-bold">
									<tr>
										<td colspan="4">UKUPNO:</td>
										<td class="text-end">
											<?php echo number_format($statistika['ukupno_promet'], 2, ',', '.'); ?> KM
										</td>
										<td></td>
										<td colspan="3"></td>
										<td class="text-end text-success">
											<?php echo number_format($statistika['ukupno_provizija'], 2, ',', '.'); ?> KM
										</td>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>

				<!-- Action Buttons i Print Footer -->
				<div class="mb-4 d-print-none">
					<button onclick="handlePrint()" class="btn btn-modern btn-primary">
						<i class="fas fa-print me-2"></i>
						Print izvještaja
					</button>
					<a href="javascript:history.back()" class="btn btn-modern btn-secondary">
						<i class="fas fa-arrow-left me-2"></i>
						Nazad
					</a>
				</div>

				<!-- Print Footer -->
				<div class="d-none d-print-block mt-5">
					<hr>
					<div class="row">
						<div class="col-6">
							<small class="text-muted">
								Datum izvještaja: <?php echo date('d.m.Y. H:i'); ?>
							</small>
						</div>
						<div class="col-6 text-end">
							<small class="text-muted">
								Generisao: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'N/A'); ?>
							</small>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div><!-- kraj container-fluid -->
		<style>
			:root {
				--primary-gradient: linear-gradient(45deg, #4e73df, #224abe);
				--success-gradient: linear-gradient(45deg, #1cc88a, #13855c);
				--info-gradient: linear-gradient(45deg, #36b9cc, #258391);
				--warning-gradient: linear-gradient(45deg, #f6c23e, #dda20a);
				--danger-gradient: linear-gradient(45deg, #e74a3b, #be2617);
				--purple-gradient: linear-gradient(45deg, #6f42c1, #4e2b89);
				--forest-gradient: linear-gradient(45deg, #2d4f3c, #1a2f24);
				
				--primary-light: rgba(78, 115, 223, 0.1);
				--success-light: rgba(28, 200, 138, 0.1);
				--info-light: rgba(54, 185, 204, 0.1);
				--warning-light: rgba(246, 194, 62, 0.1);
				--danger-light: rgba(231, 74, 59, 0.1);
				--purple-light: rgba(111, 66, 193, 0.1);
				--forest-light: rgba(45, 79, 60, 0.1);
			}

			body {
				min-height: 100vh;
				display: flex;
				flex-direction: column;
			}

			.container-fluid {
				flex: 1;
			}

			.page-wrapper {
				min-height: 100%;
				gap: 25px;
			}

			.filter-sekcija {
				width: 30%;
				position: sticky;
				top: 20px;
				height: fit-content;
				max-height: calc(100vh - 40px);
				overflow-y: auto;
				padding: 15px;
				background-color: #fff;
				border-radius: 0.25rem;
				box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
				margin-left: 12px;
			}

			.statistika {
				width: 67%;
				box-sizing: border-box;
			}

			.footer {
				margin-top: auto;
				width: 100%;
			}
			tfoot.bg-light tr {
				background: #fff;
			}
			.d-flex.align-items-center h3 {
				font-size: 34px;
			}

			@media (max-width: 992px) {
				.page-wrapper {
					flex-direction: column;
				}

				.filter-sekcija,
				.statistika {
					width: 100%;
					margin: 0 0 20px 0;
				}

				.filter-sekcija {
					position: relative;
					max-height: none;
				}
			}

			/* Filter sekcija */
			.filter-section {
				background: white;
				border-radius: 3px;
				padding: 15px;
				margin-bottom: 2rem;
			}

			.form-label {
				font-weight: 600;
				color: #4e73df;
				margin-bottom: 0.5rem;
				font-size: 0.9rem;
			}

			.form-control, .form-select {
				border-radius: 3px;
				border: 1px solid #d1d3e2;
				padding: 0.75rem 1rem;
				font-size: 0.9rem;
				transition: all 0.2s ease;
			}

			.form-control:focus, .form-select:focus {
				border-color: #4e73df;
				box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
			}

			/* Kartice statistike */
			.stats-card {
				color: white;
				padding: 1.5rem;
				border-radius: 3px;
				position: relative;
				overflow: hidden;
				transition: transform 0.3s ease;
				min-height: 140px;
				box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
			}
			h2.m-0 {
				width: 100%;
				text-align: left;
				margin: 15px 0 !important;
			}
			.stats-card:hover {
				transform: translateY(-5px);
			}

			.stats-card i.big-icon {
				position: absolute;
				right: -10px;
				bottom: -20px;
				font-size: 7rem;
				opacity: 0.1;
				transform: rotate(-15deg);
			}

			.stats-info {
				position: relative;
				z-index: 1;
			}

			.stats-card .title {
				font-size: 0.8rem;
				text-transform: uppercase;
				letter-spacing: 0.1em;
				margin-bottom: 0.5rem;
				opacity: 0.9;
			}
			.table-responsive {
				padding: 15px;
			}
			.table-custom td {
				border-bottom: none;
			}
			table.table.table-hover.table-custom.mb-0 {
				box-shadow: none;
				border-radius: 0 !important;
				margin: 10px 0;
			}
			.table>:not(:first-child) {
				border-top: 1px solid currentColor;
			}
			.stats-card .value {
				font-size: 1.8rem;
				font-weight: 700;
				margin-bottom: 0;
			}

			/* Dugmad */
			.btn-modern {
				border-radius: 30px;
				padding: 0.75rem 1.5rem;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				transition: all 0.3s ease;
				font-size: 0.9rem;
				max-width: 45%;
			}

			.btn-modern:hover {
				transform: translateY(-2px);
				box-shadow: 0 5px 15px rgba(0,0,0,0.1);
			}
			.card.h-100 {
				border: none;
				border-radius: 3px;
				box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;
			}
			
			.rounded-circle.p-3 {
				width: 60px;
				height: 60px;
				text-align: center;
			}
			.rounded-circle.p-3 i {
				margin: 0 auto;
				font-size: 26px;
			}
			/* Tabele */
			.table-custom {
				margin-bottom: 0;
			}
			.card.border-0.shadow-sm.mb-4 {
				max-width: 1380px;
				margin: 0 auto;
			}
			.table-custom thead th {
				background-color: #f8f9fc;
				border-bottom: 2px solid #e3e6f0;
				font-weight: 600;
				font-size: 0.9rem;
				text-transform: uppercase;
				letter-spacing: 0.05em;
				color: #222;
			}

			.table-custom td {
				vertical-align: middle;
				padding: 1rem  3px;
				border-bottom: 1px solid #e3e6f0;
			}
			.p-3 .d-flex.align-items-center i {
				font-size: 22px;
			}

			/* Bedževi */
			.badge {
				padding: 0.5em 0.8em;
				font-weight: 600;
				font-size: 0.75rem;
				border-radius: 30px;
			}

			/* Responzivnost */
			@media (max-width: 768px) {
				.filter-section {
					padding: 1rem;
				}

				.stats-card {
					min-height: auto;
					margin-bottom: 1rem;
				}

				.btn-modern {
					width: 100%;
					margin-bottom: 0.5rem;
				}
			}
			
			.mb-4.d-print-none {
				max-width: 1380px;
				margin: 0 auto;
				text-align: right;
				padding: 25px 0;
			}

			/* Print stilovi */
			@media print {
				@page {
					size: landscape;
					margin: 1cm;
				}

				body {
					padding: 0 !important;
					margin: 0 !important;
				}

				.container-fluid {
					width: 100% !important;
					padding: 0 !important;
					margin: 0 !important;
				}

				.d-print-none {
					display: none !important;
				}

				.stats-card {
					break-inside: avoid;
					border: 1px solid #dee2e6 !important;
					background: none !important;
					color: #000 !important;
				}

				.table-custom {
					font-size: 10pt;
				}

				.badge {
					border: 1px solid #dee2e6 !important;
					background: none !important;
					color: #000 !important;
				}
				.col-md-3 {
					width: 16.5% !important;
				}
			}
		</style>
		<!-- Bootstrap JS -->
		<script>
		// Funkcija za print
		function handlePrint() {
			const style = document.createElement('style');
			style.textContent = `
				@media print {
					@page {
						size: landscape;
						margin: 1cm;
					}
					body {
						padding: 0 !important;
						margin: 0 !important;
					}
					.container-fluid {
						width: 100% !important;
						padding: 0 !important;
						margin: 0 !important;
					}
					.d-print-none {
						display: none !important;
					}
					.card {
						break-inside: avoid;
						border: none !important;
						box-shadow: none !important;
					}
					.table {
						font-size: 11px !important;
						width: 100% !important;
					}
					.table td, .table th {
						padding: 0.4rem !important;
					}
					.badge {
						border: 1px solid #dee2e6 !important;
						background: none !important;
						color: #000 !important;
					}
				}
			`;
			document.head.appendChild(style);
			window.print();
			document.head.removeChild(style);
		}

		// Inicijalizacija dinamičnog učitavanja naselja
		document.addEventListener('DOMContentLoaded', function() {
			const opcinaSelect = document.getElementById('opcina');
			const naseljeSelect = document.getElementById('naselje');
			
			if (opcinaSelect && naseljeSelect) {
				opcinaSelect.addEventListener('change', function() {
					naseljeSelect.innerHTML = '<option value="">Sva naselja</option>';
					if (!this.value) return;
					
					naseljeSelect.disabled = true;
					const loadingOption = document.createElement('option');
					loadingOption.text = 'Učitavanje...';
					naseljeSelect.add(loadingOption);

					fetch(`get_naselja.php?opcina=${encodeURIComponent(this.value)}`)
						.then(response => response.json())
						.then(naselja => {
							naseljeSelect.innerHTML = '<option value="">Sva naselja</option>';
							naselja.forEach(naselje => {
								const option = document.createElement('option');
								option.value = naselje;
								option.textContent = naselje;
								naseljeSelect.appendChild(option);
							});
						})
						.catch(error => {
							console.error('Greška pri učitavanju naselja:', error);
							naseljeSelect.innerHTML = '<option value="">Greška pri učitavanju</option>';
						})
						.finally(() => {
							naseljeSelect.disabled = false;
						});
				});
			}
		});
		</script>
		<!-- Skripra za inicijalizaciju pie charta -->
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const ctx = document.getElementById('transactionsPieChart').getContext('2d');
			
			// Izračun postotaka
			const prodajaPostotak = <?php echo ($statistika['broj_transakcija'] > 0) ? 
				($statistika['broj_prodaja'] / $statistika['broj_transakcija'] * 100) : 0; ?>;
			const NajamPostotak = <?php echo ($statistika['broj_transakcija'] > 0) ? 
				($statistika['broj_izdavanja'] / $statistika['broj_transakcija'] * 100) : 0; ?>;
			
			new Chart(ctx, {
				type: 'pie',
				data: {
					labels: ['Prodaja', 'Najam'],
					datasets: [{
						data: [prodajaPostotak, NajamPostotak],
						backgroundColor: [
							'rgba(0, 123, 255, 0.7)', // primary color
							'rgba(40, 167, 69, 0.7)'  // success color
						],
						borderColor: [
							'rgba(0, 123, 255, 1)',
							'rgba(40, 167, 69, 1)'
						],
						borderWidth: 1
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 12
							}
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									return context.label + ': ' + context.parsed.toFixed(1) + '%';
								}
							}
						}
					}
				}
			});
		});
		</script>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const ctxContracts = document.getElementById('contractTypesPieChart').getContext('2d');
			
			// Izračun postotaka
			const ekskluzivniPostotak = <?php echo ($statistika['broj_transakcija'] > 0) ? 
				($statistika['broj_ekskluzivnih'] / $statistika['broj_transakcija'] * 100) : 0; ?>;
			const otvoreniPostotak = <?php echo ($statistika['broj_transakcija'] > 0) ? 
				($statistika['broj_otvorenih'] / $statistika['broj_transakcija'] * 100) : 0; ?>;
			
			new Chart(ctxContracts, {
				type: 'pie',
				data: {
					labels: ['Ekskluzivni', 'Otvoreni'],
					datasets: [{
						data: [ekskluzivniPostotak, otvoreniPostotak],
						backgroundColor: [
							'rgba(23, 162, 184, 0.7)', // info color
							'rgba(255, 193, 7, 0.7)'   // warning color
						],
						borderColor: [
							'rgba(23, 162, 184, 1)',
							'rgba(255, 193, 7, 1)'
						],
						borderWidth: 1
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 12
							}
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									return context.label + ': ' + context.parsed.toFixed(1) + '%';
								}
							}
						}
					}
				}
			});
		});
		</script>
		
	<?php 
	if (isset($stmt)) $stmt->close();
	$conn->close();
	include 'footer.php'; 
	?>