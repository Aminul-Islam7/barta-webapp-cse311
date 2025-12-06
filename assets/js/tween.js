// Tween Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function () {
	// Create Group Modal
	const createGroupBtn = document.getElementById('create-group-btn');
	const createGroupModal = document.getElementById('create-group-modal');
	const cancelCreateBtn = document.getElementById('cancel-create');

	if (createGroupBtn && createGroupModal && cancelCreateBtn) {
		createGroupBtn.addEventListener('click', function () {
			createGroupModal.classList.add('show');
		});

		cancelCreateBtn.addEventListener('click', function () {
			createGroupModal.classList.remove('show');
		});
	}

	// Confirmation Modal
	const confirmationModal = document.getElementById('confirmation-modal');
	const cancelConfirmationBtn = document.getElementById('cancel-confirmation');

	if (confirmationModal && cancelConfirmationBtn) {
		cancelConfirmationBtn.addEventListener('click', function () {
			confirmationModal.classList.remove('show');
		});
	}

	// Edit Message Modal
	const editMessageModal = document.getElementById('edit-message-modal');
	const cancelEditBtn = document.getElementById('cancel-edit');
	const saveEditBtn = document.getElementById('save-edit');
	const editMessageText = document.getElementById('edit-message-text');

	if (editMessageModal && cancelEditBtn && saveEditBtn && editMessageText) {
		cancelEditBtn.addEventListener('click', function () {
			editMessageModal.classList.remove('show');
		});

		saveEditBtn.addEventListener('click', function () {
			if (currentMessage) {
				const newText = editMessageText.value.trim();
				if (newText) {
					currentMessage.querySelector('.text').textContent = newText;
				}
			}
			editMessageModal.classList.remove('show');
		});
	}

	function showConfirmation(options, onConfirm) {
		const { title = 'Confirm Action', icon = '', message = 'Are you sure?', confirmText = 'Confirm' } = options;
		const h3 = confirmationModal.querySelector('h3');
		h3.innerHTML = (icon ? icon + ' ' : '') + title;
		const messageEl = confirmationModal.querySelector('.confirmation-message');
		messageEl.textContent = message;
		const confirmBtn = confirmationModal.querySelector('.btn-confirm');
		confirmBtn.textContent = confirmText;
		confirmBtn.onclick = function () {
			onConfirm();
			confirmationModal.classList.remove('show');
		};
		confirmationModal.classList.add('show');
	}

	// Theme Toggle
	const themeToggle = document.getElementById('theme-toggle');
	if (themeToggle) {
		const themeIcon = themeToggle.querySelector('i');
		themeToggle.addEventListener('click', function () {
			const currentTheme = document.body.getAttribute('data-theme');
			if (currentTheme === 'dark') {
				document.body.removeAttribute('data-theme');
				themeIcon.className = 'fa-jelly-fill fa-regular fa-moon';
			} else {
				document.body.setAttribute('data-theme', 'dark');
				themeIcon.className = 'fa-jelly-fill fa-regular fa-lightbulb';
			}
		});
	}

	// Custom Color Picker
	const colorInput = document.getElementById('group-color');
	const colorDisplay = document.getElementById('color-display');
	if (colorInput && colorDisplay) {
		colorDisplay.style.background = colorInput.value;
		colorInput.addEventListener('input', () => {
			colorDisplay.style.background = colorInput.value;
		});
	}

	// Context Menu for Own Messages
	const contextMenu = document.createElement('div');
	contextMenu.className = 'context-menu';
	contextMenu.innerHTML = `
        <button class="context-menu-item" data-action="edit">
            <i class="fa-solid fa-pen-to-square"></i> Edit
        </button>
        <button class="context-menu-item" data-action="delete">
            <i class="fa-jelly-fill fa-regular fa-trash"></i> Delete
        </button>
    `;
	document.body.appendChild(contextMenu);

	let currentMessage = null;

	document.addEventListener('contextmenu', function (e) {
		if (e.target.closest('.message.own')) {
			e.preventDefault();
			currentMessage = e.target.closest('.message.own');
			contextMenu.style.left = e.clientX + 'px';
			contextMenu.style.top = e.clientY + 'px';
			contextMenu.classList.add('show');
		} else {
			contextMenu.classList.remove('show');
		}
	});

	document.addEventListener('click', function (e) {
		if (!contextMenu.contains(e.target)) {
			contextMenu.classList.remove('show');
		}
	});

	contextMenu.addEventListener('click', function (e) {
		const action = e.target.closest('.context-menu-item')?.dataset.action;
		if (action && currentMessage) {
			if (action === 'edit') {
				const currentText = currentMessage.querySelector('.text').textContent;
				editMessageText.value = currentText;
				editMessageModal.classList.add('show');
			} else if (action === 'delete') {
				showConfirmation(
					{
						title: 'Delete Message',
						icon: '<i class="fa-jelly-fill fa-regular fa-trash"></i>',
						message: 'Are you sure you want to delete this message?',
						confirmText: 'Delete',
					},
					function () {
						currentMessage.remove();
					}
				);
			}
			contextMenu.classList.remove('show');
		}
	});

	// Unfriend and Block buttons
	const unfriendBtn = document.querySelector('.action-buttons button[title="Unfriend"]');
	const blockBtn = document.querySelector('.action-buttons button[title="Block"]');

	if (unfriendBtn) {
		unfriendBtn.addEventListener('click', function () {
			showConfirmation(
				{
					title: 'Unfriend',
					icon: '<i class="fa-solid fa-user-times"></i>',
					message: 'Are you sure you want to unfriend this person?',
					confirmText: 'Unfriend',
				},
				function () {
					// Unfriend logic
					console.log('Unfriend');
				}
			);
		});
	}

	if (blockBtn) {
		blockBtn.addEventListener('click', function () {
			showConfirmation(
				{
					title: 'Block User',
					icon: '<i class="fa-solid fa-ban"></i>',
					message: 'Are you sure you want to block this person?',
					confirmText: 'Block',
				},
				function () {
					// Block logic
					console.log('Block');
				}
			);
		});
	}

	// Profile Button
	const profileBtn = document.querySelector('.profile-content');
	if (profileBtn) {
		profileBtn.addEventListener('click', function () {
			// Open profile modal later
		});
	}
});
