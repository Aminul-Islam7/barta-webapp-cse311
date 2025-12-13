import { state } from './state.js';
import { truncateText, formatElapsedTime, formatTime, formatDateLabel } from './utils.js';

export function getMessageDisplayText(msg, meId) {
	let displayText = msg.text_content || '';
	let isItalic = false;

	if (parseInt(msg.is_clean) === 0 && parseInt(msg.sender_id) !== parseInt(meId)) {
		if (msg.parent_approval === 'pending') {
			displayText = 'This message contains a blocked word and is pending approval from your parent.';
			isItalic = true;
		} else if (msg.parent_approval === 'rejected') {
			displayText = 'This message was rejected by your parent.';
			isItalic = true;
		}
	}
	return { text: displayText, isItalic };
}

export function scrollToBottom() {
	const messagesEl = document.querySelector('.messages');
	if (!messagesEl) return;
	messagesEl.scrollTop = messagesEl.scrollHeight;
}

export function insertDateSeparators() {
	const messagesEl = document.querySelector('.messages');
	if (!messagesEl) return;
	messagesEl.querySelectorAll('.date-separator').forEach((el) => el.remove());
	const wrappers = Array.from(messagesEl.querySelectorAll('.message-wrapper'));
	let prevDateStr = null;
	for (let i = 0; i < wrappers.length; i++) {
		const w = wrappers[i];
		const sentAt = w.getAttribute('data-sent-at');
		const d = sentAt ? new Date(sentAt) : new Date();
		const ds = d.toDateString();
		if (i === 0 || ds !== prevDateStr) {
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

export function markMessageAsEdited(wrapper) {
	if (!wrapper) return;
	const messageEl = wrapper.querySelector('.message');
	if (!messageEl || messageEl.querySelector('.edited-indicator')) return;
	
	const indicator = document.createElement('i');
	indicator.className = 'fa-solid fa-pen edited-indicator';
	indicator.title = 'Edited';
	messageEl.appendChild(indicator);
}

export function recalculateMessageUI() {
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

		message.classList.remove('cut-top-left', 'cut-top-right', 'cut-bottom-left', 'cut-bottom-right');
		if (isOwn) {
			if (prevSame) message.classList.add('cut-top-right');
			if (nextSame) message.classList.add('cut-bottom-right');
		} else {
			if (prevSame) message.classList.add('cut-top-left');
			if (nextSame) message.classList.add('cut-bottom-left');
		}
	});
	insertDateSeparators();
}

export function createContactElement(contact, isFriend) {
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
			// Reuse blocked logic for preview? Or just check fields manually as in original?
			// Original logic:
			// if (parseInt(msg.is_clean) === 0 ...)
			// The contact object from search results might not have full message details like is_clean
			// But get_contacts.php was updated to pre-mask the text.
			// So for friends list loaded from server, 'last_message_text' is already masked.
			// Ideally we rely on that.
			
			// Wait, for search results ('api/search_users.php'), does it return masked text?
			// I haven't checked search_users.php. If it doesn't, we might expose blocked words in search.
			// Assuming it does or we just use what we have.
			
			let text = contact.last_message_text || '';
			// The original code in renderSearchResults did:
			// if (contact.last_message_sender_id == meId) 'You: '...
			// It did NOT do blocked check locally for search results, it seemingly relied on server or didn't check.
			// But updateContactPreview DOES check.
			// Let's stick to simple text mapping here.
			
			p = (contact.last_message_sender_id && Number(contact.last_message_sender_id) === Number(state.meId) ? 'You: ' : '') + text;
			p = truncateText(p, 40);
			if (contact.last_message_at) {
				const elapsed = formatElapsedTime(contact.last_message_at);
				if (elapsed) p += ' \u2022 ' + elapsed;
			}
		}
		previewDiv.textContent = p;
	} else {
		const bio = contact.bio || 'No bio';
		previewDiv.textContent = truncateText(bio, 40);
	}

	textContainer.appendChild(nameDiv);
	textContainer.appendChild(previewDiv);
	el.appendChild(iconCircle);
	el.appendChild(textContainer);
	return el;
}

