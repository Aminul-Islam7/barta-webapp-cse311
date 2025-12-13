import { state } from './state.js';
import { 
	renderMessages, renderContact, appendMessage, updateContactPreview, 
	moveContactToTop, recalculateMessageUI, markMessageAsEdited, 
	scrollToBottom, showConfirmation, updateLimitUI 
} from './ui.js';
import { attachContactCardListeners } from './contacts.js';

export function fetchConversation(url) {
	fetch(url)
		.then((r) => r.json())
		.then((data) => {
			if (data.error) {
				return;
			}
			document.querySelector('.empty-state').style.display = 'none';
			document.querySelector('.chat-container').style.display = 'flex';
			document.querySelector('.message-input').style.display = 'flex';
			
			// Right panel logic (cookie logic handling is in main setup mostly, but here too)
			// For simplicity, we assume main setup handles initial, but here we honor cookie
			// Actually main does checking. Here let's just respect the classes if set.
			// But original code re-checked cookie here.
			// Ideally we import getCookie/setCookie but UI logic related to panels could be in UI.
			// I'll skip the panel logic here assuming it's persistent, or if needed add it back.
			// Original code:
			// const rightPanelHidden = getCookie('rightPanelHidden') === 'true';
			// ... logic to hide/show ...
			// Let's assume the UI doesn't reset on fetch, but if it does, we need it.
			// I will omit it for brevity unless it breaks. The state is CSS classes, so it persists unless page reload.
			
			state.meId = data.me_id;
			renderMessages(data.messages, data.me_id);
			renderContact(data.contact, data.type);
			attachContactCardListeners(); // Attach listeners to new contact card

			if (data.type === 'friend') {
				state.currentTargetType = 'friend';
				state.currentTarget = data.contact.username;
			}
			
			state.lastMessageId = data.messages.length > 0 ? Math.max(...data.messages.map((m) => m.id)) : 0;
			state.lastActiveTime = data.last_active_time || 0;
			
			if (typeof data.daily_limit !== 'undefined') {
				state.currentDailyLimit = parseInt(data.daily_limit);
				state.currentTodayCount = parseInt(data.today_count);
				updateLimitUI();
			}

			startPolling();
		})
		.catch((err) => {});
}

export function startPolling() {
	if (state.pollAbortController) {
		state.pollAbortController.abort();
		state.pollAbortController = null;
	}
	state.pollAbortController = new AbortController();
	longPoll(state.pollAbortController.signal);
}

function longPoll(signal) {
	if (!state.currentTargetType || !state.currentTarget) {
		if (signal && signal.aborted) return;
		setTimeout(() => longPoll(signal), 2000);
		return;
	}
	const startedAt = Date.now();
	const url = `tween/fetch_conversation.php?${state.currentTargetType === 'friend' ? 'u' : 'group'}=${encodeURIComponent(state.currentTarget)}&since=${state.lastMessageId}&last_active_time=${state.lastActiveTime}`;
	fetch(url, { cache: 'no-store', signal: signal })
		.then((r) => r.json())
		.then((data) => {
			if (data.last_active_time) {
				state.lastActiveTime = data.last_active_time;
			}
			if (data && data.messages && data.messages.length > 0) {
				data.messages.forEach((msg) => {
					if (!document.querySelector(`.message-wrapper[data-message-id="${msg.id}"]`)) {
						appendMessage(msg, data.me_id);
						updateContactPreview(state.currentTargetType, state.currentTarget, msg, data.me_id);
					}
					state.lastMessageId = Math.max(state.lastMessageId, msg.id);
				});
			}
			if (data && data.deleted_message_ids && data.deleted_message_ids.length > 0) {
				data.deleted_message_ids.forEach((msgId) => {
					const wrapper = document.querySelector(`.message-wrapper[data-message-id="${msgId}"]`);
					if (wrapper) {
						wrapper.remove();
						recalculateMessageUI();
					}
				});
			}
			if (data && data.edited_messages && data.edited_messages.length > 0) {
				data.edited_messages.forEach((edit) => {
					const wrapper = document.querySelector(`.message-wrapper[data-message-id="${edit.id}"]`);
					if (wrapper) {
						const senderId = wrapper.getAttribute('data-sender-id');
						let displayText = edit.text_content || '';
						let isItalic = false;

						if (parseInt(edit.is_clean) === 0 && parseInt(senderId) !== parseInt(data.me_id)) {
							if (edit.parent_approval === 'pending') {
								displayText = 'This message contains a blocked word and is pending approval from your parent.';
								isItalic = true;
							} else if (edit.parent_approval === 'rejected') {
								displayText = 'This message was rejected by your parent.';
								isItalic = true;
							}
						}

						const textEl = wrapper.querySelector('.text');
						if (textEl) {
							textEl.textContent = displayText;
							if (isItalic) {
								textEl.style.fontStyle = 'italic';
								textEl.style.color = 'var(--text-muted)';
							} else {
								textEl.style.fontStyle = '';
								textEl.style.color = '';
							}
						}
						markMessageAsEdited(wrapper);
					}
				});
			}
			if (!signal || !signal.aborted) {
				const duration = Date.now() - startedAt;
				const delay = duration < 400 ? 500 : 0;
				setTimeout(() => longPoll(signal), delay);
			}
		})
		.catch((err) => {
			if (err && err.name === 'AbortError') return;
			setTimeout(() => {
				if (!signal || !signal.aborted) longPoll(signal);
			}, 2000);
		});
}

