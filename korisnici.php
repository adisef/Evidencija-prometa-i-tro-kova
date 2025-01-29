<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.class.php';

$auth = new Auth($conn);

// Provjera autentifikacije i admin prava
if (!$auth->checkAuth()) {
    header('Location: ../login.php');
    exit;
}

if (!$auth->hasPermission('admin')) {
    header('Location: ../unauthorized.php');
    exit;
}

// Generiranje CSRF tokena ako ne postoji
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Definiranje parametara za sortiranje i filtriranje
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Validacija sort parametara
$allowed_columns = ['id', 'username', 'ime', 'prezime', 'email', 'uloga', 'status', 'last_login'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'id';
}
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Priprema SQL upita sa pretragom
$sql = "SELECT id, username, ime, prezime, email, uloga, status, is_protected, last_login 
        FROM korisnici 
        WHERE (username LIKE ? OR ime LIKE ? OR prezime LIKE ? OR email LIKE ?)
        ORDER BY $sort_column $sort_order";

$stmt = $conn->prepare($sql);
$search_term = "%$search%";
$stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

// Dobijanje ukupnog broja korisnika
$total_users = $result->num_rows;

include '../views/header.php';

// Dodajemo search box i informacije o broju korisnika
?>
<div class="container mt-4">
    <!-- Search box -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form class="d-flex" method="GET">
                <input class="form-control me-2" type="search" placeholder="Pretraži korisnike..." 
                       name="search" value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-primary" type="submit">Pretraži</button>
                <?php if (!empty($search)): ?>
                    <a href="korisnici.php" class="btn btn-outline-secondary ms-2">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <span class="text-muted">
                Ukupno korisnika: <?php echo $total_users; ?>
            </span>
        </div>
    </div>

    <!-- Success i Error poruke -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

<div class="container mt-4">
    <!-- Success i Error poruke -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Upravljanje korisnicima sistema</h2>
        <?php if ($auth->hasPermission('admin')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noviKorisnikModal">
            <i class="fas fa-user-plus"></i> Novi korisnik
        </button>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
               <table class="table table-striped">
					<thead>
						<tr>
							<th>Korisničko ime</th>
							<th>Ime i prezime</th>
							<th>Email</th>
							<th>Ovlaštenja</th>
							<th>Status</th>
							<th>Zadnja prijava</th>
							<th>Uredi</th>
						</tr>
					</thead>
					<tbody>
						<?php while ($user = $result->fetch_assoc()): ?>
						<tr>
							<td><?php echo htmlspecialchars($user['username']); ?></td>
							<td><?php echo htmlspecialchars($user['ime'] . ' ' . $user['prezime']); ?></td>
							<td><?php echo htmlspecialchars($user['email']); ?></td>
							<td>
								<span class="badge bg-<?php 
									echo $user['uloga'] === 'super_admin' ? 'danger' : 
										($user['uloga'] === 'admin' ? 'primary' : 'secondary'); 
								?>">
									<?php echo ucfirst($user['uloga']); ?>
								</span>
								<?php if ($user['is_protected']): ?>
									<span class="badge bg-warning">Zaštićen</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="badge bg-<?php echo $user['status'] === 'aktivan' ? 'success' : 'secondary'; ?>">
									<?php echo ucfirst($user['status']); ?>
								</span>
							</td>
							<td><?php echo $user['last_login'] ? date('d.m.Y. H:i', strtotime($user['last_login'])) : 'Nikad'; ?></td>
							<td>
								<?php if ($auth->canManageUser($user['id']) && $user['uloga'] !== 'super_admin'): ?>
									<div class="btn-group">
										<button type="button" class="btn btn-sm btn-primary" 
												onclick="urediKorisnika(<?php echo $user['id']; ?>, 
																	  '<?php echo htmlspecialchars($user['username']); ?>',
																	  '<?php echo htmlspecialchars($user['ime']); ?>',
																	  '<?php echo htmlspecialchars($user['prezime']); ?>',
																	  '<?php echo htmlspecialchars($user['email']); ?>',
																	  '<?php echo htmlspecialchars($user['uloga']); ?>')">
											<i class="fas fa-edit"></i>
										</button>
										<button type="button" class="btn btn-sm btn-danger" 
												onclick="obrisiKorisnika(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
											<i class="fas fa-trash"></i>
										</button>
									</div>
								<?php endif; ?>
							</td>
						</tr>
						<?php endwhile; ?>
					</tbody>
				</table> 
            </div>
        </div>
    </div>