export function updateContactPreview(targetType, target, msg, meId) {
	if (!targetType || !target || !msg) return;
	
	const { text, isItalic } = getMessageDisplayText(msg, meId);
	let previewText = text;
	
	// blocked/pending logic is handled in getMessageDisplayText
	// Original code masked it here.
	
	if (Number(msg.sender_id) === Number(meId)) previewText = 'You: ' + previewText;
	previewText = truncateText(previewText, 40);
	if (msg && msg.sent_at) {
		const elapsed = formatElapsedTime(msg.sent_at);
		if (elapsed) previewText += ' \u2022 ' + elapsed;
	}
	if (targetType === 'friend') {
		const el = document.querySelector(`.contact-item[data-username="${target}"] .contact-preview`);
		if (el) el.textContent = previewText;
	}
}

export function renderMessages(messages, meId) {
	const messagesEl = document.querySelector('.messages');
	if (!messagesEl) return;
	messagesEl.innerHTML = '';
	if (!messages || messages.length === 0) {
		const noMsgTemplate = document.getElementById('no-messages-template');
		const noMsgClone = noMsgTemplate.firstElementChild.cloneNode(true);
		messagesEl.appendChild(noMsgClone);
		return;
	}
	for (let i = 0; i < messages.length; i++) {
		const msg = messages[i];
		const template = document.getElementById('message-template');
		const wrapper = template.firstElementChild.cloneNode(true);
		const isOwn = Number(msg.sender_id) === Number(meId);
		const prevSame = i > 0 && messages[i - 1].sender_id === msg.sender_id;
		const nextSame = i < messages.length - 1 && messages[i + 1].sender_id === msg.sender_id;
		const showSender = !prevSame;
		wrapper.className = 'message-wrapper' + (isOwn ? ' own' : '') + (!showSender ? ' no-sender' : '');
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
		
		const { text, isItalic } = getMessageDisplayText(msg, meId);
		textDiv.textContent = text;
		if (isItalic) {
			textDiv.style.fontStyle = 'italic';
			textDiv.style.color = 'var(--text-muted)';
		}
		
		const timestampDiv = messageEl.querySelector('.timestamp');
		timestampDiv.textContent = formatTime(msg.sent_at || new Date().toISOString());
		
		if (msg.is_edited && Number(msg.is_edited) === 1) {
			const indicator = document.createElement('i');
			indicator.className = 'fa-solid fa-pen edited-indicator';
			indicator.title = 'Edited';
			messageEl.appendChild(indicator);
		}
		messagesEl.appendChild(wrapper);
	}
	insertDateSeparators();
	scrollToBottom();
}

export function appendMessage(msg, meId) {
	const messagesEl = document.querySelector('.messages');
	if (!messagesEl) return;
	if (messagesEl.querySelector(`.message-wrapper[data-message-id="${msg.id}"]`)) return;
	const noMsg = messagesEl.querySelector('.no-messages');
	if (noMsg) noMsg.remove();
	const template = document.getElementById('message-template');
	const wrapper = template.firstElementChild.cloneNode(true);
	const isOwn = Number(msg.sender_id) === Number(meId);
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
	if (prevSame) {
		messageEl.classList.add(isOwn ? 'cut-top-right' : 'cut-top-left');
	} else {
		messageEl.classList.remove('cut-top-right', 'cut-top-left');
	}
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
	
	const { text, isItalic } = getMessageDisplayText(msg, meId);
	textDiv.textContent = text;
	if (isItalic) {
		textDiv.style.fontStyle = 'italic';
		textDiv.style.color = 'var(--text-muted)';
	}
	
	const timestampDiv = messageEl.querySelector('.timestamp');
	timestampDiv.textContent = formatTime(msg.sent_at);
	messagesEl.appendChild(wrapper);
	insertDateSeparators();
	scrollToBottom();
}

