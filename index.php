<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.class.php';

$auth = new Auth($conn);

if (!$auth->checkAuth()) {
    header('Location: ../login.php');
    exit;
}

// Dohvat podataka
$kantoni_query = "SELECT id, naziv, active FROM kantoni ORDER BY naziv";
$kantoni_result = $conn->query($kantoni_query);

$opcine_query = "SELECT o.*, k.naziv as kanton_naziv 
                 FROM opcine o 
                 LEFT JOIN kantoni k ON o.kanton_id = k.id 
                 ORDER BY k.naziv, o.naziv";
$opcine_result = $conn->query($opcine_query);

$naselja_query = "SELECT n.*, o.naziv as opcina_naziv, k.naziv as kanton_naziv 
                 FROM naselja n 
                 LEFT JOIN opcine o ON n.opcina_id = o.id 
                 LEFT JOIN kantoni k ON o.kanton_id = k.id 
                 ORDER BY k.naziv, o.naziv, n.naziv";
$naselja_result = $conn->query($naselja_query);

include '../header.php';
?>

<div class="container-fluid mt-4">
    <ul class="nav nav-tabs" id="lokacijeTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="naselja-tab" data-bs-toggle="tab" href="#naselja" role="tab">Naselja</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="opcine-tab" data-bs-toggle="tab" href="#opcine" role="tab">Općine</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="kantoni-tab" data-bs-toggle="tab" href="#kantoni" role="tab">Kantoni</a>
        </li>
    </ul>

    <div class="tab-content" id="lokacijeTabContent">
        <!-- Tab za naselja -->
        <div class="tab-pane fade show active" id="naselja" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="float-start">Dodaj novo naselje</h5>
                </div>
                <div class="card-body">
                    <form id="naseljeForm">
                        <div class="row">
                            <div class="col-md-4">
                                <select id="kanton_id" class="form-select" required>
                                    <option value="">Odaberi kanton</option>
                                    <?php 
                                    $kantoni_result->data_seek(0);
                                    while($kanton = $kantoni_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $kanton['id'] ?>"><?= $kanton['naziv'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="opcina_id" name="opcina_id" class="form-select" required disabled>
                                    <option value="">Prvo odaberi kanton</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="naziv" class="form-control" placeholder="Naziv naselja" required>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary">Dodaj</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="showInactiveNaselja">
                        <label class="form-check-label" for="showInactiveNaselja">
                            Prikaži neaktivna naselja
                        </label>
                    </div>
                    <table id="naseljaTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kanton</th>
                                <th>Općina</th>
                                <th>Naselje</th>
                                <th>Status</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($naselje = $naselja_result->fetch_assoc()): ?>
                            <tr data-status="<?= $naselje['active'] ?>">
                                <td><?= $naselje['kanton_naziv'] ?></td>
                                <td><?= $naselje['opcina_naziv'] ?></td>
                                <td><?= $naselje['naziv'] ?></td>
                                <td><?= $naselje['active'] ? 'Aktivno' : 'Neaktivno' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-naselje" data-id="<?= $naselje['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-naselje" data-id="<?= $naselje['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab za općine -->
        <div class="tab-pane fade" id="opcine" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="float-start">Dodaj novu općinu</h5>
                </div>
                <div class="card-body">
                    <form id="opcinaForm">
                        <div class="row">
                            <div class="col-md-4">
                                <select name="kanton_id" class="form-select" required>
                                    <option value="">Odaberi kanton</option>
                                    <?php 
                                    $kantoni_result->data_seek(0);
                                    while($kanton = $kantoni_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $kanton['id'] ?>"><?= $kanton['naziv'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <input type="text" name="naziv" class="form-control" placeholder="Naziv općine" required>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary">Dodaj</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="showInactiveOpcine">
                        <label class="form-check-label" for="showInactiveOpcine">
                            Prikaži neaktivne općine
                        </label>
                    </div>
                    <table id="opcineTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kanton</th>
                                <th>Općina</th>
                                <th>Status</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($opcina = $opcine_result->fetch_assoc()): ?>
                            <tr data-status="<?= $opcina['active'] ?>">
                                <td><?= $opcina['kanton_naziv'] ?></td>
                                <td><?= $opcina['naziv'] ?></td>
                                <td><?= $opcina['active'] ? 'Aktivno' : 'Neaktivno' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-opcina" data-id="<?= $opcina['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-opcina" data-id="<?= $opcina['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab za kantone -->
        <div class="tab-pane fade" id="kantoni" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="float-start">Dodaj novi kanton</h5>
                </div>
                <div class="card-body">
                    <form id="kantonForm">
                        <div class="row">
                            <div class="col-md-11">
                                <input type="text" name="naziv" class="form-control" placeholder="Naziv kantona" required>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary">Dodaj</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="showInactiveKantoni">
                        <label class="form-check-label" for="showInactiveKantoni">
                            Prikaži neaktivne kantone
                        </label>
                    </div>
                    <table id="kantoniTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Naziv</th>
                                <th>Status</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $kantoni_result->data_seek(0);
                            while($kanton = $kantoni_result->fetch_assoc()): 
                            ?>
                            <tr data-status="<?= $kanton['active'] ?>">
                                <td><?= $kanton['naziv'] ?></td>
                                <td><?= $kanton['active'] ? 'Aktivno' : 'Neaktivno' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-kanton" data-id="<?= $kanton['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-kanton" data-id="<?= $kanton['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modalni prozori za uređivanje -->
<!-- Modal za naselja -->
<div class="modal fade" id="editNaseljeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Uredi naselje</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editNaseljeForm">
                    <input type="hidden" id="edit_naselje_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Kanton</label>
                        <select id="edit_naselje_kanton" class="form-select" required>
                            <option value="">Odaberi kanton</option>
                            <?php 
                            $kantoni_result->data_seek(0);
                            while($kanton = $kantoni_result->fetch_assoc()): 
                            ?>
                                <option value="<?= $kanton['id'] ?>"><?= $kanton['naziv'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Općina</label>
                        <select id="edit_naselje_opcina" name="opcina_id" class="form-select" required>
                            <option value="">Prvo odaberi kanton</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Naziv</label>
                        <input type="text" id="edit_naselje_naziv" name="naziv" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="edit_naselje_status" name="active" class="form-check-input">
                            <label class="form-check-label">Aktivno</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zatvori</button>
                <button type="button" class="btn btn-primary" id="saveNaseljeChanges">Spremi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal za općine -->
<div class="modal fade" id="editOpcinaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Uredi općinu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editOpcinaForm">
                    <input type="hidden" id="edit_opcina_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Kanton</label>
                        <select id="edit_opcina_kanton" name="kanton_id" class="form-select" required>
                            <option value="">Odaberi kanton</option>
                            <?php 
                            $kantoni_result->data_seek(0);
                            while($kanton = $kantoni_result->fetch_assoc()): 
                            ?>
                                <option value="<?= $kanton['id'] ?>"><?= $kanton['naziv'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Naziv</label>
                        <input type="text" id="edit_opcina_naziv" name="naziv" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="edit_opcina_status" name="active" class="form-check-input">
                            <label class="form-check-label">Aktivno</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zatvori</button>
                <button type="button" class="btn btn-primary" id="saveOpcinaChanges">Spremi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal za kantone -->
<div class="modal fade" id="editKantonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Uredi kanton</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editKantonForm">
                    <input type="hidden" id="edit_kanton_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Naziv</label>
                        <input type="text" id="edit_kanton_naziv" name="naziv" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" id="edit_kanton_status" name="active" class="form-check-input">
                            <label class="form-check-label">Aktivno</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zatvori</button>
                <button type="button" class="btn btn-primary" id="saveKantonChanges">Spremi</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= ROOT_URL ?>views/lokacije/js/lokacije.js"></script>

<?php include '../footer.php'; ?>