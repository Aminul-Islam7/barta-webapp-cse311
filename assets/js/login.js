(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var toggleButtons = document.querySelectorAll('.p-login__user-type-btn');
		var forms = document.querySelectorAll('.p-login__form');

		if (!toggleButtons.length) return;

		function showForm(targetId) {
			forms.forEach(function (f) {
				if (f.id === targetId) {
					f.classList.remove('p-login__form--hidden');
					f.classList.add('p-login__form--visible');
				} else {
					f.classList.add('p-login__form--hidden');
					f.classList.remove('p-login__form--visible');
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

		// Initialize default button state (show tween)
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
