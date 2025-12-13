import { state } from './state.js';
import { createContactElement, selectItem, clearSelection, showConfirmation, renderContact } from './ui.js';
import { fetchConversation } from './chat.js';
import { truncateText, formatElapsedTime } from './utils.js';

export function refreshContacts(force = false) {
	if (state.isSearchActive && !force) return;
	if (force && state.contactsPollController) {
		state.contactsPollController.abort();
	}
	if (!force && state.contactsPollController && !state.contactsPollController.signal.aborted) {
		return;
	}
	state.contactsPollController = new AbortController();
	const signal = state.contactsPollController.signal;
	const timeParam = force ? '' : state.contactsLastActiveTime;

	fetch('api/fetch_contacts.php?last_active_time=' + encodeURIComponent(timeParam), { signal })
		.then((r) => r.json())
		.then((data) => {
			if (data.error) throw new Error(data.error);
			if (data.last_active_time) {
				state.contactsLastActiveTime = data.last_active_time;
			}
			const contactsList = document.querySelector('.contacts-list');
			if (contactsList && Array.isArray(data.friends)) {
				contactsList.querySelectorAll('.contact-item[data-is-friend="false"]').forEach((el) => el.remove());
				contactsList.querySelectorAll('.text-muted').forEach((el) => {
					if (el.textContent && el.textContent.trim() === 'No results found') el.remove();
				});
				const selected = document.querySelector('.contact-item.is-selected');
				const selectedUsername = selected ? selected.getAttribute('data-username') : null;
				data.friends.forEach((friend) => {
					let el = document.querySelector(`.contact-item[data-username="${friend.username}"][data-is-friend="true"]`);
					if (!el) {
						el = createContactElement(friend, true);
					}
					const preview = el.querySelector('.contact-preview');
					if (preview) {
						let text = friend.last_message_text || 'Click to chat';
						if (friend.last_message_sender_id && Number(friend.last_message_sender_id) === Number(state.meId)) {
							text = 'You: ' + text;
						}
						text = truncateText(text, 40);
						if (friend.last_message_at) {
							const elapsed = formatElapsedTime(friend.last_message_at);
							if (elapsed) text += ' \u2022 ' + elapsed;
						}
						preview.textContent = text;
					}
					contactsList.appendChild(el);
				});
				if (selectedUsername) {
					const newSel = document.querySelector(`.contact-item[data-username="${selectedUsername}"][data-is-friend="true"]`);
					if (newSel) newSel.classList.add('is-selected');
				}
				initContactClicks();
			}
			// Groups logical removed
			state.contactsPollController = null;
			refreshContacts();
		})
		.catch((err) => {
			state.contactsPollController = null;
			if (err.name === 'AbortError') return;
			setTimeout(refreshContacts, 5000);
		});
}

export function performSearch(query) {
	const searchBox = document.querySelector('.search-box');
	const hintEl = document.querySelector('.search-hint');
	if (!query || query.trim() === '') {
		state.isSearchActive = false;
		if (hintEl) hintEl.textContent = '';
		refreshContacts(true);
		return;
	}
	state.isSearchActive = true;
	const placeholder = searchBox.placeholder;
	if (hintEl) hintEl.textContent = 'Searching...';
	searchBox.placeholder = 'Searching...';
	fetch('api/search_users.php?q=' + encodeURIComponent(query))
		.then((r) => r.json())
		.then((data) => {
			if (data.error) return;
			renderSearchResults(data.friends, data.non_friends);
			if (hintEl) {
				const count = (Array.isArray(data.friends) ? data.friends.length : 0) + (Array.isArray(data.non_friends) ? data.non_friends.length : 0);
				hintEl.textContent = count + (count === 1 ? ' match' : ' matches');
			}
		})
		.catch(() => {
			if (hintEl) hintEl.textContent = 'Error';
		})
		.finally(() => {
			searchBox.placeholder = placeholder;
		});
}