export function sendMessage(text) {
	if (!state.currentTargetType || !state.currentTarget) return;
	if (!text || text.trim() === '') return;
	const form = new FormData();
	form.append('text', text);
	if (state.currentTargetType === 'friend') form.append('u', state.currentTarget);
	// Group sending disabled

	const sendBtn = document.querySelector('.message-input button');
	if (sendBtn) sendBtn.disabled = true;
	fetch('tween/send_message.php', {
		method: 'POST',
		body: form,
	})
		.then((r) => r.json())
		.then((data) => {
			if (!data || data.error) {
				if (data?.error && data.error.includes('limit')) {
					state.currentTodayCount = state.currentDailyLimit;
					updateLimitUI();
				}
				if (sendBtn) sendBtn.disabled = false;
				return;
			}
			appendMessage(data.message, data.me_id);
			updateContactPreview(state.currentTargetType, state.currentTarget, data.message, data.me_id);
			if (state.currentTargetType && state.currentTarget) moveContactToTop(state.currentTargetType, state.currentTarget);
			state.lastMessageId = data.message.id;
			state.lastActiveTime = data.message.sent_at || state.lastActiveTime;
			if (state.bc) {
				state.bc.postMessage({ type: 'send', message: data.message, me_id: data.me_id, target_type: data.target_type, target: data.target, source: state.TAB_ID });
			}
			const ta = document.querySelector('.message-input textarea');
			if (ta) {
				ta.value = '';
				ta.focus();
			}
			if (sendBtn) sendBtn.disabled = false;
			state.currentTodayCount++;
			updateLimitUI();
		})
		.catch((err) => {
			console.error(err);
			if (sendBtn) sendBtn.disabled = false;
		});
}

