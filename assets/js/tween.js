import { getCookie, setCookie } from './modules/utils.js';
import { scrollToBottom } from './modules/ui.js';
import { state } from './modules/state.js';
import { refreshContacts, performSearch, initContactClicks } from './modules/contacts.js';
import { fetchConversation, initChat, startPolling } from './modules/chat.js';
import { initSettings } from './modules/settings.js';
import { clearSelection, selectItem, showConfirmation } from './modules/ui.js';

document.addEventListener('DOMContentLoaded', function () {
	// Apply saved preferences setup (Cookie logic for Theme)
	const savedTheme = getCookie('theme');
	if (savedTheme === 'dark') {
		document.body.setAttribute('data-theme', 'dark');
		const themeIcon = document.querySelector('#theme-toggle i');
		if (themeIcon) themeIcon.className = 'fa-jelly-fill fa-regular fa-lightbulb';
	}
	
	// Initial UI setup
	const messageInputEl = document.querySelector('.message-input');
	const chatContainerEl = document.querySelector('.chat-container');
	if (messageInputEl && chatContainerEl) {
		const chatVisible = window.getComputedStyle(chatContainerEl).display !== 'none';
		messageInputEl.style.display = chatVisible ? 'flex' : 'none';
		if (chatVisible) setTimeout(() => {
			scrollToBottom();
		}, 0);
	}
	
	// Nav & Modals
	initSettings();
	
	// Chat Listeners
	initChat();
	
	// Contacts & Search
	initContactClicks();
	refreshContacts(); // Starts polling
	
	const searchBox = document.querySelector('.search-box');
	const searchClearBtn = document.querySelector('.search-clear-btn');
	let searchTimeout = null;
	
	if (searchBox) {
		searchBox.addEventListener('input', function (e) {
			const query = e.target.value;
			if (searchClearBtn) {
				searchClearBtn.style.display = query.trim().length > 0 ? 'block' : 'none';
			}
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(() => {
				performSearch(query);
			}, 300);
		});
		searchBox.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				performSearch(e.target.value);
			}
		});
		if (searchClearBtn) {
			searchClearBtn.addEventListener('click', function() {
				searchBox.value = '';
				this.style.display = 'none';
				searchBox.focus();
				performSearch('');
			});
		}
	}
	
	// Browser Navigation (Back/Forward)
	window.addEventListener('popstate', function (e) {
		if (!e.state) {
			// Reset to empty state
			document.querySelector('.empty-state').style.display = '';
			document.querySelector('.chat-container').style.display = 'none';
			const mi = document.querySelector('.message-input');
			if (mi) mi.style.display = 'none';
			document.querySelector('.middle-panel').classList.add('expanded');
			document.querySelector('.right-panel').classList.add('hidden');
			clearSelection();
			if (state.pollAbortController) {
				state.pollAbortController.abort();
				state.pollAbortController = null;
			}
			state.currentTarget = null;
			state.currentTargetType = null;
			state.lastMessageId = 0;
			state.lastActiveTime = 0;
			return;
		}
		if (e.state && e.state.u) {
			fetchConversation('tween/fetch_conversation.php?u=' + encodeURIComponent(e.state.u));
		}
		// Group support removed
	});
	
	// Initial URL Check
	const params = new URLSearchParams(window.location.search);
	if (params.has('u')) {
		const username = params.get('u');
		const el = document.querySelector('.contact-item[data-username="' + username + '"]');
		if (el) selectItem(el);
		fetchConversation('tween/fetch_conversation.php?u=' + encodeURIComponent(username));
	}
	
	// Notification Dot Check
	const friendsNotificationDot = document.getElementById('friends-notification-dot');
	if (friendsNotificationDot) {
		fetch('api/fetch_friends_data.php')
			.then((r) => r.json())
			.then((data) => {
				if (data.pending_requests && data.pending_requests.length > 0) {
					friendsNotificationDot.style.display = 'block';
				}
			})
			.catch(() => {});
	}
	
	// Right Panel Toggle Logic (if exists on page)
	const toggleBtn = document.querySelector('.toggle-right-panel-btn');
	if (toggleBtn) {
		const rightPanel = document.querySelector('.right-panel');
		if (rightPanel && rightPanel.classList.contains('hidden')) {
			toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-left"></i>';
		} else {
			toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-right"></i>';
		}
		toggleBtn.addEventListener('click', function () {
			const middlePanel = document.querySelector('.middle-panel');
			if (rightPanel.classList.contains('hidden')) {
				rightPanel.classList.remove('hidden');
				middlePanel.classList.remove('expanded');
				toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-right"></i>';
				setCookie('rightPanelHidden', 'false');
			} else {
				rightPanel.classList.add('hidden');
				middlePanel.classList.add('expanded');
				toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-left"></i>';
				setCookie('rightPanelHidden', 'true');
			}
		});
	}
});
