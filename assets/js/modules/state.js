export const state = {
	currentTarget: null,
	currentTargetType: null,
	pollAbortController: null,
	lastMessageId: 0,
	lastActiveTime: 0,
	meId: null,
	currentDailyLimit: 100,
	currentTodayCount: 0,
	contactsPollController: null,
	isSearchActive: false,
	contactsLastActiveTime: '',
	TAB_ID: 'tab-' + Math.random().toString(36).substr(2, 9),
	bc: window.BroadcastChannel ? new BroadcastChannel('barta-messages') : null
};