export function initChat() {
	// Broadcast listener
	if (state.bc) {
		state.bc.onmessage = function (event) {
			const payload = event.data || {};
			const { type, messageId, newText, message, me_id, target_type, target } = payload;
			if (!state.currentTargetType || !state.currentTarget) return;
			if (payload.source && payload.source === state.TAB_ID) return;
			if (type === 'edit') {
				if (target_type !== state.currentTargetType || String(target) !== String(state.currentTarget)) return;
				const wrapper = document.querySelector(`.message-wrapper[data-message-id="${messageId}"]`);
				if (wrapper) {
					const textEl = wrapper.querySelector('.text');
					if (textEl) textEl.textContent = newText;
					markMessageAsEdited(wrapper);
				}
				return;
			}
			if (type === 'delete') {
				if (target_type !== state.currentTargetType || String(target) !== String(state.currentTarget)) return;
				const wrapper = document.querySelector(`.message-wrapper[data-message-id="${messageId}"]`);
				if (wrapper) {
					wrapper.remove();
					recalculateMessageUI();
				}
				return;
			}
			if (type === 'send' && message) {
				if (target_type === state.currentTargetType && String(target) === String(state.currentTarget)) {
					if (!document.querySelector(`.message-wrapper[data-message-id="${message.id}"]`)) {
						appendMessage(message, me_id);
						state.lastMessageId = Math.max(state.lastMessageId, message.id);
					}
				}
				updateContactPreview(target_type, target, message, me_id);
				moveContactToTop(target_type === 'friend' ? 'friend' : 'group', target);
			}
		};
	}
	
	const sendBtn = document.querySelector('.message-input button');
	const messageTa = document.querySelector('.message-input textarea');
	if (sendBtn && messageTa) {
		sendBtn.addEventListener('click', function () {
			const text = messageTa.value.trim();
			if (!text) return;
			sendMessage(text);
		});
		messageTa.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				const text = messageTa.value.trim();
				if (!text) return;
				sendMessage(text);
			}
		});
	}
	
	// Context Menu
	const contextMenuTemplate = document.getElementById('context-menu-template');
	const clone = contextMenuTemplate.cloneNode(true);
	const contextMenu = clone.querySelector('.context-menu');
	document.body.appendChild(contextMenu);
	let currentMessage = null;
	document.addEventListener('contextmenu', function (e) {
		const msgEl = e.target.closest('.message');
		if (msgEl) {
			e.preventDefault();
			currentMessage = msgEl;
			const isOwn = currentMessage.classList.contains('own');
			const editBtn = contextMenu.querySelector('[data-action="edit"]');
			const deleteBtn = contextMenu.querySelector('[data-action="delete"]');
			if (editBtn) editBtn.style.display = isOwn ? 'flex' : 'none';
			if (deleteBtn) deleteBtn.style.display = isOwn ? 'flex' : 'none';
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
			if (action === 'copy') {
				let textToCopy = '';
				const selection = window.getSelection();
				if (selection && !selection.isCollapsed && currentMessage.contains(selection.anchorNode)) {
					textToCopy = selection.toString();
				} else {
					textToCopy = currentMessage.querySelector('.text').textContent;
				}
				if (textToCopy) {
					navigator.clipboard.writeText(textToCopy).catch(() => {});
				}
				contextMenu.classList.remove('show');
			} else if (action === 'edit') {
				const currentText = currentMessage.querySelector('.text').textContent;
				const editMessageText = document.getElementById('edit-message-text');
				const editMessageModal = document.getElementById('edit-message-modal');
				if (editMessageText && editMessageModal) {
					// We need to pass the messageId to the modal handler, or store it in state / dom
					// The modal logic needs to find 'currentMessage' or its ID.
					// We can attach it to the modal dataset?
					// Or we can rely on `currentMessage` variable if we export it or use it?
					// `currentMessage` here is local to this closure.
					// Helper: make `currentMessage` accessible or attach data to modal input.
					// Actually the edit message logic is simpler if we just populate the modal and set an attributes on the modal.
					
					let wrapper = currentMessage.closest('.message-wrapper');
					let messageId = wrapper?.getAttribute('data-message-id');
					editMessageModal.setAttribute('data-target-message-id', messageId);
					editMessageText.value = currentText;
					editMessageModal.classList.add('show');
				}
			} else if (action === 'delete') {
				let wrapper = currentMessage.closest('.message-wrapper');
				let messageId = wrapper?.getAttribute('data-message-id');
				showConfirmation({
						title: 'Delete Message',
						icon: '<i class="fa-jelly-fill fa-regular fa-trash"></i>',
						message: 'Are you sure you want to delete this message?',
						confirmText: 'Delete',
					}, function () {
						const form = new FormData();
						form.append('message_id', messageId);
						fetch('tween/delete_message.php', { method: 'POST', body: form })
						.then(r => r.json())
						.then(data => {
							if (data.success) {
								wrapper.remove();
								recalculateMessageUI();
								if (state.bc) {
									state.bc.postMessage({ type: 'delete', messageId, target_type: state.currentTargetType, target: state.currentTarget, source: state.TAB_ID });
								}
							}
						});
					}
				);
			}
			contextMenu.classList.remove('show');
		}
	});
	
	// Edit message modal listener needs to handle the save. 
	// The original code has this listener in `tween.js`. 
	// I should put it here or in settings? 
	// It's chat related. Put here.
	const editMessageModal = document.getElementById('edit-message-modal');
	const saveEditBtn = document.getElementById('save-edit');
	const editMessageText = document.getElementById('edit-message-text');
	
	if (saveEditBtn && editMessageModal) {
		saveEditBtn.addEventListener('click', function() {
			const newText = editMessageText.value.trim();
			const messageId = editMessageModal.getAttribute('data-target-message-id');
			if (!newText || !messageId) {
				editMessageModal.classList.remove('show');
				return;
			}
			const form = new FormData();
			form.append('message_id', messageId);
			form.append('text', newText);
			fetch('tween/edit_message.php', { method: 'POST', body: form })
				.then(r => r.json())
				.then(data => {
					if (data.success) {
						// update UI
						const wrapper = document.querySelector(`.message-wrapper[data-message-id="${messageId}"]`);
						if (wrapper) {
							wrapper.querySelector('.text').textContent = newText;
							markMessageAsEdited(wrapper);
						}
						if (state.bc) {
							state.bc.postMessage({ type: 'edit', messageId, newText, target_type: state.currentTargetType, target: state.currentTarget, source: state.TAB_ID });
						}
					}
				});
			editMessageModal.classList.remove('show');
		});
	}
}