<!-- Modal za uređivanje korisnika -->
<div class="modal fade" id="urediKorisnikaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
			<form action="process_korisnik.php" method="POST" class="needs-validation" novalidate>
				<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
				<input type="hidden" name="action" value="edit">
				<input type="hidden" name="id" id="edit_id">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-user-edit"></i> Uredi korisnika</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<!-- Osnovne informacije -->
					<div class="mb-3">
						<label for="edit_username" class="form-label">
							<i class="fas fa-user"></i> Korisničko ime *
						</label>
						<input type="text" class="form-control" id="edit_username" name="username" 
							   pattern="[a-zA-Z0-9_]{3,20}" 
							   title="3-20 znakova, samo slova, brojevi i _" required>
						<div class="invalid-feedback">
							Korisničko ime mora sadržavati 3-20 znakova (slova, brojevi i _)
						</div>
					</div>

					<!-- Lozinka -->
					<div class="mb-3">
						<label for="edit_password" class="form-label">
							<i class="fas fa-key"></i> Nova lozinka
						</label>
						<div class="input-group">
							<input type="password" class="form-control" id="edit_password" name="password"
								   minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$"
								   title="Minimum 8 znakova, najmanje jedno slovo i jedan broj">
							<button class="btn btn-outline-secondary" type="button" id="toggleEditPassword">
								<i class="fas fa-eye"></i>
							</button>
						</div>
						<div class="form-text text-muted">
							<i class="fas fa-info-circle"></i> 
							Ostavite prazno ako ne želite mijenjati lozinku. 
							Nova lozinka mora sadržavati minimum 8 znakova, slova i brojeve.
						</div>
					</div>

					<!-- Osobni podaci -->
					<div class="row">
						<div class="col-md-6 mb-3">
							<label for="edit_ime" class="form-label">
								<i class="fas fa-font"></i> Ime *
							</label>
							<input type="text" class="form-control" id="edit_ime" name="ime" 
								   required pattern="[A-Za-zČčĆćĐđŠšŽž\s]{2,50}"
								   title="Ime mora sadržavati samo slova">
							<div class="invalid-feedback">
								Unesite ispravno ime (samo slova)
							</div>
						</div>
						<div class="col-md-6 mb-3">
							<label for="edit_prezime" class="form-label">
								<i class="fas fa-font"></i> Prezime *
							</label>
							<input type="text" class="form-control" id="edit_prezime" name="prezime" 
								   required pattern="[A-Za-zČčĆćĐđŠšŽž\s]{2,50}"
								   title="Prezime mora sadržavati samo slova">
							<div class="invalid-feedback">
								Unesite ispravno prezime (samo slova)
							</div>
						</div>
					</div>

					<!-- Email -->
					<div class="mb-3">
						<label for="edit_email" class="form-label">
							<i class="fas fa-envelope"></i> Email *
						</label>
						<input type="email" class="form-control" id="edit_email" name="email" required>
						<div class="invalid-feedback">
							Unesite ispravnu email adresu
						</div>
					</div>

					<!-- Status i ovlaštenja -->
					<div class="mb-3">
						<label class="form-label">Status i ovlaštenja</label>
						<div class="d-flex gap-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="edit_is_active" 
									   name="status" value="aktivan" checked>
								<label class="form-check-label" for="edit_is_active">
									<i class="fas fa-toggle-on"></i> Aktivan račun
								</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="edit_is_admin" 
									   name="is_admin">
								<label class="form-check-label" for="edit_is_admin">
									<i class="fas fa-user-shield"></i> Admin ovlaštenja
								</label>
							</div>
						</div>
					</div>
				</div>

				<!-- Footer -->
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
						<i class="fas fa-times"></i> Zatvori
					</button>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save"></i> Spremi promjene
					</button>
				</div>
			</form>
        </div>
    </div>
