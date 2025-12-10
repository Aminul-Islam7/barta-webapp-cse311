document.addEventListener('DOMContentLoaded', function () {
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
		} else {
			h3.textContent = contact.group_name;
			p.textContent = 'Members: ' + (contact.members || []).map((m) => m.full_name).join(', ');
		}
		infoPanel.appendChild(clone);
	}

	function formatTime(datetime) {
		const d = new Date(datetime);
		let hours = d.getHours();
		const minutes = d.getMinutes().toString().padStart(2, '0');
		const ampm = hours >= 12 ? 'PM' : 'AM';
		hours = hours % 12 || 12;
		return hours + ':' + minutes + ' ' + ampm;
	}

	// helper: truncate text safely
	function truncateText(text, maxLen = 40) {
		if (!text) return '';
		if (text.length <= maxLen) return text;
		return text.substring(0, maxLen - 3) + '...';
	}

	function updateContactPreview(targetType, target, msg, meId) {
		if (!targetType || !target || !msg) return;
		let previewText = msg.text_content || '';
		if (Number(msg.sender_id) === Number(meId)) previewText = 'You: ' + previewText;
		previewText = truncateText(previewText, 40);
		if (targetType === 'friend') {
			const el = document.querySelector(`.contact-item[data-username="${target}"] .contact-preview`);
			if (el) el.textContent = previewText;
		} else if (targetType === 'group') {
			const el = document.querySelector(`.group-item[data-group-id="${target}"] .contact-preview`);
			if (el) el.textContent = previewText;
		}
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
				document.querySelector('.middle-panel').classList.remove('expanded');
				document.querySelector('.right-panel').classList.remove('hidden');
				// render messages and contact info
				meId = data.me_id;
				renderMessages(data.messages, data.me_id);
				renderContact(data.contact, data.type); // set current target for sending
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
				if (target_type !== currentTargetType || String(target) !== String(currentTarget)) return;
				// avoid duplicates
				if (!document.querySelector(`.message-wrapper[data-message-id="${message.id}"]`)) {
					appendMessage(message, me_id);
					lastMessageId = Math.max(lastMessageId, message.id);
				}
				// Update contact preview
				updateContactPreview(target_type, target, message, me_id);
				return;
			}
		};
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
			el.addEventListener('click', function (e) {
				const username = el.getAttribute('data-username');
				if (!username) return;
				// Stop previous polling
				if (pollAbortController) {
					pollAbortController.abort();
					pollAbortController = null;
				}
				currentTargetType = 'friend';
				currentTarget = username;
				const url = 'tween/fetch_conversation.php?u=' + encodeURIComponent(username);
				selectItem(el);
				history.pushState({ u: username }, '', '?u=' + encodeURIComponent(username));
				fetchConversation(url);
			});
		});

		const groupItems = document.querySelectorAll('.groups .group-item');
		groupItems.forEach((el) => {
			el.addEventListener('click', function (e) {
				const groupId = el.getAttribute('data-group-id');
				if (!groupId) return;
				// Stop previous polling
				if (pollAbortController) {
					pollAbortController.abort();
					pollAbortController = null;
				}
				currentTargetType = 'group';
				currentTarget = groupId;
				const url = 'tween/fetch_conversation.php?group=' + encodeURIComponent(groupId);
				selectItem(el);
				history.pushState({ group: groupId }, '', '?group=' + encodeURIComponent(groupId));
				fetchConversation(url);
			});
		});
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

	// If URL already has u= or group=, load that conversation
	const params = new URLSearchParams(window.location.search);
	if (params.has('u')) {
		const username = params.get('u');
		const el = document.querySelector('.contact-item[data-username="' + username + '"]');
		if (el) selectItem(el);
		fetchConversation('tween/fetch_conversation.php?u=' + encodeURIComponent(username));
	} else if (params.has('group')) {
		const groupId = params.get('group');
		const el = document.querySelector('.group-item[data-group-id="' + groupId + '"]');
		if (el) selectItem(el);
		fetchConversation('tween/fetch_conversation.php?group=' + encodeURIComponent(groupId));
	}

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
				// Append newly created message
				appendMessage(data.message, data.me_id);
				// Update contact preview for the conversation
				updateContactPreview(currentTargetType, currentTarget, data.message, data.me_id);
				// Update last message ID and lastActiveTime
				lastMessageId = data.message.id;
				lastActiveTime = data.message.sent_at || lastActiveTime;
				// Broadcast the sent message to other same-user tabs
				if (bc) {
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
	const toggleBtn = document.querySelector('.toggle-right-panel-btn');
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
			} else {
				rightPanel.classList.add('hidden');
				middlePanel.classList.add('expanded');
				toggleBtn.innerHTML = '<i class="fa-duotone fa-solid fa-chevrons-left"></i>';
			}
		});
	}
});