function renderSearchResults(friends, nonFriends) {
	const contactsList = document.querySelector('.contacts-list');
	if (!contactsList) return;
	const selected = document.querySelector('.contact-item.is-selected');
	const selectedUsername = selected ? selected.getAttribute('data-username') : null;
	contactsList.innerHTML = '';
	let itemsAdded = 0;
	if (Array.isArray(friends) && friends.length > 0) {
		friends.forEach((friend) => {
			const el = createContactElement(friend, true);
			contactsList.appendChild(el);
			itemsAdded++;
		});
	}
	if (Array.isArray(nonFriends) && nonFriends.length > 0) {
		nonFriends.forEach((nonFriend) => {
			const el = createContactElement(nonFriend, false);
			contactsList.appendChild(el);
			itemsAdded++;
		});
	}
	if (itemsAdded === 0) {
		const noRes = document.createElement('div');
		noRes.className = 'text-muted';
		noRes.textContent = 'No results found';
		contactsList.appendChild(noRes);
	}
	initContactClicks();
	if (selectedUsername) {
		const newSel = document.querySelector(`.contact-item[data-username="${selectedUsername}"]`);
		if (newSel) newSel.classList.add('is-selected');
	}
}

export function initContactClicks() {
	const contactItems = document.querySelectorAll('.contacts .contact-item');
	contactItems.forEach((el) => {
		el.onclick = function () {
			const username = el.getAttribute('data-username');
			const isFriend = el.getAttribute('data-is-friend') === 'true';
			if (!username) return;
			if (state.pollAbortController) {
				state.pollAbortController.abort();
				state.pollAbortController = null;
			}
			if (isFriend) {
				state.currentTargetType = 'friend';
				state.currentTarget = username;
				selectItem(el);
				history.pushState({ u: username }, '', '?u=' + encodeURIComponent(username));
				fetchConversation('tween/fetch_conversation.php?u=' + encodeURIComponent(username));
			} else {
				state.currentTargetType = 'non-friend';
				state.currentTarget = username;
				selectItem(el);
				showNonFriendView(username);
			}
		};
	});
}

export function showNonFriendView(username) {
	fetch('api/get_user_info.php?username=' + encodeURIComponent(username))
		.then((r) => r.json())
		.then((data) => {
			if (data.error) return;
			document.querySelector('.empty-state').style.display = 'none';
			document.querySelector('.chat-container').style.display = 'flex';
			document.querySelector('.message-input').style.display = 'none';
			
			const chatHeader = document.querySelector('.chat-header');
			if (chatHeader) {
				const contactName = chatHeader.querySelector('span');
				const icon = chatHeader.querySelector('i');
				const iconContainer = chatHeader.querySelector('.contact-icon-circle');
				if (contactName) contactName.textContent = data.full_name;
				if (iconContainer) {
					const i = iconContainer.querySelector('i');
					if (i) i.className = 'fa-solid fa-user';
				}
			}
			const messagesEl = document.querySelector('.messages');
			if (messagesEl) {
				messagesEl.innerHTML = '<div class="no-messages">Add as friend to chat..</div>';
			}

			const rightPanel = document.querySelector('.right-panel');
			rightPanel.classList.remove('hidden');
			const infoPanel = document.querySelector('.right-panel .info-panel');
			if (infoPanel) {
				infoPanel.innerHTML = '';
				const template = document.getElementById('non-friend-info-template');
				const clone = template.cloneNode(true);
				clone.style.display = '';
				clone.querySelector('h3').textContent = data.full_name;
				clone.querySelector('small').textContent = '@' + data.username;
				clone.querySelector('p').textContent = data.bio || '';
				
				const addFriendBtn = clone.querySelector('.btn-add-friend');
				const blockBtn = clone.querySelector('.btn-block');
				if (blockBtn) blockBtn.setAttribute('data-tween-id', data.id);
				
				if (addFriendBtn) {
					if (data.request_incoming) {
						addFriendBtn.innerHTML = '<i class="fa-solid fa-check"></i> Accept Request';
						addFriendBtn.onclick = function () {
							if (!data.incoming_parent_approved) {
								alert('This request is waiting for parent approval.');
								return;
							}
							const form = new FormData();
							form.append('request_id', data.incoming_request_id);
							fetch('tween/accept_friend.php', { method: 'POST', body: form })
								.then(r => r.json())
								.then(resp => {
									if (resp.success) {
										refreshContacts(true);
										loadFriendsData();
										window.location.reload();
									}
								});
						};
					} else if (data.request_pending) {
						addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Cancel Request';
						addFriendBtn.onclick = () => sendFriendRequest(username, 'cancel');
					} else {
						addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Add Friend';
						addFriendBtn.onclick = () => sendFriendRequest(username, 'send');
					}
				}
				
				if (blockBtn) {
					blockBtn.addEventListener('click', function () {
						showConfirmation({title: 'Block User', icon: '<i class="fa-solid fa-ban"></i>', message: 'Block this person?', confirmText: 'Block'},
						function() {
							const form = new FormData();
							form.append('tween_id', data.id);
							fetch('tween/block_user.php', { method: 'POST', body: form })
							.then(r => r.json())
							.then(res => {
								if (res.success) {
									refreshContacts(true);
									loadFriendsData();
									document.querySelector('.empty-state').style.display = '';
									document.querySelector('.chat-container').style.display = 'none';
								}
							});
						});
					});
				}
				infoPanel.appendChild(clone);
			}
		});
}

