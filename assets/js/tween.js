document.addEventListener('DOMContentLoaded', function () {
	// Cookie helpers
	function setCookie(name, value, days = 365) {
		const d = new Date();
		d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
		document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/';
	}

	function getCookie(name) {
		const nameEQ = name + '=';
		const ca = document.cookie.split(';');
		for (let i = 0; i < ca.length; i++) {
			let c = ca[i];
			while (c.charAt(0) == ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
		}
		return null;
	}

	let toggleBtn; // Declare here to avoid redeclaration

	// Apply saved preferences on load
	const savedTheme = getCookie('theme');
	if (savedTheme === 'dark') {
		document.body.setAttribute('data-theme', 'dark');
		const themeIcon = document.querySelector('#theme-toggle i');
		if (themeIcon) themeIcon.className = 'fa-jelly-fill fa-regular fa-lightbulb';
	}
	// Show/hide message input based on chat visibility
	const messageInputEl = document.querySelector('.message-input');
	const chatContainerEl = document.querySelector('.chat-container');
	if (messageInputEl && chatContainerEl) {
		const chatVisible = window.getComputedStyle(chatContainerEl).display !== 'none';
		messageInputEl.style.display = chatVisible ? 'flex' : 'none';
		if (chatVisible) setTimeout(scrollToBottom, 0);
	}
	let currentTarget = null;
	let currentTargetType = null;
	let pollAbortController = null;
	let lastMessageId = 0;
	let lastActiveTime = 0;
	let meId = null;
	// BroadcastChannel for local tab sync and a per-tab stable id
	const TAB_ID = 'tab-' + Math.random().toString(36).substr(2, 9);
	const bc = window.BroadcastChannel ? new BroadcastChannel('barta-messages') : null;
	function renderMessages(messages, meId) {
		// Clear existing messages
		const messagesEl = document.querySelector('.messages');
		if (!messagesEl) return;
		messagesEl.innerHTML = '';
		if (!messages || messages.length === 0) {
			const noMsgTemplate = document.getElementById('no-messages-template');
			const noMsgClone = noMsgTemplate.firstElementChild.cloneNode(true);
			messagesEl.appendChild(noMsgClone);
			return;
		}
		// Render each message with prev/next context
		for (let i = 0; i < messages.length; i++) {
			const msg = messages[i];
			const template = document.getElementById('message-template');
			const wrapper = template.firstElementChild.cloneNode(true);
			const isOwn = Number(msg.sender_id) === Number(meId);
			const prevSame = i > 0 && messages[i - 1].sender_id === msg.sender_id;
			const nextSame = i < messages.length - 1 && messages[i + 1].sender_id === msg.sender_id;
			const showSender = !prevSame;
			wrapper.className = 'message-wrapper' + (isOwn ? ' own' : '') + (!showSender ? ' no-sender' : '');
			// store sent time on wrapper for date separator logic
			wrapper.setAttribute('data-sent-at', msg.sent_at || new Date().toISOString());
			wrapper.setAttribute('data-sender-id', msg.sender_id);
			wrapper.setAttribute('data-message-id', msg.id);
			if (!isOwn && msg.sender_name) {
				wrapper.setAttribute('data-sender-name', msg.sender_name.split(' ')[0].toUpperCase());
			}
			const sender = wrapper.querySelector('.sender');
			if (showSender) {
				sender.textContent = isOwn ? 'Me' : (msg.sender_name || '').split(' ')[0].toUpperCase();
			} else {
				sender.style.display = 'none';
			}
			const messageEl = wrapper.querySelector('.message');
			messageEl.className = 'message' + (isOwn ? ' own' : '');
			// corner cuts
			if (prevSame) {
				messageEl.classList.add(isOwn ? 'cut-top-right' : 'cut-top-left');
			} else {
				messageEl.classList.remove('cut-top-right', 'cut-top-left');
			}
			if (nextSame) {
				messageEl.classList.add(isOwn ? 'cut-bottom-right' : 'cut-bottom-left');
			} else {
				messageEl.classList.remove('cut-bottom-right', 'cut-bottom-left');
			}
			const textDiv = messageEl.querySelector('.text');
			textDiv.textContent = msg.text_content || '';
			const timestampDiv = messageEl.querySelector('.timestamp');
			timestampDiv.textContent = formatTime(msg.sent_at || new Date().toISOString());
			messagesEl.appendChild(wrapper);
		}
		// Scroll chat to bottom after rendering
		// ensure date separators are inserted
		insertDateSeparators();
		scrollToBottom();
	}

	function renderContact(contact, type) {
		const chatHeader = document.querySelector('.chat-header');
		if (!chatHeader) return;

		const iconContainer = chatHeader.querySelector('.contact-icon-circle');
		const contactName = chatHeader.querySelector('span');
		const icon = iconContainer.querySelector('i');

		if (contactName) {
			contactName.textContent = type === 'friend' ? contact.full_name : contact.group_name;
		}
		if (icon) {
			icon.className = 'fa-solid fa-' + (type === 'friend' ? 'user' : 'users');
		}
		const infoPanel = document.querySelector('.right-panel .info-panel');
		if (!infoPanel) return;
		infoPanel.innerHTML = ''; // clear
		const templateId = type === 'friend' ? 'friend-info-template' : 'group-info-template';
		const template = document.getElementById(templateId);
		const clone = template.cloneNode(true);
		clone.style.display = ''; // remove display none
		const h3 = clone.querySelector('h3');
		const small = clone.querySelector('small');
		const p = clone.querySelector('p');
		if (type === 'friend') {
			h3.textContent = contact.full_name;
			small.textContent = '@' + contact.username;
			p.textContent = contact.bio || '';
			// Add tween_id to block button
			const blockBtn = clone.querySelector('.btn-block');
			if (blockBtn) {
				const id = contact.id || contact.tween_id || contact.tweenId || contact.tweenId || '';
				blockBtn.setAttribute('data-tween-id', id);
			}
		} else {
			h3.textContent = contact.group_name;
			p.textContent = 'Members: ' + (contact.members || []).map((m) => m.full_name).join(', ');
		}
		// Append the built clone into right panel
		infoPanel.appendChild(clone);

		// Attach event listeners to the new buttons
		const unfriendBtn = clone.querySelector('button[title="Unfriend"]');
		const blockBtn = clone.querySelector('button[title="Block"]');

		if (unfriendBtn) {
			const uid = contact.id || contact.tween_id || contact.tweenId || '';
			unfriendBtn.setAttribute('data-tween-id', uid);
			unfriendBtn.addEventListener('click', function () {
				showConfirmation(
					{
						title: 'Unfriend',
						icon: '<i class="fa-solid fa-user-xmark"></i>',
						message: 'Are you sure you want to unfriend this person?',
						confirmText: 'Unfriend',
					},
					function () {
						const friendId = unfriendBtn.getAttribute('data-tween-id');
						const form = new FormData();
						form.append('friend_id', friendId);
						fetch('tween/unfriend.php', {
							method: 'POST',
							body: form,
						})
							.then((r) => r.json())
							.then((data) => {
								if (data.success) {
									loadFriendsData();
									refreshContacts(true);
									const contactItem = document.querySelector(`.contact-item[data-username="${contact.username}"]`);
									if (contactItem) contactItem.remove();

									// Close chat if it was the unfriend target
									if (currentTarget === contact.username && currentTargetType === 'friend') {
										document.querySelector('.empty-state').style.display = '';
										document.querySelector('.chat-container').style.display = 'none';
										document.querySelector('.message-input').style.display = 'none';
										document.querySelector('.middle-panel').classList.add('expanded');
										document.querySelector('.right-panel').classList.add('hidden');
										clearSelection();
										if (pollAbortController) {
											pollAbortController.abort();
											pollAbortController = null;
										}
										currentTarget = null;
										currentTargetType = null;
										lastMessageId = 0;
										lastActiveTime = 0;
									}
								} else {
									console.error(data.message || 'Failed to unfriend');
								}
							})
							.catch((err) => console.error(err));
					}
				);
			});
		}

		if (blockBtn) {
			blockBtn.setAttribute('data-tween-id', contact.tween_id);
			blockBtn.addEventListener('click', function () {
				showConfirmation(
					{
						title: 'Block User',
						icon: '<i class="fa-solid fa-ban"></i>',
						message: 'Are you sure you want to block this person?',
						confirmText: 'Block',
					},
					function () {
						const tweenId = blockBtn.getAttribute('data-tween-id');
						console.debug('block_user: confirmed (right-panel friend)', tweenId);
						const form = new FormData();
						form.append('tween_id', tweenId);
						fetch('tween/block_user.php', {
							method: 'POST',
							body: form,
						})
							.then((r) => r.json())
							.then((data) => {
								if (data.success) {
									loadFriendsData();
									refreshContacts(true);
									// Remove from left panel contacts list
									const username = document.querySelector('.user-details small').textContent.slice(1);
									const contactItem = document.querySelector(`.contact-item[data-username="${username}"]`);
									if (contactItem) contactItem.remove();
									// If this was the currently selected friend, close the chat
									if (currentTarget === contact.username && currentTargetType === 'friend') {
										document.querySelector('.empty-state').style.display = '';
										document.querySelector('.chat-container').style.display = 'none';
										document.querySelector('.message-input').style.display = 'none';
										document.querySelector('.middle-panel').classList.add('expanded');
										document.querySelector('.right-panel').classList.add('hidden');
										clearSelection();
										if (pollAbortController) {
											pollAbortController.abort();
											pollAbortController = null;
										}
										currentTarget = null;
										currentTargetType = null;
										lastMessageId = 0;
										lastActiveTime = 0;
									}
								} else {
									console.error(data.error || 'Failed to block user');
								}
							})
							.catch((err) => console.error(err));
					}
				);
			});
		}
	}

	function formatTime(datetime) {
		const d = new Date(datetime);
		let hours = d.getHours();
		const minutes = d.getMinutes().toString().padStart(2, '0');
		const ampm = hours >= 12 ? 'PM' : 'AM';
		hours = hours % 12 || 12;
		return hours + ':' + minutes + ' ' + ampm;
	}

	// Format a friendly date label used in the date separator
	function formatDateLabel(datetime) {
		const d = new Date(datetime);
		const today = new Date();
		const yesterday = new Date();
		yesterday.setDate(today.getDate() - 1);
		if (d.toDateString() === today.toDateString()) return 'Today';
		if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
		return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	// Insert date separators before the first message and when day changes between messages
	function insertDateSeparators() {
		const messagesEl = document.querySelector('.messages');
		if (!messagesEl) return;
		// Remove existing separators
		messagesEl.querySelectorAll('.date-separator').forEach((el) => el.remove());
		const wrappers = Array.from(messagesEl.querySelectorAll('.message-wrapper'));
		let prevDateStr = null;
		for (let i = 0; i < wrappers.length; i++) {
			const w = wrappers[i];
			const sentAt = w.getAttribute('data-sent-at');
			const d = sentAt ? new Date(sentAt) : new Date();
			const ds = d.toDateString();
			if (i === 0 || ds !== prevDateStr) {
				// add separator element before wrapper
				const separator = document.createElement('div');
				separator.className = 'date-separator';
				const small = document.createElement('small');
				small.textContent = formatDateLabel(d);
				separator.appendChild(small);
				messagesEl.insertBefore(separator, w);
				prevDateStr = ds;
			}
		}
	}

	// helper: truncate text safely
	function truncateText(text, maxLen = 40) {
		if (!text) return '';
		if (text.length <= maxLen) return text;
		return text.substring(0, maxLen - 3) + '...';
	}

	// Returns a short elapsed time label for a given timestamp
	function formatElapsedTime(datetime) {
		if (!datetime) return '';
		// Support Date objects or timestamp strings
		const dt = typeof datetime === 'object' ? datetime : new Date(datetime);
		if (isNaN(dt.getTime())) return '';
		const seconds = Math.floor((Date.now() - dt.getTime()) / 1000);
		if (seconds < 60) return 'now';
		if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
		if (seconds < 86400) return Math.floor(seconds / 3600) + 'h';
		if (seconds < 604800) return Math.floor(seconds / 86400) + 'd';
		if (seconds < 31536000) return Math.floor(seconds / 604800) + 'w';
		return Math.floor(seconds / 31536000) + 'y';
	}

	function updateContactPreview(targetType, target, msg, meId) {
		if (!targetType || !target || !msg) return;
		let previewText = msg.text_content || '';
		if (Number(msg.sender_id) === Number(meId)) previewText = 'You: ' + previewText;
		previewText = truncateText(previewText, 40);
		// Append elapsed time if message includes sent_at
		if (msg && msg.sent_at) {
			const elapsed = formatElapsedTime(msg.sent_at);
			if (elapsed) previewText += ' \u2022 ' + elapsed;
		}
		if (targetType === 'friend') {
			const el = document.querySelector(`.contact-item[data-username="${target}"] .contact-preview`);
			if (el) el.textContent = previewText;
		} else if (targetType === 'group') {
			const el = document.querySelector(`.group-item[data-group-id="${target}"] .contact-preview`);
			if (el) el.textContent = previewText;
		}
	}

	function showNonFriendView(username) {
		// Fetch user info
		fetch('api/get_user_info.php?username=' + encodeURIComponent(username))
			.then((r) => r.json())
			.then((data) => {
				if (data.error) {
					console.error(data.error);
					return;
				}

				// Show chat container, hide empty state
				document.querySelector('.empty-state').style.display = 'none';
				document.querySelector('.chat-container').style.display = 'flex';
				document.querySelector('.message-input').style.display = 'none'; // Hide message input for non-friends

				// Update chat header
				const chatHeader = document.querySelector('.chat-header');
				if (chatHeader) {
					const iconContainer = chatHeader.querySelector('.contact-icon-circle');
					const contactName = chatHeader.querySelector('span');
					const icon = iconContainer.querySelector('i');

					if (contactName) {
						contactName.textContent = data.full_name;
					}
					if (icon) {
						icon.className = 'fa-solid fa-user';
					}
				}

				// Show "Add as friend to chat" message
				const messagesEl = document.querySelector('.messages');
				if (messagesEl) {
					messagesEl.innerHTML = '<div class="no-messages">Add as friend to chat..</div>';
				}

				// Update right panel with non-friend info
				const rightPanel = document.querySelector('.right-panel');
				const middlePanel = document.querySelector('.middle-panel');
				rightPanel.classList.remove('hidden');
				middlePanel.classList.remove('expanded');

				const infoPanel = document.querySelector('.right-panel .info-panel');
				if (infoPanel) {
					infoPanel.innerHTML = '';
					const template = document.getElementById('non-friend-info-template');
					const clone = template.cloneNode(true);
					clone.style.display = '';

					const h3 = clone.querySelector('h3');
					const small = clone.querySelector('small');
					const p = clone.querySelector('p');
					const addFriendBtn = clone.querySelector('.btn-add-friend');
					const blockBtn = clone.querySelector('.btn-block');

					h3.textContent = data.full_name;
					small.textContent = '@' + data.username;
					p.textContent = data.bio || '';

					// Add tween_id to block button
					if (blockBtn) blockBtn.setAttribute('data-tween-id', data.id);

					// Add friend request handler
					if (addFriendBtn) {
						if (data.request_incoming) {
							addFriendBtn.innerHTML = '<i class="fa-solid fa-check"></i> Accept Request';
							addFriendBtn.disabled = false;
							addFriendBtn.onclick = function () {
								if (!data.incoming_parent_approved) {
									alert('This request is waiting for parent approval.');
									return;
								}
								const form = new FormData();
								form.append('request_id', data.incoming_request_id);
								fetch('tween/accept_friend.php', {
									method: 'POST',
									body: form,
								})
									.then((r) => r.json())
									.then((resp) => {
										if (resp.success) {
											loadFriendsData();
											refreshContacts(true);
											// Reload conversation as friend
											window.location.reload(); 
										} else {
											console.error(resp.error || 'Failed to accept request');
										}
									})
									.catch((err) => console.error(err));
							};
						} else if (data.request_pending) {
							addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Cancel Request';
							addFriendBtn.disabled = false;
							addFriendBtn.onclick = function () {
								sendFriendRequest(username, 'cancel');
							};
						} else {
							addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Add Friend';
							addFriendBtn.disabled = false;
							addFriendBtn.onclick = function () {
								sendFriendRequest(username, 'send');
							};
						}
					}

					// Attach block button listener
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
									const tweenId = blockBtn.getAttribute('data-tween-id');
									console.debug('block_user: confirmed (right-panel friend) ', tweenId);
									const form = new FormData();
									form.append('tween_id', tweenId);
									fetch('tween/block_user.php', {
										method: 'POST',
										body: form,
									})
										.then((r) => r.json())
										.then((data) => {
											if (data.success) {
												refreshContacts(true);
												// Remove from left panel contacts list if it was a friend
												const username = document.querySelector('.user-details small').textContent.slice(1);
												const contactItem = document.querySelector(`.contact-item[data-username="${username}"]`);
												if (contactItem) contactItem.remove();
												// Close the chat view
												document.querySelector('.empty-state').style.display = '';
												document.querySelector('.chat-container').style.display = 'none';
												document.querySelector('.message-input').style.display = 'none';
												document.querySelector('.middle-panel').classList.add('expanded');
												document.querySelector('.right-panel').classList.add('hidden');
												clearSelection();
												if (pollAbortController) {
													pollAbortController.abort();
													pollAbortController = null;
												}
												currentTarget = null;
												currentTargetType = null;
												lastMessageId = 0;
												lastActiveTime = 0;
											} else {
												console.error(data.error || 'Failed to block user');
											}
										})
										.catch((err) => console.error(err));
								}
							);
						});
					}

					infoPanel.appendChild(clone);
				}
			})
			.catch((err) => console.error(err));
	}

	function sendFriendRequest(username, action = 'send') {
		const form = new FormData();
		form.append('username', username);
		if (action === 'cancel') form.append('action', 'cancel');

		const addFriendBtn = document.querySelector('.btn-add-friend');
		if (addFriendBtn) {
			addFriendBtn.disabled = true;
		}

		fetch('tween/add_friend.php', {
			method: 'POST',
			body: form,
		})
			.then((r) => r.json())
			.then((data) => {
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
							addFriendBtn.onclick = function () {
								sendFriendRequest(username, 'send');
							};
						}
					} else {
						if (addFriendBtn) {
							addFriendBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Cancel Request';
							addFriendBtn.disabled = false;
							addFriendBtn.onclick = function () {
								sendFriendRequest(username, 'cancel');
							};
						}
					}
				}
			})
			.catch((err) => {
				console.error(err);
				if (addFriendBtn) addFriendBtn.disabled = false;
			});
	}

	function fetchConversation(url) {
		fetch(url)
			.then((r) => r.json())
			.then((data) => {
				if (data.error) {
					return;
				}
				// show chat container, hide empty state
				document.querySelector('.empty-state').style.display = 'none';
				document.querySelector('.chat-container').style.display = 'flex';
				document.querySelector('.message-input').style.display = 'flex';
				// Set right panel state based on cookie
				const rightPanelHidden = getCookie('rightPanelHidden') === 'true';
				const rightPanel = document.querySelector('.right-panel');
				const middlePanel = document.querySelector('.middle-panel');
				if (rightPanelHidden) {
					rightPanel.classList.add('hidden');
					middlePanel.classList.add('expanded');
				} else {
					rightPanel.classList.remove('hidden');
					middlePanel.classList.remove('expanded');
				}
				// Update toggle button icon
				const toggleBtn = document.querySelector('.toggle-right-panel-btn');
				if (toggleBtn) {
					if (rightPanelHidden) {
						toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-left"></i>';
					} else {
						toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-right"></i>';
					}
				}
				// render messages and contact info
				meId = data.me_id;
				renderMessages(data.messages, data.me_id);
				renderContact(data.contact, data.type); // set current target for sending
				// (No move-to-top on click) — keep ordering changes only for new message events
				if (data.type === 'friend') {
					currentTargetType = 'friend';
					currentTarget = data.contact.username;
				} else if (data.type === 'group') {
					currentTargetType = 'group';
					currentTarget = data.contact.id;
				}
				// Set last message ID for polling
				lastMessageId = data.messages.length > 0 ? Math.max(...data.messages.map((m) => m.id)) : 0;
				lastActiveTime = data.last_active_time || 0;
				// Start polling for new messages
				startPolling();
			})
			.catch((err) => {});
	}

	// Listen for BroadcastChannel messages (instant updates among same-user tabs)
	if (bc) {
		bc.onmessage = function (event) {
			const payload = event.data || {};
			console.debug && console.debug('bc.onmessage received', payload);
			const { type, messageId, newText, message, me_id, target_type, target } = payload;
			// Only handle events if we're viewing a conversation
			if (!currentTargetType || !currentTarget) return;
			// Ignore our own broadcasts
			if (payload.source && payload.source === TAB_ID) return;
			if (type === 'edit') {
				if (target_type !== currentTargetType || String(target) !== String(currentTarget)) return;
				const wrapper = document.querySelector(`.message-wrapper[data-message-id="${messageId}"]`);
				if (wrapper) {
					const textEl = wrapper.querySelector('.text');
					if (textEl) textEl.textContent = newText;
				}
				return;
			}
			if (type === 'delete') {
				if (target_type !== currentTargetType || String(target) !== String(currentTarget)) return;
				const wrapper = document.querySelector(`.message-wrapper[data-message-id="${messageId}"]`);
				if (wrapper) {
					wrapper.remove();
					recalculateMessageUI();
				}
				return;
			}
			if (type === 'send' && message) {
				console.debug && console.debug('bc.onmessage send', { message, target_type, target });
				// Check if this message is for the conversation we're currently viewing
				if (target_type === currentTargetType && String(target) === String(currentTarget)) {
					// Viewing this conversation - append message
					if (!document.querySelector(`.message-wrapper[data-message-id="${message.id}"]`)) {
						appendMessage(message, me_id);
						lastMessageId = Math.max(lastMessageId, message.id);
					}
				}
				// Always update contact preview and move to top (whether viewing this conversation or not)
				updateContactPreview(target_type, target, message, me_id);
				moveContactToTop(target_type === 'friend' ? 'friend' : 'group', target);
			}
			return;
		};
	}

	// Move a contact or group to the top of its list
	function moveContactToTop(type, identifier) {
		if (type === 'friend') {
			const contactsList = document.querySelector('.contacts-list');
			const item = document.querySelector(`.contact-item[data-username="${identifier}"][data-is-friend="true"]`);
			if (contactsList && item) {
				contactsList.insertBefore(item, contactsList.firstChild);
			}
		} else if (type === 'group') {
			const groupsList = document.querySelector('.groups-list');
			const item = document.querySelector(`.group-item[data-group-id="${identifier}"]`);
			if (groupsList && item) {
				groupsList.insertBefore(item, groupsList.firstChild);
			}
		}
	}

	// Highlight selected contact
	function clearSelection() {
		document.querySelectorAll('.contacts .contact-item, .groups .group-item').forEach((el) => el.classList.remove('is-selected'));
	}
	function selectItem(el) {
		clearSelection();
		el.classList.add('is-selected');
	}

	// intercept clicks for friends & groups
	function initContactClicks() {
		const contactItems = document.querySelectorAll('.contacts .contact-item');
		contactItems.forEach((el) => {
			el.onclick = function (e) {
				const username = el.getAttribute('data-username');
				const isFriend = el.getAttribute('data-is-friend') === 'true';
				if (!username) return;
				// Stop previous polling
				if (pollAbortController) {
					pollAbortController.abort();
					pollAbortController = null;
				}

				if (isFriend) {
					// Existing friend - load conversation
					currentTargetType = 'friend';
					currentTarget = username;
					const url = 'tween/fetch_conversation.php?u=' + encodeURIComponent(username);
					selectItem(el);
					history.pushState({ u: username }, '', '?u=' + encodeURIComponent(username));
					fetchConversation(url);
				} else {
					// Non-friend - show add friend view
					currentTargetType = 'non-friend';
					currentTarget = username;
					selectItem(el);
					showNonFriendView(username);
				}
			};
		});

		// const groupItems = document.querySelectorAll('.groups .group-item');
		// groupItems.forEach((el) => {
		// 	el.onclick = function (e) {
		// 		const groupId = el.getAttribute('data-group-id');
		// 		if (!groupId) return;
		// 		// Stop previous polling
		// 		if (pollAbortController) {
		// 			pollAbortController.abort();
		// 			pollAbortController = null;
		// 		}
		// 		currentTargetType = 'group';
		// 		currentTarget = groupId;
		// 		const url = 'tween/fetch_conversation.php?group=' + encodeURIComponent(groupId);
		// 		selectItem(el);
		// 		history.pushState({ group: groupId }, '', '?group=' + encodeURIComponent(groupId));
		// 		fetchConversation(url);
		// 	};
		// });
	}

	// Back/forward navigation without full page reload
	window.addEventListener('popstate', function (e) {
		// No state: show empty state
		if (!e.state) {
			document.querySelector('.empty-state').style.display = '';
			document.querySelector('.chat-container').style.display = 'none';
			if (document.querySelector('.message-input')) document.querySelector('.message-input').style.display = 'none';
			document.querySelector('.middle-panel').classList.add('expanded');
			document.querySelector('.right-panel').classList.add('hidden');
			clearSelection();
			// Stop polling
			if (pollAbortController) {
				pollAbortController.abort();
				pollAbortController = null;
			}
			currentTarget = null;
			currentTargetType = null;
			lastMessageId = 0;
			lastActiveTime = 0;
			return;
		}
		// Load conversation based on state
		if (e.state && e.state.u) {
			fetchConversation('tween/fetch_conversation.php?u=' + encodeURIComponent(e.state.u));
		}
		if (e.state && e.state.group) {
			fetchConversation('tween/fetch_conversation.php?group=' + encodeURIComponent(e.state.group));
		}
	});

	// Initialize contact click handlers
	initContactClicks();

	// Search functionality
	const searchBox = document.querySelector('.search-box');
	let searchTimeout = null;
	let isSearchActive = false;
	let originalFriends = []; // Cache original friends list
	let originalGroups = []; // Cache original groups list

	function performSearch(query) {
		if (!query || query.trim() === '') {
			// Restore original lists
			isSearchActive = false;
			const hintEl = document.querySelector('.search-hint');
			if (hintEl) hintEl.textContent = '';
			refreshContacts(true);
			return;
		}
		isSearchActive = true;
		console.debug('performSearch', query);
		// Visual feedback
		const placeholder = searchBox.placeholder;
		const hintEl = document.querySelector('.search-hint');
		if (hintEl) hintEl.textContent = 'Searching...';
		searchBox.placeholder = 'Searching...';
		fetch('api/search_users.php?q=' + encodeURIComponent(query))
			.then((r) => {
				// For debugging, capture the raw response first
				return r.text().then((text) => {
					try {
						return JSON.parse(text);
					} catch (e) {
						console.error('search_users response non-JSON', text);
						throw e;
					}
				});
			})
			.then((data) => {
				console.debug('search results', data);
				if (data.error) return;
				renderSearchResults(data.friends, data.non_friends);
				if (hintEl) {
					const count = (Array.isArray(data.friends) ? data.friends.length : 0) + (Array.isArray(data.non_friends) ? data.non_friends.length : 0);
					hintEl.textContent = count + (count === 1 ? ' match' : ' matches');
				}
			})
			.catch((err) => {
				console.error('performSearch error', err);
				if (hintEl) hintEl.textContent = 'Error';
			})
			.finally(() => {
				searchBox.placeholder = placeholder;
			});
	}

	function renderSearchResults(friends, nonFriends) {
		const contactsList = document.querySelector('.contacts-list');
		if (!contactsList) return;
		console.debug('renderSearchResults', { friendsCount: friends?.length || 0, nonFriendsCount: nonFriends?.length || 0 });

		// Remember selection
		const selected = document.querySelector('.contact-item.is-selected');
		const selectedUsername = selected ? selected.getAttribute('data-username') : null;
		const selectedIsFriend = selected ? selected.getAttribute('data-is-friend') === 'true' : false;

		// Clear list
		contactsList.innerHTML = '';

		let itemsAdded = 0;
		// Render friends matching search
		if (Array.isArray(friends) && friends.length > 0) {
			friends.forEach((friend) => {
				const el = createContactElement(friend, true);
				contactsList.appendChild(el);
				itemsAdded++;
			});
		}

		// Render non-friends
		if (Array.isArray(nonFriends) && nonFriends.length > 0) {
			nonFriends.forEach((nonFriend) => {
				const el = createContactElement(nonFriend, false);
				contactsList.appendChild(el);
				itemsAdded++;
			});
		}

		// If no results, show placeholder
		if (itemsAdded === 0) {
			const noRes = document.createElement('div');
			noRes.className = 'text-muted';
			noRes.textContent = 'No results found';
			contactsList.appendChild(noRes);
		}

		// Re-initialize click handlers
		initContactClicks();

		// Restore selection if still present
		if (selectedUsername) {
			const newSel = document.querySelector(`.contact-item[data-username="${selectedUsername}"][data-is-friend="${selectedIsFriend}"]`);
			if (newSel) newSel.classList.add('is-selected');
		}
	}

	function createContactElement(contact, isFriend) {
		const el = document.createElement('div');
		el.className = 'contact-item';
		el.setAttribute('data-type', isFriend ? 'friend' : 'non-friend');
		el.setAttribute('data-username', contact.username);
		el.setAttribute('data-is-friend', isFriend ? 'true' : 'false');

		const iconCircle = document.createElement('div');
		iconCircle.className = 'contact-icon-circle' + (isFriend ? '' : ' bg-subtle');
		iconCircle.innerHTML = '<i class="fa-solid fa-user"></i>';

		const textContainer = document.createElement('div');

		const nameDiv = document.createElement('div');
		nameDiv.textContent = contact.full_name;

		const previewDiv = document.createElement('div');
		previewDiv.className = 'text-muted contact-preview';
		if (isFriend) {
			let p = 'Click to chat';
			if (contact.last_message_text) {
				p = (contact.last_message_sender_id && Number(contact.last_message_sender_id) === Number(meId) ? 'You: ' : '') + (contact.last_message_text || '');
				p = truncateText(p, 40);
				if (contact.last_message_at) {
					const elapsed = formatElapsedTime(contact.last_message_at);
					if (elapsed) p += ' \u2022 ' + elapsed;
				}
			}
			previewDiv.textContent = p;
		} else {
			// Show bio for non-friends
			const bio = contact.bio || 'No bio';
			previewDiv.textContent = truncateText(bio, 40);
		}

		textContainer.appendChild(nameDiv);
		textContainer.appendChild(previewDiv);

		el.appendChild(iconCircle);
		el.appendChild(textContainer);

		return el;
	}

	if (searchBox) {
		searchBox.addEventListener('input', function (e) {
			const query = e.target.value;
			console.debug('search input detected:', query);
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(() => {
				performSearch(query);
			}, 300); // 300ms debounce
		});
		searchBox.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				performSearch(e.target.value);
			}
		});
	}

	// Refresh contacts with long polling
	let contactsLastActiveTime = '';
	let contactsPollController = null;

	function refreshContacts(force = false) {
		// Don't refresh if search is active
		if (isSearchActive && !force) return;

		// If forcing a refresh (e.g. after action), abort any pending poll to restart immediately
		if (force && contactsPollController) {
			contactsPollController.abort();
		}
		// If just looping and a request is already active, don't double up (unless forcing)
		// Actually, if we are calling recursively, we shouldn't worry, but safeguard:
		if (!force && contactsPollController && !contactsPollController.signal.aborted) {
			// This might happen if called externally while waiting. 
			// We can either abort existing or ignore new. 
			// If we ignore new, we rely on existing to finish.
			return; 
		}

		contactsPollController = new AbortController();
		const signal = contactsPollController.signal;

		// If force, we send no timestamp to bypass server wait
		const timeParam = force ? '' : contactsLastActiveTime;

		fetch('api/fetch_contacts.php?last_active_time=' + encodeURIComponent(timeParam), { signal })
			.then((r) => r.json())
			.then((data) => {
				if (data.error) throw new Error(data.error);
				
				// Update high-water mark
				if (data.last_active_time) {
					contactsLastActiveTime = data.last_active_time;
				}

				// update previews and reorder lists according to server-sorted order
				const contactsList = document.querySelector('.contacts-list');
				if (contactsList && Array.isArray(data.friends)) {
					// Remove any non-friend items left in the list (search-created) to restore server friend list
					contactsList.querySelectorAll('.contact-item[data-is-friend="false"]').forEach((el) => el.remove());
					// Remove any "No results found" placeholder inserted by search
					contactsList.querySelectorAll('.text-muted').forEach((el) => {
						if (el.textContent && el.textContent.trim() === 'No results found') el.remove();
					});
					// remember selected contact
					const selected = document.querySelector('.contact-item.is-selected');
					const selectedUsername = selected ? selected.getAttribute('data-username') : null;
					// Rebuild/reorder friends list according to data.friends order; create missing elements if necessary
					data.friends.forEach((friend) => {
						let el = document.querySelector(`.contact-item[data-username="${friend.username}"][data-is-friend="true"]`);
						if (!el) {
							el = createContactElement(friend, true);
						}
						// update preview
						const preview = el.querySelector('.contact-preview');
						if (preview) {
							let text = friend.last_message_text || 'Click to chat';
							if (friend.last_message_sender_id && Number(friend.last_message_sender_id) === Number(meId)) {
								text = 'You: ' + text;
							}
							text = truncateText(text, 40);
							if (friend.last_message_at) {
								const elapsed = formatElapsedTime(friend.last_message_at);
								if (elapsed) text += ' \u2022 ' + elapsed;
							}
							preview.textContent = text;
						}
						// append in order
						contactsList.appendChild(el);
					});
					// reapply selection
					if (selectedUsername) {
						const newSel = document.querySelector(`.contact-item[data-username="${selectedUsername}"][data-is-friend="true"]`);
						if (newSel) newSel.classList.add('is-selected');
					}
					// Ensure click handlers are attached to any newly created elements
					initContactClicks();
				}

				// groups
				const groupsList = document.querySelector('.groups-list');
				if (groupsList && Array.isArray(data.groups)) {
					const selectedGroup = document.querySelector('.group-item.is-selected');
					const selectedGroupId = selectedGroup ? selectedGroup.getAttribute('data-group-id') : null;
					data.groups.forEach((group) => {
						const el = document.querySelector(`.group-item[data-group-id="${group.id}"]`);
						if (!el) return;
						const preview = el.querySelector('.contact-preview');
						if (preview) {
							let text = group.last_message_text || 'Click to chat';
							if (group.last_message_sender_id && Number(group.last_message_sender_id) === Number(meId)) {
								text = 'You: ' + text;
							}
							text = truncateText(text, 40);
							if (group.last_message_at) {
								const elapsed = formatElapsedTime(group.last_message_at);
								if (elapsed) text += ' \u2022 ' + elapsed;
							}
							preview.textContent = text;
						}
						groupsList.appendChild(el);
					});
					if (selectedGroupId) {
						const newSel = document.querySelector(`.group-item[data-group-id="${selectedGroupId}"]`);
						if (newSel) newSel.classList.add('is-selected');
					}
				}

				// Schedule next poll
				contactsPollController = null;
				refreshContacts();
			})
			.catch((err) => {
				contactsPollController = null;
				if (err.name === 'AbortError') return;
				// Retry after delay on error
				setTimeout(refreshContacts, 5000);
			});
	}

	// start polling
	refreshContacts();

	// If URL already has u= or group=, load that conversation
	const params = new URLSearchParams(window.location.search);
	if (params.has('u')) {
		const username = params.get('u');
		const el = document.querySelector('.contact-item[data-username="' + username + '"]');
		if (el) selectItem(el);
		fetchConversation('tween/fetch_conversation.php?u=' + encodeURIComponent(username));
	} /* else if (params.has('group')) {
		const groupId = params.get('group');
		const el = document.querySelector('.group-item[data-group-id="' + groupId + '"]');
		if (el) selectItem(el);
		fetchConversation('tween/fetch_conversation.php?group=' + encodeURIComponent(groupId));
	} */

	// Create Group Modal
	const createGroupBtn = document.getElementById('create-group-btn');
	const createGroupModal = document.getElementById('create-group-modal');
	const cancelCreateBtn = document.getElementById('cancel-create');

	/* if (createGroupBtn && createGroupModal && cancelCreateBtn) {
		createGroupBtn.addEventListener('click', function () {
			createGroupModal.classList.add('show');
		});

		cancelCreateBtn.addEventListener('click', function () {
			createGroupModal.classList.remove('show');
		}); */
	/* } */

	// Confirmation Modal
	const confirmationModal = document.getElementById('confirmation-modal');
	const cancelConfirmationBtn = document.getElementById('cancel-confirmation');

	if (confirmationModal && cancelConfirmationBtn) {
		cancelConfirmationBtn.addEventListener('click', function () {
			confirmationModal.classList.remove('show');
		});
	}

	// Friends Modal
	const friendsBtn = document.getElementById('friends-btn');
	const friendsModal = document.getElementById('friends-modal');
	const closeFriendsModalBtn = document.getElementById('close-friends-modal');
	const friendsNotificationDot = document.getElementById('friends-notification-dot');

	function loadFriendsData() {
		fetch('api/fetch_friends_data.php')
			.then((r) => r.text().then((text) => ({ status: r.status, ok: r.ok, text })))
			.then((res) => {
				if (!res.ok) {
					console.error('fetch_friends_data HTTP error', res.status, res.text);
					// still try to parse body for JSON error info
					let parsed = null;
					try {
						parsed = JSON.parse(res.text);
					} catch (e) {
						console.error('fetch_friends_data returned non-JSON error body:', res.text);
						throw new Error('Server error');
					}
					return parsed;
				}
				try {
					const data = JSON.parse(res.text);
					console.log('Friends data loaded:', data);
					return data;
				} catch (e) {
					console.error('fetch_friends_data returned non-JSON:', res.text);
					throw e;
				}
			})
			.then((data) => {
				if (data.error) {
					console.error('API error:', data.error, data.detail || '');
					const friendsList = document.getElementById('current-friends-list');
					const blockedList = document.getElementById('blocked-users-list');
					const requestsList = document.getElementById('pending-requests-list');
					if (friendsList) friendsList.innerHTML = '<p class="text-muted">Error: ' + data.error + '</p>';
					if (blockedList) blockedList.innerHTML = '<p class="text-muted">Error: ' + data.error + '</p>';
					if (requestsList) requestsList.innerHTML = '<p class="text-muted">Error: ' + data.error + '</p>';
					if (friendsNotificationDot) friendsNotificationDot.style.display = 'none';
					return;
				}

				// Populate current friends
				const friendsList = document.getElementById('current-friends-list');
				friendsList.innerHTML = '';
				if (data.friends && data.friends.length > 0) {
					data.friends.forEach((friend) => {
						const item = document.createElement('div');
						item.className = 'friend-item';
						const fid = friend.id || friend.tween_id || friend.tweenId || '';
						item.setAttribute('data-username', friend.username);
						item.innerHTML = `
							<span class="friend-name">${friend.full_name}</span>
							<div class="friend-actions">
								<button class="btn-friend-action btn-unfriend" data-tween-id="${fid}" title="Unfriend">
									<i class="fa-solid fa-user-xmark"></i>
								</button>
								<button class="btn-friend-action btn-block-user" data-tween-id="${fid}" title="Block">
									<i class="fa-solid fa-ban"></i>
								</button>
							</div>
						`;
						friendsList.appendChild(item);
					});
				} else {
					friendsList.innerHTML = '<p class="text-muted">No friends yet.</p>';
				}

				// Populate blocked users
				const blockedList = document.getElementById('blocked-users-list');
				blockedList.innerHTML = '';
				if (data.blocked && data.blocked.length > 0) {
					data.blocked.forEach((user) => {
						const item = document.createElement('div');
						item.className = 'friend-item';
						const uid = user.id || user.tween_id || user.tweenId || '';
						item.innerHTML = `
							<span class="friend-name">${user.full_name}</span>
							<div class="friend-actions">
								<button class="btn-friend-action btn-unblock" data-tween-id="${uid}" title="Unblock">
									<i class="fa-solid fa-unlock"></i>
								</button>
							</div>
						`;
						blockedList.appendChild(item);
					});
				} else {
					blockedList.innerHTML = '<p class="text-muted">No blocked users.</p>';
				}

				// Populate pending requests
				const requestsList = document.getElementById('pending-requests-list');
				requestsList.innerHTML = '';
				if (data.pending_requests && data.pending_requests.length > 0) {
					data.pending_requests.forEach((req) => {
						const item = document.createElement('div');
						item.className = 'friend-item';
						item.innerHTML = `
							<span class="friend-name">${req.full_name}</span>
							<div class="friend-actions">
								<button class="btn-friend-action btn-accept" data-request-id="${req.request_id}" title="Accept">
									<i class="fa-solid fa-check"></i>
								</button>
								<button class="btn-friend-action btn-decline" data-request-id="${req.request_id}" title="Decline">
									<i class="fa-solid fa-xmark"></i>
								</button>
							</div>
						`;
						requestsList.appendChild(item);
					});
					// Show notification dot
					if (friendsNotificationDot) friendsNotificationDot.style.display = 'block';
				} else {
					requestsList.innerHTML = '<p class="text-muted">No pending requests.</p>';
					if (friendsNotificationDot) friendsNotificationDot.style.display = 'none';
				}

				// Attach event listeners to action buttons
				attachFriendActionListeners();
			})
			.catch((err) => console.error(err));
	}

	function attachFriendActionListeners() {
		// Unfriend buttons
		document.querySelectorAll('.btn-unfriend').forEach((btn) => {
			if (btn.dataset.unfriendListenerAdded) return;
			btn.dataset.unfriendListenerAdded = '1';
			btn.addEventListener('click', function () {
				const tweenId = this.getAttribute('data-tween-id');
				const form = new FormData();
				form.append('friend_id', tweenId);
				// Capture 'this' for use inside promise
				const btn = this;
				fetch('tween/unfriend.php', {
					method: 'POST',
					body: form,
				})
					.then((r) => r.json())
					.then((data) => {
						if (data.success) {
							loadFriendsData();
							refreshContacts(true);
							// Remove from left panel contacts list
							const item = btn.closest('.friend-item');
									const username = item.getAttribute('data-username');
									const contactItem = document.querySelector(`.contact-item[data-username="${username}"]`);
									if (contactItem) contactItem.remove();
									// If this was the currently selected friend, close the chat
									if (currentTarget === username && currentTargetType === 'friend') {
										document.querySelector('.empty-state').style.display = '';
										document.querySelector('.chat-container').style.display = 'none';
										document.querySelector('.message-input').style.display = 'none';
										document.querySelector('.middle-panel').classList.add('expanded');
										document.querySelector('.right-panel').classList.add('hidden');
										clearSelection();
										if (pollAbortController) {
											pollAbortController.abort();
											pollAbortController = null;
										}
										currentTarget = null;
										currentTargetType = null;
										lastMessageId = 0;
										lastActiveTime = 0;
									}
								} else {
									console.error(data.message || 'Failed to unfriend');
								}
							})
							.catch((err) => console.error(err));
						});
				});

		// Block buttons
		document.querySelectorAll('.btn-block-user').forEach((btn) => {
			if (btn.dataset.blockListenerAdded) return;
			btn.dataset.blockListenerAdded = '1';
			btn.addEventListener('click', function (e) {
				e.stopPropagation(); // Prevent delegated handler
				const tweenId = this.getAttribute('data-tween-id');
				const form = new FormData();
				form.append('tween_id', tweenId);
				fetch('tween/block_user.php', {
					method: 'POST',
					body: form,
				})
					.then((r) => r.json())
					.then((data) => {
						if (data.success) {
							loadFriendsData();
							refreshContacts(true);
							// Remove from left panel contacts list
							const item = this.closest('.friend-item');
							const username = item.getAttribute('data-username');
							const contactItem = document.querySelector(`.contact-item[data-username="${username}"]`);
							if (contactItem) contactItem.remove();
							// Close the chat view if the blocked user is currently selected
							if (currentTarget === username && currentTargetType === 'friend') {
								document.querySelector('.empty-state').style.display = '';
								document.querySelector('.chat-container').style.display = 'none';
								document.querySelector('.message-input').style.display = 'none';
								document.querySelector('.middle-panel').classList.add('expanded');
								document.querySelector('.right-panel').classList.add('hidden');
								clearSelection();
								if (pollAbortController) {
									pollAbortController.abort();
									pollAbortController = null;
								}
								currentTarget = null;
								currentTargetType = null;
								lastMessageId = 0;
								lastActiveTime = 0;
							}
						} else {
							console.error(data.error || 'Failed to block user');
						}
					})
					.catch((err) => console.error(err));
			});
		});

		// Unblock buttons
		document.querySelectorAll('.btn-unblock').forEach((btn) => {
			if (btn.dataset.unblockListenerAdded) return;
			btn.dataset.unblockListenerAdded = '1';
			btn.addEventListener('click', function () {
				const tweenId = this.getAttribute('data-tween-id');
				const form = new FormData();
				form.append('tween_id', tweenId);
				fetch('tween/unblock_user.php', {
					method: 'POST',
					body: form,
				})
					.then((r) => r.json())
					.then((data) => {
						if (data.success) {
							loadFriendsData();
							refreshContacts(true);
						} else {
							console.error(data.error || 'Failed to unblock user');
						}
					})
					.catch((err) => console.error(err));
			});
		});

		// Accept buttons
		document.querySelectorAll('.btn-accept').forEach((btn) => {
			btn.addEventListener('click', function () {
				const requestId = this.getAttribute('data-request-id');
				const form = new FormData();
				form.append('request_id', requestId);
				fetch('tween/accept_friend.php', {
					method: 'POST',
					body: form,
				})
					.then((r) => r.json())
					.then((data) => {
						if (data.success) {
							loadFriendsData();
							refreshContacts(true);
						} else {
							console.error(data.error || 'Failed to accept request');
						}
					})
					.catch((err) => console.error(err));
			});
		});

		// Decline buttons
		document.querySelectorAll('.btn-decline').forEach((btn) => {
			btn.addEventListener('click', function () {
				const requestId = this.getAttribute('data-request-id');
				const form = new FormData();
				form.append('request_id', requestId);
				fetch('tween/decline_friend.php', {
					method: 'POST',
					body: form,
				})
					.then((r) => r.json())
					.then((data) => {
						if (data.success) {
							loadFriendsData();
						} else {
							console.error(data.error || 'Failed to decline request');
						}
					})
					.catch((err) => console.error(err));
			});
		});
	}

	if (friendsBtn && friendsModal) {
		friendsBtn.addEventListener('click', function () {
			friendsModal.classList.add('show');
			loadFriendsData();
		});
	}

	if (closeFriendsModalBtn) {
		closeFriendsModalBtn.addEventListener('click', function () {
			friendsModal.classList.remove('show');
		});
	}

	// Close modal when clicking outside
	if (friendsModal) {
		friendsModal.addEventListener('click', function (e) {
			if (e.target === friendsModal) {
				friendsModal.classList.remove('show');
			}
		});
	}

	// Delegated handler for blocking from left panel (friend list) - uses existing confirmation modal
	document.body.addEventListener('click', function (e) {
		const btn = e.target.closest('.btn-block-user');
		if (!btn) return;
		// Use the existing showConfirmation modal
		const tweenId = btn.getAttribute('data-tween-id');
		showConfirmation(
			{
				title: 'Block User',
				icon: '<i class="fa-solid fa-ban"></i>',
				message: 'Are you sure you want to block this person?',
				confirmText: 'Block',
			},
			function () {
				const form = new FormData();
				form.append('tween_id', tweenId);
				fetch('tween/block_user.php', {
					method: 'POST',
					body: form,
				})
					.then((r) => r.json())
					.then((data) => {
						if (data.success) {
							loadFriendsData();
							refreshContacts(true);
						} else {
							console.error(data.error || 'Failed to block user');
						}
					})
					.catch((err) => console.error(err));
			}
		);
	});

	// Load notification dot status on page load
	fetch('api/fetch_friends_data.php')
		.then((r) => r.json())
		.then((data) => {
			if (data.pending_requests && data.pending_requests.length > 0 && friendsNotificationDot) {
				friendsNotificationDot.style.display = 'block';
			}
		})
		.catch(() => {});

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
			const newText = editMessageText.value.trim();
			if (!newText || !currentMessage) {
				editMessageModal.classList.remove('show');
				return;
			}
			const wrapper = currentMessage.closest('.message-wrapper');
			const messageId = wrapper?.getAttribute('data-message-id');
			if (!messageId) {
				editMessageModal.classList.remove('show');
				return;
			}

			const form = new FormData();
			form.append('message_id', messageId);
			form.append('text', newText);

			fetch('tween/edit_message.php', {
				method: 'POST',
				body: form,
			})
				.then((r) => r.json())
				.then((data) => {
					if (data && data.success) {
						currentMessage.querySelector('.text').textContent = newText;
						// Broadcast edit to other same-user tabs
						if (bc) {
							bc.postMessage({ type: 'edit', messageId, newText, target_type: currentTargetType, target: currentTarget, source: TAB_ID });
						}
						// Abort current poll (it will naturally restart on its own)
					} else {
						console.error(data?.error || 'Failed to edit message');
					}
				})
				.catch((err) => {
					console.error(err);
				});

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
			console.debug('confirmation confirmed for', title);
			try {
				onConfirm();
			} catch (e) {
				console.error('confirmation onConfirm error', e);
			}
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
				setCookie('theme', 'light');
			} else {
				document.body.setAttribute('data-theme', 'dark');
				themeIcon.className = 'fa-jelly-fill fa-regular fa-lightbulb';
				setCookie('theme', 'dark');
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

	// Custom Context Menu for Own Messages
	const contextMenuTemplate = document.getElementById('context-menu-template');
	const clone = contextMenuTemplate.cloneNode(true);
	const contextMenu = clone.querySelector('.context-menu');
	document.body.appendChild(contextMenu);

	let currentMessage = null;

	document.addEventListener('contextmenu', function (e) {
		if (e.target.closest('.message.own')) {
			e.preventDefault();
			currentMessage = e.target.closest('.message.own');
			const wrapper = currentMessage.closest('.message-wrapper') || currentMessage.closest('[data-message-id]');
			console.log('Context menu on message wrapper dataset=', wrapper?.dataset, 'getAttribute=', wrapper?.getAttribute?.('data-message-id'));
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

	// Context Menu Actions
	contextMenu.addEventListener('click', function (e) {
		const action = e.target.closest('.context-menu-item')?.dataset.action;
		if (action && currentMessage) {
			if (action === 'edit') {
				const currentText = currentMessage.querySelector('.text').textContent;
				editMessageText.value = currentText;
				editMessageModal.classList.add('show');
			} else if (action === 'delete') {
				let wrapper = currentMessage.closest('.message-wrapper');
				if (!wrapper) wrapper = currentMessage.closest('[data-message-id]');
				let messageId = wrapper?.getAttribute('data-message-id');
				if (!messageId && currentMessage.dataset && currentMessage.dataset.messageId) {
					messageId = currentMessage.dataset.messageId;
				}
				console.log('Delete clicked on message wrapper dataset:', wrapper?.dataset, 'messageId:', messageId);
				showConfirmation(
					{
						title: 'Delete Message',
						icon: '<i class="fa-jelly-fill fa-regular fa-trash"></i>',
						message: 'Are you sure you want to delete this message?',
						confirmText: 'Delete',
					},
					function () {
						if (!messageId || isNaN(parseInt(messageId, 10))) {
							console.error('Invalid message id for delete:', messageId);
							return;
						}
						const form = new FormData();
						form.append('message_id', messageId);
						console.log('Deleting message id', messageId);
						fetch('tween/delete_message.php', {
							method: 'POST',
							body: form,
						})
							.then((r) => r.json())
							.then((data) => {
								console.log('Delete response', data);
								if (data && data.success) {
									wrapper.remove();
									recalculateMessageUI();
									if (bc) {
										bc.postMessage({ type: 'delete', messageId, target_type: currentTargetType, target: currentTarget, source: TAB_ID });
									}
								} else {
									console.error(data?.error || 'Failed to delete message');
								}
							})
							.catch((err) => {
								console.error(err);
							});
					}
				);
			}
			contextMenu.classList.remove('show');
		}
	});

	// Unfriend and Block buttons are handled in renderContact()


	// Profile Button
	const profileBtn = document.querySelector('.profile-content');
	if (profileBtn) {
		profileBtn.addEventListener('click', function () {
			// Open profile modal later
		});
	}

	// Recalculate corner classes and sender visibility for all messages
	function recalculateMessageUI() {
		const messagesEl = document.querySelector('.messages');
		if (!messagesEl) return;
		const wrappers = messagesEl.querySelectorAll('.message-wrapper');
		if (wrappers.length === 0) return;

		wrappers.forEach((wrapper, i) => {
			const message = wrapper.querySelector('.message');
			if (!message) return;
			const isOwn = wrapper.classList.contains('own');
			const senderId = wrapper.getAttribute('data-sender-id');
			const prevWrapper = i > 0 ? wrappers[i - 1] : null;
			const nextWrapper = i < wrappers.length - 1 ? wrappers[i + 1] : null;
			const prevSame = prevWrapper && prevWrapper.getAttribute('data-sender-id') === senderId;
			const nextSame = nextWrapper && nextWrapper.getAttribute('data-sender-id') === senderId;

			// Update sender visibility
			const sender = wrapper.querySelector('.sender');
			if (!prevSame) {
				wrapper.classList.remove('no-sender');
				if (sender) {
					sender.style.display = '';
					if (!sender.textContent) {
						sender.textContent = isOwn ? 'Me' : wrapper.getAttribute('data-sender-name') || '';
					}
				}
			} else {
				wrapper.classList.add('no-sender');
				if (sender) sender.style.display = 'none';
			}

			// Update corner cuts
			message.classList.remove('cut-top-left', 'cut-top-right', 'cut-bottom-left', 'cut-bottom-right');
			if (isOwn) {
				if (prevSame) message.classList.add('cut-top-right');
				if (nextSame) message.classList.add('cut-bottom-right');
			} else {
				if (prevSame) message.classList.add('cut-top-left');
				if (nextSame) message.classList.add('cut-bottom-left');
			}
		});
		// Recompute date separators when the message list layout changes
		insertDateSeparators();
	}

	// Polling for new messages
	function longPoll(signal) {
		if (!currentTargetType || !currentTarget) {
			// nothing to poll for, retry in 2s
			if (signal && signal.aborted) return;
			setTimeout(() => longPoll(signal), 2000);
			return;
		}
		// Track request duration; if the server returns immediately (unexpected for long-poll), we add a tiny delay before the next attempt to avoid request storms.
		const startedAt = Date.now();
		const url = `tween/fetch_conversation.php?${currentTargetType === 'friend' ? 'u' : 'group'}=${encodeURIComponent(currentTarget)}&since=${lastMessageId}&last_active_time=${lastActiveTime}`;
		fetch(url, { cache: 'no-store', signal: signal })
			.then((r) => r.json())
			.then((data) => {
				if (data.last_active_time) {
					lastActiveTime = data.last_active_time;
				}
				if (data && data.messages && data.messages.length > 0) {
					data.messages.forEach((msg) => {
						// Avoid duplicates: if the message is already present, don't append it.
						if (!document.querySelector(`.message-wrapper[data-message-id="${msg.id}"]`)) {
							appendMessage(msg, data.me_id);
							// Update contact preview for this conversation
							updateContactPreview(currentTargetType, currentTarget, msg, data.me_id);
						}
						// Always update watermark id so polling progresses past this message.
						lastMessageId = Math.max(lastMessageId, msg.id);
					});
				}
				// Handle deleted messages
				if (data && data.deleted_message_ids && data.deleted_message_ids.length > 0) {
					data.deleted_message_ids.forEach((msgId) => {
						const wrapper = document.querySelector(`.message-wrapper[data-message-id="${msgId}"]`);
						if (wrapper) {
							wrapper.remove();
							recalculateMessageUI();
						}
					});
				}
				// Handle edited messages
				if (data && data.edited_messages && data.edited_messages.length > 0) {
					data.edited_messages.forEach((edit) => {
						const wrapper = document.querySelector(`.message-wrapper[data-message-id="${edit.id}"]`);
						if (wrapper) {
							const textEl = wrapper.querySelector('.text');
							if (textEl) textEl.textContent = edit.text_content;
						}
					});
				}
				// Immediately restart long-poll (server holds connection up to 25s)
				if (!signal || !signal.aborted) {
					const duration = Date.now() - startedAt;
					const delay = duration < 400 ? 500 : 0; // guard against rapid-fire responses
					setTimeout(() => longPoll(signal), delay);
				}
			})
			.catch((err) => {
				// If fetch aborted, do nothing; otherwise retry with backoff
				if (err && err.name === 'AbortError') return;
				setTimeout(() => {
					if (!signal || !signal.aborted) longPoll(signal);
				}, 2000);
			});
	}

	function startPolling() {
		if (pollAbortController) {
			pollAbortController.abort();
			pollAbortController = null;
		}
		pollAbortController = new AbortController();
		longPoll(pollAbortController.signal);
	}

	// Scroll helper
	function scrollToBottom() {
		const messagesEl = document.querySelector('.messages');
		if (!messagesEl) return;
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	// Append a single message to messages container
	function appendMessage(msg, meId) {
		const messagesEl = document.querySelector('.messages');
		if (!messagesEl) return;
		// Avoid duplicate appends when message is already rendered
		if (messagesEl.querySelector(`.message-wrapper[data-message-id="${msg.id}"]`)) return;
		const noMsg = messagesEl.querySelector('.no-messages');
		if (noMsg) noMsg.remove();
		const template = document.getElementById('message-template');
		const wrapper = template.firstElementChild.cloneNode(true);
		// Use numeric compare to avoid string/number mismatch
		const isOwn = Number(msg.sender_id) === Number(meId);
		// Check if last message is from same sender
		const lastWrapper = messagesEl.lastElementChild;
		const lastSenderId = lastWrapper ? lastWrapper.getAttribute('data-sender-id') : null;
		const prevSame = lastSenderId === msg.sender_id.toString();
		const showSender = !prevSame;
		wrapper.setAttribute('data-sender-id', msg.sender_id);
		wrapper.setAttribute('data-sent-at', msg.sent_at || new Date().toISOString());
		wrapper.setAttribute('data-message-id', msg.id);
		if (!isOwn && msg.sender_name) {
			wrapper.setAttribute('data-sender-name', msg.sender_name.split(' ')[0].toUpperCase());
		}
		wrapper.className = 'message-wrapper' + (isOwn ? ' own' : '') + (!showSender ? ' no-sender' : '');
		const sender = wrapper.querySelector('.sender');
		if (showSender) {
			sender.textContent = isOwn ? 'Me' : (msg.sender_name || '').split(' ')[0].toUpperCase();
		} else {
			sender.style.display = 'none';
		}
		const messageEl = wrapper.querySelector('.message');
		messageEl.className = 'message' + (isOwn ? ' own' : '');
		// top cut
		if (prevSame) {
			messageEl.classList.add(isOwn ? 'cut-top-right' : 'cut-top-left');
		} else {
			messageEl.classList.remove('cut-top-right', 'cut-top-left');
		}
		// Update previous wrapper's bottom cut if needed
		if (lastWrapper) {
			const prevMsgEl = lastWrapper.querySelector('.message');
			const prevIsOwn = lastWrapper.classList.contains('own');
			if (prevSame) {
				prevMsgEl.classList.add(prevIsOwn ? 'cut-bottom-right' : 'cut-bottom-left');
			} else {
				prevMsgEl.classList.remove('cut-bottom-right', 'cut-bottom-left');
			}
		}
		const textDiv = messageEl.querySelector('.text');
		textDiv.textContent = msg.text_content;
		const timestampDiv = messageEl.querySelector('.timestamp');
		timestampDiv.textContent = formatTime(msg.sent_at);
		messagesEl.appendChild(wrapper);
		// Ensure date separators are up-to-date after appending
		insertDateSeparators();
		scrollToBottom();
	}

	// Send message to server
	function sendMessage(text) {
		if (!currentTargetType || !currentTarget) return;
		if (!text || text.trim() === '') return;
		const form = new FormData();
		form.append('text', text);
		if (currentTargetType === 'friend') form.append('u', currentTarget);
		if (currentTargetType === 'group') form.append('group', currentTarget);

		if (sendBtn) sendBtn.disabled = true;
		fetch('tween/send_message.php', {
			method: 'POST',
			body: form,
		})
			.then((r) => r.json())
			.then((data) => {
				if (!data || data.error) {
					console.error(data?.error || 'Failed to send message');
					if (sendBtn) sendBtn.disabled = false;
					return;
				}
				console.debug && console.debug('sendMessage: server response', data);
				// Append newly created message
				appendMessage(data.message, data.me_id);
				// Update contact preview for the conversation
				updateContactPreview(currentTargetType, currentTarget, data.message, data.me_id);
				// Move contact to top (ensure current target is at top after send)
				if (currentTargetType && currentTarget) moveContactToTop(currentTargetType, currentTarget);
				// Update last message ID and lastActiveTime
				lastMessageId = data.message.id;
				lastActiveTime = data.message.sent_at || lastActiveTime;
				// Broadcast the sent message to other same-user tabs
				if (bc) {
					console.debug && console.debug('sendMessage: bc.postMessage payload', { type: 'send', message: data.message, me_id: data.me_id, target_type: data.target_type, target: data.target });
					bc.postMessage({ type: 'send', message: data.message, me_id: data.me_id, target_type: data.target_type, target: data.target, source: TAB_ID });
				}
				// Abort current poll (it will naturally restart on its own)
				// clear textarea
				const ta = document.querySelector('.message-input textarea');
				if (ta) {
					ta.value = '';
					// keep focus
					ta.focus();
				}
				if (sendBtn) sendBtn.disabled = false;
			})
			.catch((err) => {
				console.error(err);
				if (sendBtn) sendBtn.disabled = false;
			});
	}

	// Wire send button and enter key
	const sendBtn = document.querySelector('.message-input button');
	const messageTa = document.querySelector('.message-input textarea');
	if (sendBtn && messageTa) {
		sendBtn.addEventListener('click', function () {
			const text = messageTa.value.trim();
			if (!text) return;
			sendMessage(text);
		});
		// Enter to send, Shift+Enter for newline
		messageTa.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				const text = messageTa.value.trim();
				if (!text) return;
				sendMessage(text);
			}
		});
	}

	// Add toggle button for right panel
	toggleBtn = document.querySelector('.toggle-right-panel-btn');
	if (toggleBtn) {
		const rightPanel = document.querySelector('.right-panel');
		// initialize icon based on current panel state
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
