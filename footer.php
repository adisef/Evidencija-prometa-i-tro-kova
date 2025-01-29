		</div> <!-- Zatvaranje container-fluid -->
		<footer>
			<p>&copy; 2025 Evidencija Nekretnina</p>
		</footer>
		<!-- Bootstrap Bundle with Popper -->
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
		<script>
		// Globalna funkcija za prikazivanje obavještenja
		function showAlert(message, type = 'success') {
			const alertHtml = `
				<div class="alert alert-${type} alert-dismissible fade show" role="alert">
					${message}
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				</div>
			`;
			
			$('#alertContainer').append(alertHtml);
			
			setTimeout(() => {
				$('.alert').fadeOut('slow', function() {
					$(this).remove();
				});
			}, 3000);
		}
		</script>
		<script src="<?php echo ROOT_URL; ?>assets/js/utils.js"></script>
	</body>
</html>