function sendFriendRequest(username, action = 'send') {
	const form = new FormData();
	form.append('username', username);
	if (action === 'cancel') form.append('action', 'cancel');
	const addFriendBtn = document.querySelector('.btn-add-friend');
	if (addFriendBtn) addFriendBtn.disabled = true;
	fetch('tween/add_friend.php', { method: 'POST', body: form })
		.then(r => r.json())
		.then(data => {
			if (data.error) {
				alert(data.error);
				if (addFriendBtn) addFriendBtn.disabled = false;
				return;
			}
			if (data.success) {
				if (action === 'cancel') {
					if (addFriendBtn) {
						addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Add Friend';
						addFriendBtn.disabled = false;
						addFriendBtn.onclick = () => sendFriendRequest(username, 'send');
					}
				} else {
					if (addFriendBtn) {
						addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Cancel Request';
						addFriendBtn.disabled = false;
						addFriendBtn.onclick = () => sendFriendRequest(username, 'cancel');
					}
				}
			}
		})
		.catch(() => {
			if (addFriendBtn) addFriendBtn.disabled = false;
		});
}

// Handlers for right panel buttons (Unfriend, Block)
export function attachContactCardListeners() {
	const infoPanel = document.querySelector('.right-panel .info-panel');
	if (!infoPanel) return;
	const unfriendBtn = infoPanel.querySelector('button[title="Unfriend"]');
	const blockBtn = infoPanel.querySelector('button[title="Block"]');
	
	if (unfriendBtn && !unfriendBtn.dataset.listener) {
		unfriendBtn.dataset.listener = 'true';
		unfriendBtn.addEventListener('click', function() {
			const friendId = unfriendBtn.getAttribute('data-tween-id');
			showConfirmation({ title: 'Unfriend', icon: '<i class="fa-solid fa-user-xmark"></i>', message: 'Unfriend this person?', confirmText: 'Unfriend' }, function() {
				const form = new FormData();
				form.append('friend_id', friendId);
				fetch('tween/unfriend.php', { method: 'POST', body: form }).then(r=>r.json()).then(data => {
					if (data.success) {
						loadFriendsData();
						refreshContacts(true);
						// Close view
						document.querySelector('.empty-state').style.display = '';
						document.querySelector('.chat-container').style.display = 'none';
						document.querySelector('.right-panel').classList.add('hidden');
					}
				});
			});
		});
	}
	
	if (blockBtn && !blockBtn.dataset.listener) {
		blockBtn.dataset.listener = 'true';
		blockBtn.addEventListener('click', function() {
			const tweenId = blockBtn.getAttribute('data-tween-id');
			showConfirmation({ title: 'Block User', icon: '<i class="fa-solid fa-ban"></i>', message: 'Block this person?', confirmText: 'Block' }, function() {
				const form = new FormData();
				form.append('tween_id', tweenId);
				fetch('tween/block_user.php', { method: 'POST', body: form }).then(r=>r.json()).then(data => {
					if (data.success) {
						loadFriendsData();
						refreshContacts(true);
						document.querySelector('.empty-state').style.display = '';
						document.querySelector('.chat-container').style.display = 'none';
						document.querySelector('.right-panel').classList.add('hidden');
					}
				});
			});
		});
	}
}

