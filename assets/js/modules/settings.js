import { setCookie, getCookie } from './utils.js';
import { loadFriendsData } from './contacts.js';
import { showConfirmation } from './ui.js';

export function initSettings() {
	// Theme Toggle
	const themeToggle = document.getElementById('theme-toggle');
	if (themeToggle) {
		const themeIcon = themeToggle.querySelector('i');
		themeToggle.addEventListener('click', function () {
			const currentTheme = document.body.getAttribute('data-theme');
			if (currentTheme === 'dark') {
				document.body.removeAttribute('data-theme');
				themeIcon.className = 'fa-jelly-fill fa-regular fa-moon';
				setCookie('theme', 'light');
			} else {
				document.body.setAttribute('data-theme', 'dark');
				themeIcon.className = 'fa-jelly-fill fa-regular fa-lightbulb';
				setCookie('theme', 'dark');
			}
		});
	}

	// Friends Modal
	const friendsBtn = document.getElementById('friends-btn');
	const friendsModal = document.getElementById('friends-modal');
	const closeFriendsModalBtn = document.getElementById('close-friends-modal');
	
	if (friendsBtn && friendsModal) {
		friendsBtn.addEventListener('click', function () {
			friendsModal.classList.add('show');
			loadFriendsData();
		});
	}
	if (closeFriendsModalBtn && friendsModal) {
		closeFriendsModalBtn.addEventListener('click', () => friendsModal.classList.remove('show'));
	}

	// Settings Modal
	const settingsBtn = document.getElementById('settings-btn');
	const settingsModal = document.getElementById('settings-modal');
	const closeSettingsModalBtn = document.getElementById('close-settings-modal');
	const profileBtn = document.querySelector('.profile-content');
	
	function openSettingsModal() {
		if (!settingsModal) return;
		settingsModal.classList.add('show');
		fetch('api/fetch_profile.php').then(r => r.json()).then(res => {
			if (res.success) {
				const data = res.data;
				const nameInp = document.getElementById('set-name');
				if (nameInp) nameInp.value = data.full_name || '';
				const usrInp = document.getElementById('set-username');
				if (usrInp) usrInp.value = data.username || '';
				const bioInp = document.getElementById('set-bio');
				if (bioInp) bioInp.value = data.bio || '';
				const emailInp = document.getElementById('set-email');
				if (emailInp) emailInp.value = data.email || '';
				const dob = document.getElementById('set-dob');
				if (dob) dob.textContent = data.birth_date || 'N/A';
				const parent = document.getElementById('set-parent');
				if (parent) parent.textContent = data.parent_name || 'Not linked';
			}
		});
	}

	if (settingsBtn) settingsBtn.addEventListener('click', openSettingsModal);
	if (profileBtn) profileBtn.addEventListener('click', openSettingsModal);
	if (closeSettingsModalBtn) closeSettingsModalBtn.addEventListener('click', () => settingsModal.classList.remove('show'));

	// Profile Form
	const profileForm = document.getElementById('profile-form');
	if (profileForm) {
		profileForm.addEventListener('submit', function(e) {
			e.preventDefault();
			const formData = new FormData(this);
			fetch('api/update_profile.php', { method: 'POST', body: formData }).then(r => r.json()).then(res => {
				if (res.success) {
					alert('Profile updated!');
					location.reload();
				} else {
					alert('Error: ' + res.error);
				}
			});
		});
	}
	
	// Password Form
	const passwordForm = document.getElementById('password-form');
	if (passwordForm) {
		passwordForm.addEventListener('submit', function(e) {
			e.preventDefault();
			const formData = new FormData(this);
			fetch('api/update_password.php', { method: 'POST', body: formData }).then(r => r.json()).then(res => {
				if (res.success) {
					alert('Password updated!');
					this.reset();
				} else {
					alert('Error: ' + res.error);
				}
			});
		});
	}

	// Limits Modal
	const limitsBtn = document.getElementById('limits-btn');
	const limitsModal = document.getElementById('message-limit-modal');
	const closeLimitsModalBtn = document.getElementById('close-limit-modal');
	
	if (limitsBtn && limitsModal) {
		limitsBtn.addEventListener('click', function() {
			limitsModal.classList.add('show');
			fetchMessageStats();
		});
	}
	if (closeLimitsModalBtn) closeLimitsModalBtn.addEventListener('click', () => limitsModal.classList.remove('show'));

	function fetchMessageStats() {
		fetch('api/fetch_message_stats.php').then(r => r.json()).then(data => {
			if (data.success) {
				const sent = parseInt(data.sent_count) || 0;
				const received = parseInt(data.received_count) || 0;
				const limit = parseInt(data.daily_limit) || 100;
				const left = Math.max(0, limit - sent);
				const sSent = document.getElementById('stat-sent');
				const sRec = document.getElementById('stat-received');
				const sLim = document.getElementById('stat-limit');
				const sLeft = document.getElementById('stat-left');
				if (sSent) sSent.textContent = sent;
				if (sRec) sRec.textContent = received;
				if (sLim) sLim.textContent = limit;
				if (sLeft) sLeft.textContent = left;
			}
		});
	}

	// Help Modal
	const helpBtn = document.getElementById('help-btn');
	const helpModal = document.getElementById('help-modal');
	const closeHelpModalBtn = document.getElementById('close-help-modal');
	if (helpBtn && helpModal) helpBtn.addEventListener('click', () => helpModal.classList.add('show'));
	if (closeHelpModalBtn) closeHelpModalBtn.addEventListener('click', () => helpModal.classList.remove('show'));

	// Confirmation Modal cancel button
	const cancelConfirmationBtn = document.getElementById('cancel-confirmation');
	if (cancelConfirmationBtn) {
		cancelConfirmationBtn.addEventListener('click', function () {
			const modal = document.getElementById('confirmation-modal');
			if (modal) modal.classList.remove('show');
		});
	}
	
	// Close any modal when clicking outside
	document.querySelectorAll('.modal').forEach(modal => {
		modal.addEventListener('click', function (e) {
			if (e.target === modal) {
				modal.classList.remove('show');
			}
		});
	});
	
	// Delegated block button handler (from body, e.g. friend list)
	document.body.addEventListener('click', function(e) {
		const btn = e.target.closest('.btn-block-user');
		// The contact card attach logic handles buttons inside the sidebar. 
		// Friends modal logic handles buttons inside friends modal.
		// So this might be redundant or valid for dynamic elements not covered.
		// Assuming specific handlers are attached, I'll remove the generic body listener 
		// to avoid double firing if I attached explicit listeners.
		// BUT `contacts.js` `attachFriendActionListeners` does attach explicit listeners.
		// So we are good.
	});
}
