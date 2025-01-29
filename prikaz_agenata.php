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

// Svi mogu vidjeti listu, ali samo admin može uređivati

include 'header.php';

// Dohvat svih agenata sa sigurnom pripremljenom izjavom
$sql = "SELECT id, ime_prezime, email, telefon, status, datum_zaposlenja FROM agenti ORDER BY ime_prezime ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pregled agenata</h2>
        <?php if ($auth->hasPermission('admin')): ?>
        <a href="unos_agenta.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Novi agent
        </a>
        <?php endif; ?>
    </div>

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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Ime i prezime</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Status</th>
                            <th>Datum zaposlenja</th>
                            <?php if ($auth->hasPermission('admin')): ?>
                            <th>Akcije</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($agent = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($agent['ime_prezime']); ?></td>
                                    <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                    <td><?php echo htmlspecialchars($agent['telefon']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $agent['status'] === 'aktivan' ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($agent['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y.', strtotime($agent['datum_zaposlenja'])); ?></td>
                                    <?php if ($auth->hasPermission('admin')): ?>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="uredi_agenta.php?id=<?php echo $agent['id']; ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Uredi">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="potvrdiPotvrdiPotvrditkePotvrditkePotvrditketkePotvrditkeBrisjanje(<?php echo $agent['id']; ?>)"
                                                    title="Obriši">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $auth->hasPermission('admin') ? '6' : '5'; ?>" class="text-center">
                                    Nema dostupnih agenata.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($auth->hasPermission('admin')): ?>
<!-- Modal za potvrdu brisanja -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Potvrda brisanja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Jeste li sigurni da želite obrisati ovog agenta?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Odustani</button>
                <form id="deleteForm" action="../controllers/obrisi_agenta.php" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="agent_id" id="deleteAgentId">
                    <button type="submit" class="btn btn-danger">Obriši</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function potvrdiPotvrdiPotvrditkePotvrditkePotvrditketkePotvrditkeBrisjanje(id) {
    document.getElementById('deleteAgentId').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php endif; ?>

<?php
$stmt->close();
include 'footer.php';
?>