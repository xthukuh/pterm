(() => {
	if (!(Terminal && 'function' === typeof Terminal)) throw new Error('Terminal function is not defined!');
	window.terminal = Terminal('#terminal', {
		handler: window.HANDLER,
		chdir: window.CHDIR,
		chdirEl: '#chdir',
		closeEl: '#close',
	});
})();