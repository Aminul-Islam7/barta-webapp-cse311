(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var toggleButtons = document.querySelectorAll('[data-target]');
		var forms = document.querySelectorAll('form[id]');

		function showForm(targetId) {
			forms.forEach(function (f) {
				if (f.id === targetId) {
					f.classList.remove('form--hidden');
					f.classList.add('form--visible');
				} else {
					f.classList.add('form--hidden');
					f.classList.remove('form--visible');
				}
			});

			toggleButtons.forEach(function (btn) {
				var t = btn.getAttribute('data-target');
				if (t === targetId) {
					btn.classList.add('btn-primary');
					btn.classList.remove('btn-secondary');
				} else {
					btn.classList.remove('btn-primary');
					btn.classList.add('btn-secondary');
				}
			});
		}


		showForm('tween');

		// Wire buttons
		toggleButtons.forEach(function (b) {
			b.addEventListener('click', function (e) {
				e.preventDefault();
				var target = b.getAttribute('data-target');
				if (!target) return;
				showForm(target);
			});
		});
	});
})();