// Friends Modal Logic
export function loadFriendsData() {
	fetch('api/fetch_friends_data.php')
		.then(r => r.json())
		.then(data => {
			if (data.error) return;
			// Populate lists
			const friendsList = document.getElementById('current-friends-list');
			const blockedList = document.getElementById('blocked-users-list');
			const requestsList = document.getElementById('pending-requests-list');
			
			if (friendsList) {
				friendsList.innerHTML = '';
				if (data.friends && data.friends.length) {
					data.friends.forEach(f => {
						const div = document.createElement('div');
						div.className = 'friend-item';
						div.setAttribute('data-username', f.username);
						div.innerHTML = `
							<span class="friend-name">${f.full_name}</span>
							<div class="friend-actions">
								<button class="btn-friend-action btn-unfriend" data-tween-id="${f.id||f.tween_id}" title="Unfriend"><i class="fa-solid fa-user-xmark"></i></button>
								<button class="btn-friend-action btn-block-user" data-tween-id="${f.id||f.tween_id}" title="Block"><i class="fa-solid fa-ban"></i></button>
							</div>`;
						friendsList.appendChild(div);
					});
				} else {
					friendsList.innerHTML = '<p class="text-muted">No friends yet.</p>';
				}
			}
			
			if (blockedList) {
				blockedList.innerHTML = '';
				if (data.blocked && data.blocked.length) {
					data.blocked.forEach(u => {
						const div = document.createElement('div');
						div.className = 'friend-item';
						div.innerHTML = `<span class="friend-name">${u.full_name}</span>
							<div class="friend-actions"><button class="btn-friend-action btn-unblock" data-tween-id="${u.id||u.tween_id}" title="Unblock"><i class="fa-solid fa-unlock"></i></button></div>`;
						blockedList.appendChild(div);
					});
				} else {
					blockedList.innerHTML = '<p class="text-muted">No blocked users.</p>';
				}
			}
			
			if (requestsList) {
				requestsList.innerHTML = '';
				if (data.pending_requests && data.pending_requests.length) {
					data.pending_requests.forEach(r => {
						const div = document.createElement('div');
						div.className = 'friend-item';
						div.innerHTML = `<span class="friend-name">${r.full_name}</span>
							<div class="friend-actions">
								<button class="btn-friend-action btn-accept" data-request-id="${r.request_id}"><i class="fa-solid fa-check"></i></button>
								<button class="btn-friend-action btn-decline" data-request-id="${r.request_id}"><i class="fa-solid fa-xmark"></i></button>
							</div>`;
						requestsList.appendChild(div);
					});
					const dot = document.getElementById('friends-notification-dot');
					if (dot) dot.style.display = 'block';
				} else {
					requestsList.innerHTML = '<p class="text-muted">No pending requests.</p>';
					const dot = document.getElementById('friends-notification-dot');
					if (dot) dot.style.display = 'none';
				}
			}
			attachFriendActionListeners();
		});
}

function attachFriendActionListeners() {
	document.querySelectorAll('.btn-unfriend').forEach(btn => {
		if (btn.dataset.ls) return;
		btn.dataset.ls = '1';
		btn.addEventListener('click', function() {
			const tweenId = this.dataset.tweenId;
			const form = new FormData();
			form.append('friend_id', tweenId);
			fetch('tween/unfriend.php', { method: 'POST', body: form }).then(r=>r.json()).then(d => {
				if(d.success) { loadFriendsData(); refreshContacts(true); }
			});
		});
	});
	document.querySelectorAll('.btn-block-user').forEach(btn => {
		if (btn.dataset.ls) return;
		btn.dataset.ls = '1';
		btn.addEventListener('click', function() {
			const tweenId = this.dataset.tweenId;
			const form = new FormData();
			form.append('tween_id', tweenId);
			fetch('tween/block_user.php', { method: 'POST', body: form }).then(r=>r.json()).then(d => {
				if(d.success) { loadFriendsData(); refreshContacts(true); }
			});
		});
	});
	document.querySelectorAll('.btn-unblock').forEach(btn => {
		if (btn.dataset.ls) return;
		btn.dataset.ls = '1';
		btn.addEventListener('click', function() {
			const tweenId = this.dataset.tweenId;
			const form = new FormData();
			form.append('tween_id', tweenId);
			fetch('tween/unblock_user.php', { method: 'POST', body: form }).then(r=>r.json()).then(d => {
				if(d.success) { loadFriendsData(); refreshContacts(true); }
			});
		});
	});
	document.querySelectorAll('.btn-accept').forEach(btn => {
		if (btn.dataset.ls) return;
		btn.dataset.ls = '1';
		btn.addEventListener('click', function() {
			const form = new FormData();
			form.append('request_id', this.dataset.requestId);
			fetch('tween/accept_friend.php', { method: 'POST', body: form }).then(r=>r.json()).then(d => {
				if(d.success) { loadFriendsData(); refreshContacts(true); }
			});
		});
	});
	// decline logic similar...
	document.querySelectorAll('.btn-decline').forEach(btn => {
		if (btn.dataset.ls) return;
		btn.dataset.ls = '1';
		btn.addEventListener('click', function() {
			const form = new FormData();
			form.append('request_id', this.dataset.requestId);
			fetch('tween/decline_friend.php', { method: 'POST', body: form }).then(r=>r.json()).then(d => {
				loadFriendsData();
			});
		});
	});
}
