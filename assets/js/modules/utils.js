export function setCookie(name, value, days = 365) {
	const d = new Date();
	d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
	document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/';
}

export function getCookie(name) {
	const nameEQ = name + '=';
	const ca = document.cookie.split(';');
	for (let i = 0; i < ca.length; i++) {
		let c = ca[i];
		while (c.charAt(0) == ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

export function formatTime(datetime) {
	const d = new Date(datetime);
	let hours = d.getHours();
	const minutes = d.getMinutes().toString().padStart(2, '0');
	const ampm = hours >= 12 ? 'PM' : 'AM';
	hours = hours % 12 || 12;
	return hours + ':' + minutes + ' ' + ampm;
}

export function formatDateLabel(datetime) {
	const d = new Date(datetime);
	const today = new Date();
	const yesterday = new Date();
	yesterday.setDate(today.getDate() - 1);
	if (d.toDateString() === today.toDateString()) return 'Today';
	if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
	return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function truncateText(text, maxLen = 40) {
	if (!text) return '';
	if (text.length <= maxLen) return text;
	return text.substring(0, maxLen - 3) + '...';
}

export function formatElapsedTime(datetime) {
	if (!datetime) return '';
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
