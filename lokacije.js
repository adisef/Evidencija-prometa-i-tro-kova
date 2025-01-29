$(document).ready(function() {
    // Debug logging
    console.log('Script initialized');
    console.log('ROOT_URL:', ROOT_URL);

    // Inicijalizacija DataTables
    const naseljaTable = $('#naseljaTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/hr.json'
        },
        pageLength: 15,
        order: [[0, 'asc'], [1, 'asc'], [2, 'asc']]
    });

    const opcineTable = $('#opcineTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/hr.json'
        },
        pageLength: 15,
        order: [[0, 'asc'], [1, 'asc']]
    });

    const kantoniTable = $('#kantoniTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/hr.json'
        },
        pageLength: 15,
        order: [[0, 'asc']]
    });

    // Funkcija za AJAX pozive
    function makeAjaxCall(action, method, data) {
        const url = ROOT_URL + 'views/lokacije/actions/' + action;
        console.log('Making AJAX call to:', url, 'with data:', data);

        return $.ajax({
            url: url,
            type: method,
            data: data,
            dataType: 'json'
        }).fail(function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            alert('Greška u komunikaciji sa serverom');
        });
    }

    // Učitavanje općina za odabrani kanton
	function loadOpcine(kantonId, targetSelect) {
		if (!kantonId) return;
		
		const url = ROOT_URL + 'views/lokacije/actions/opcine.php';
		console.log('Making AJAX call to:', url); // Za debugiranje
		
		$(targetSelect).prop('disabled', true);
		
		$.ajax({
			url: url,
			type: 'GET',
			data: {
				action: 'getByKanton',
				kanton_id: kantonId
			},
			dataType: 'json'
		})
		.done(function(response) {
			console.log('Success response:', response); // Za debugiranje
			let options = '<option value="">Odaberite općinu</option>';
			if (response && response.success && response.data) {
				response.data.forEach(function(opcina) {
					options += `<option value="${opcina.id}">${opcina.naziv}</option>`;
				});
			}
			$(targetSelect).html(options).prop('disabled', false);
		})
		.fail(function(xhr, status, error) {
			console.error('AJAX Error:', {
				url: url,
				status: xhr.status,
				statusText: xhr.statusText,
				responseText: xhr.responseText
			});
			$(targetSelect).prop('disabled', false);
		});
	}

    // Event handlers za promjenu kantona
    $('#kanton_id, #edit_naselje_kanton').change(function() {
        const targetSelect = this.id === 'kanton_id' ? '#opcina_id' : '#edit_naselje_opcina';
        loadOpcine($(this).val(), targetSelect);
    });

    // NASELJA
    // Dodavanje novog naselja
    $('#naseljeForm').submit(function(e) {
        e.preventDefault();
        console.log('Submitting naselje form');
        makeAjaxCall('naselja.php', 'POST', {
            action: 'save',
            ...$(this).serialize()
        }).done(function(response) {
            if (response.success) {
                alert('Naselje je uspješno dodano');
                location.reload();
            } else {
                alert(response.error || 'Došlo je do greške');
            }
        });
    });

    // Uređivanje naselja
    $('.edit-naselje').click(function() {
        const id = $(this).data('id');
        console.log('Editing naselje:', id);
        makeAjaxCall('naselja.php', 'GET', {
            action: 'get',
            id: id
        }).done(function(response) {
            if (response.success) {
                const data = response.data;
                $('#edit_naselje_id').val(data.id);
                $('#edit_naselje_kanton').val(data.kanton_id).trigger('change');
                setTimeout(() => {
                    $('#edit_naselje_opcina').val(data.opcina_id);
                }, 500);
                $('#edit_naselje_naziv').val(data.naziv);
                $('#edit_naselje_status').prop('checked', data.active == 1);
                $('#editNaseljeModal').modal('show');
            }
        });
    });

    // Spremanje izmjena naselja
    $('#saveNaseljeChanges').click(function() {
        console.log('Saving naselje changes');
        makeAjaxCall('naselja.php', 'POST', {
            action: 'update',
            ...$("#editNaseljeForm").serialize()
        }).done(function(response) {
            if (response.success) {
                $('#editNaseljeModal').modal('hide');
                alert('Naselje je uspješno ažurirano');
                location.reload();
            }
        });
    });

    // OPĆINE
    // Dodavanje nove općine
    $('#opcinaForm').submit(function(e) {
        e.preventDefault();
        console.log('Submitting opcina form');
        makeAjaxCall('opcine.php', 'POST', {
            action: 'save',
            ...$(this).serialize()
        }).done(function(response) {
            if (response.success) {
                alert('Općina je uspješno dodana');
                location.reload();
            }
        });
    });

    // Uređivanje općine
    $('.edit-opcina').click(function() {
        const id = $(this).data('id');
        console.log('Editing opcina:', id);
        makeAjaxCall('opcine.php', 'GET', {
            action: 'get',
            id: id
        }).done(function(response) {
            if (response.success) {
                const data = response.data;
                $('#edit_opcina_id').val(data.id);
                $('#edit_opcina_kanton').val(data.kanton_id);
                $('#edit_opcina_naziv').val(data.naziv);
                $('#edit_opcina_status').prop('checked', data.active == 1);
                $('#editOpcinaModal').modal('show');
            }
        });
    });

    // Spremanje izmjena općine
    $('#saveOpcinaChanges').click(function() {
        console.log('Saving opcina changes');
        makeAjaxCall('opcine.php', 'POST', {
            action: 'update',
            ...$("#editOpcinaForm").serialize()
        }).done(function(response) {
            if (response.success) {
                $('#editOpcinaModal').modal('hide');
                alert('Općina je uspješno ažurirana');
                location.reload();
            }
        });
    });

    // KANTONI
    // Dodavanje novog kantona
    $('#kantonForm').submit(function(e) {
        e.preventDefault();
        console.log('Submitting kanton form');
        makeAjaxCall('kantoni.php', 'POST', {
            action: 'save',
            ...$(this).serialize()
        }).done(function(response) {
            if (response.success) {
                alert('Kanton je uspješno dodan');
                location.reload();
            }
        });
    });

    // Uređivanje kantona
    $('.edit-kanton').click(function() {
        const id = $(this).data('id');
        console.log('Editing kanton:', id);
        makeAjaxCall('kantoni.php', 'GET', {
            action: 'get',
            id: id
        }).done(function(response) {
            if (response.success) {
                const data = response.data;
                $('#edit_kanton_id').val(data.id);
                $('#edit_kanton_naziv').val(data.naziv);
                $('#edit_kanton_status').prop('checked', data.active == 1);
                $('#editKantonModal').modal('show');
            }
        });
    });

    // Spremanje izmjena kantona
    $('#saveKantonChanges').click(function() {
        console.log('Saving kanton changes');
        makeAjaxCall('kantoni.php', 'POST', {
            action: 'update',
            ...$("#editKantonForm").serialize()
        }).done(function(response) {
            if (response.success) {
                $('#editKantonModal').modal('hide');
                alert('Kanton je uspješno ažuriran');
                location.reload();
            }
        });
    });

    // Brisanje
    $('.delete-naselje').click(function() {
        if (confirm('Jeste li sigurni da želite obrisati ovo naselje?')) {
            const id = $(this).data('id');
            makeAjaxCall('naselja.php', 'POST', {
                action: 'delete',
                id: id
            }).done(function(response) {
                if (response.success) {
                    alert('Naselje je uspješno obrisano');
                    location.reload();
                }
            });
        }
    });

    $('.delete-opcina').click(function() {
        if (confirm('Jeste li sigurni da želite obrisati ovu općinu?')) {
            const id = $(this).data('id');
            makeAjaxCall('opcine.php', 'POST', {
                action: 'delete',
                id: id
            }).done(function(response) {
                if (response.success) {
                    alert('Općina je uspješno obrisana');
                    location.reload();
                }
            });
        }
    });

    $('.delete-kanton').click(function() {
        if (confirm('Jeste li sigurni da želite obrisati ovaj kanton?')) {
            const id = $(this).data('id');
            makeAjaxCall('kantoni.php', 'POST', {
                action: 'delete',
                id: id
            }).done(function(response) {
                if (response.success) {
                    alert('Kanton je uspješno obrisan');
                    location.reload();
                }
            });
        }
    });

    // Filter za prikaz neaktivnih
    $('#showInactiveNaselja, #showInactiveOpcine, #showInactiveKantoni').change(function() {
        const table = this.id === 'showInactiveNaselja' ? naseljaTable :
                     this.id === 'showInactiveOpcine' ? opcineTable : kantoniTable;
        
        table.draw();
        if (!this.checked) {
            table.rows('[data-status="0"]').nodes().to$().hide();
        }
    }).prop('checked', false).trigger('change');
});