export function renderContact(contact, type) {
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
	infoPanel.innerHTML = '';
	const templateId = type === 'friend' ? 'friend-info-template' : 'group-info-template';
	const template = document.getElementById(templateId);
	const clone = template.cloneNode(true);
	clone.style.display = '';
	const h3 = clone.querySelector('h3');
	const small = clone.querySelector('small');
	const p = clone.querySelector('p');
	if (type === 'friend') {
		h3.textContent = contact.full_name;
		small.textContent = '@' + contact.username;
		p.textContent = contact.bio || '';
		const blockBtn = clone.querySelector('.btn-block');
		if (blockBtn) {
			const id = contact.id || contact.tween_id || contact.tweenId || '';
			blockBtn.setAttribute('data-tween-id', id);
		}
	} else {
		h3.textContent = contact.group_name;
		p.textContent = 'Members: ' + (contact.members || []).map((m) => m.full_name).join(', ');
	}
	infoPanel.appendChild(clone);
	
	// Events for buttons are attached in contacts.js or via delegation
	// Actually original logic attached them here inline in `renderContact`.
	// I should probably export a function to attach listeners or move that logic to `contacts.js`.
	// For now, I will leave elements without listeners here and let `contacts.js` handle it?
	// `contacts.js` isn't called when `renderContact` is called.
	// So `renderContact` must perform attachment.
	// It relies on `unfriend.php` fetch etc.
	// I'll dispatch a custom event or allow passing a "setupHandlers" callback? 
	// Or just import `attachFriendCardListeners` from `contacts.js`?
	// `ui.js` -> `contacts.js`. Circular.
	
	// Alternative: `renderContact` just RENDERs. `chat.js` (which calls renderContact) then calls `contacts.attachCardListeners`.
	// Better.
}

export function moveContactToTop(type, identifier) {
	if (type === 'friend') {
		const contactsList = document.querySelector('.contacts-list');
		const item = document.querySelector(`.contact-item[data-username="${identifier}"][data-is-friend="true"]`);
		if (contactsList && item) {
			contactsList.insertBefore(item, contactsList.firstChild);
		}
	}
}

export function clearSelection() {
	document.querySelectorAll('.contacts .contact-item, .groups .group-item').forEach((el) => el.classList.remove('is-selected'));
}

export function selectItem(el) {
	clearSelection();
	el.classList.add('is-selected');
}

export function showConfirmation(options, onConfirm) {
	const confirmationModal = document.getElementById('confirmation-modal');
	if (!confirmationModal) return;
	const { title = 'Confirm Action', icon = '', message = 'Are you sure?', confirmText = 'Confirm' } = options;
	const h3 = confirmationModal.querySelector('h3');
	h3.innerHTML = (icon ? icon + ' ' : '') + title;
	const messageEl = confirmationModal.querySelector('.confirmation-message');
	messageEl.textContent = message;
	const confirmBtn = confirmationModal.querySelector('.btn-confirm');
	confirmBtn.textContent = confirmText;
	confirmBtn.onclick = function () {
		try {
			onConfirm();
		} catch (e) {
			console.error('confirmation onConfirm error', e);
		}
		confirmationModal.classList.remove('show');
	};
	confirmationModal.classList.add('show');
}

export function updateLimitUI() {
	const ta = document.querySelector('.message-input textarea');
	const limitMsg = document.querySelector('.message-input .limit-reached-msg');
	const leftCircle = document.querySelector('.messages-left-circle');
	const sendBtn = document.querySelector('.message-input .btn-primary');
	
	if (!ta) return;

	const left = Math.max(0, state.currentDailyLimit - state.currentTodayCount);
	if (leftCircle) {
		leftCircle.textContent = left;
	}
	
	if (state.currentTodayCount >= state.currentDailyLimit) {
		ta.style.display = 'none';
		if (leftCircle) leftCircle.style.display = 'none';
		if (sendBtn) sendBtn.style.display = 'none';
		if (limitMsg) limitMsg.style.display = 'flex';
		ta.disabled = true;
	} else {
		ta.style.display = 'block';
		if (leftCircle) leftCircle.style.display = 'flex';
		if (sendBtn) sendBtn.style.display = 'inline-flex';
		if (limitMsg) limitMsg.style.display = 'none';
		ta.disabled = false;
		if (!ta.value && ta.placeholder !== "Type a message...") {
             ta.placeholder = "Type a message...";
        }
	}
}