</div>
</div>

<!-- Modal za novog korisnika -->
<div class="modal fade" id="noviKorisnikModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process_korisnik.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create">  <!-- Dodana linija -->
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Novi korisnik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Korisničko ime *
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required
                               pattern="[a-zA-Z0-9_]{3,20}" title="3-20 znakova, samo slova, brojevi i _">
                        <div class="invalid-feedback">Korisničko ime mora sadržavati 3-20 znakova (slova, brojevi i _)</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-key"></i> Lozinka *
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required
                                   minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$"
                                   title="Minimum 8 znakova, najmanje jedno slovo i jedan broj">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Minimum 8 znakova, slova i brojevi
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ime" class="form-label">
                                <i class="fas fa-font"></i> Ime *
                            </label>
                            <input type="text" class="form-control" id="ime" name="ime" required
                                   pattern="[A-Za-zČčĆćĐđŠšŽž\s]{2,50}" title="Ime mora sadržavati samo slova">
                            <div class="invalid-feedback">Unesite ispravno ime (samo slova)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prezime" class="form-label">
                                <i class="fas fa-font"></i> Prezime *
                            </label>
                            <input type="text" class="form-control" id="prezime" name="prezime" required
                                   pattern="[A-Za-zČčĆćĐđŠšŽž\s]{2,50}" title="Prezime mora sadržavati samo slova">
                            <div class="invalid-feedback">Unesite ispravno prezime (samo slova)</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Unesite ispravnu email adresu</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin">
                            <label class="form-check-label" for="is_admin">
                                <i class="fas fa-user-shield"></i> Dodjeli admin ovlaštenja
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Zatvori
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Spremi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Postojeći kod za validaciju forme...

    // Toggle password visibility za oba modala
    ['togglePassword', 'toggleEditPassword'].forEach(id => {
        const toggle = document.getElementById(id);
        const password = document.getElementById(id === 'togglePassword' ? 'password' : 'edit_password');
        
        if (toggle && password) {
            toggle.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
    });
});

function urediKorisnika(id, username, ime, prezime, email, uloga) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_ime').value = ime;
    document.getElementById('edit_prezime').value = prezime;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_is_admin').checked = (uloga === 'admin');
    
    // Resetirati polje za lozinku
    document.getElementById('edit_password').value = '';
    
    // Otvoriti modal
    new bootstrap.Modal(document.getElementById('urediKorisnikaModal')).show();
}

function obrisiKorisnika(id, username) {
    if (confirm(`Da li ste sigurni da želite obrisati korisnika "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_korisnik.php';
        
        const fields = {
            'csrf_token': '<?php echo $_SESSION['csrf_token']; ?>',
            'action': 'delete',
            'id': id
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<!-- CSS -->
<style>
.table-responsive {
    margin-bottom: 1rem;
}

.badge {
    font-size: 0.875rem;
}

.btn-sm {
    margin: 0 2px;
}

.modal-dialog {
    max-width: 500px;
}

.form-label {
    font-weight: 500;
}
.modal-body .form-label {
    font-weight: 500;
}

.modal-body .fas {
    width: 20px;
    text-align: center;
    margin-right: 5px;
}

.invalid-feedback {
    font-size: 80%;
}

.form-text {
    font-size: 80%;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    padding: 1rem;
}

.form-check {
    margin-bottom: 0;
}
</style>

<?php include '../views/footer.php'; ?>