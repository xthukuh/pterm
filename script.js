(() => {
	if (!(Terminal && 'function' === typeof Terminal)) throw new Error('Terminal function is not defined!');
	window.terminal = Terminal('#terminal', {
		light: 'col-light',
		handler: window.HANDLER,
		logoutEl: '#logout',
		whoami: window.WHOAMI,
		whoamiEl: '#whoami',
		cwd: window.CWD,
		cwdEl: '#cwd',
	});
})();