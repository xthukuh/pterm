'use strict';

//edit terminal
const Terminal = (el, opts={}) => {
	
	//is element
	const isElement = el => {
		try {
			return el instanceof HTMLElement;
		}
		catch (e){
			return 'object' === typeof el && el
			&& el.hasOwnProperty('nodeType')
			&& el.hasOwnProperty('style')
			&& el.hasOwnProperty('ownerDocument')
			&& 'object' === typeof el.style && el.style
			&& 'object' === typeof el.ownerDocument && el.ownerDocument;
		}
	};

	//set edit
	const edit = 'string' === typeof el ? document.querySelector(el) : el;
	if (!isElement(edit)) throw new Error('Terminal element is invalid!', el);

	//set edit caret
	if (!(Caret && 'function' === typeof Caret)) throw new Error('Caret function is not defined!');
	const caret = Caret(edit);
	
	//defaults
	const commands = [];
	const buffer = [];
	const state = {
		busy: false,
		length: 0,
		history: 0,
		controller: null,
	};
	const options = Object.assign({}, {
		prompt: '> ',
		light: 'text-light',
		handler: '',
		logoutEl: null,
		whoami: '',
		whoamiEl: null,
		cwd: '',
		cwdEl: null,
	}, opts);

	//set header option
	const setHeaderOption = (key, str) => {
		if ('string' === typeof str && str !== options[key]) options[key] = str.trim();
		if (isElement(options[`${key}El`])) options[`${key}El`].innerHTML = options[key];
		return options[key];
	};

	//to html
	const toHtml = text => text.replace(/\r\n/g, '\n')
	.replace(/\<br\s*?\/?\>/ig, '\n')
	.replace(/\>/g, '&gt;')
	.replace(/\</g, '&lt;')
	.replace(/[ ]/g, '&nbsp;')
	.replace(/\n/g, '<br>');
	
	//caret end
	const caretEnd = (scroll=true) => {
		if (scroll) edit.scroll(0, edit.scrollHeight);
		setTimeout(() => caret.end());
	};

	//busy
	const busy = (toggle=true) => state.busy = !!toggle;

	//remove buffer (from last)
	const remove = count => {
		let len = buffer.length;
		count = !isNaN(count = Number(count)) && count > 0 ? count : 1;
		count = count > len ? len : count;
		if (len) buffer.splice(len - count);
	};

	//output
	const output = (caret_end=true) => {
		let setBusy = !state.busy;
		if (setBusy) busy();
		edit.innerHTML = buffer.join('<br>');
		state.length = edit.innerText.length;
		if (caret_end) caretEnd();
		if (setBusy) busy(0);
	};

	//history cmd
	const history = down => {
		let setBusy = !state.busy;
		if (setBusy) busy();
		let len = commands.length;
		let pos = state.history + (down ? -1 : 1);
		state.history = !len || pos < 0 ? 0 : (pos > len ? len : pos);
		let cmd = !state.history ? '' : commands.slice(state.history * -1)[0];
		//let cmd = !len ? '' : Array.from(commands).reverse()[state.history];
		output(0);
		edit.innerHTML = edit.innerHTML + toHtml(cmd);
		caretEnd();
		if (setBusy) busy(0);
	};

	//print
	const print = (text, light) => {
		let html = toHtml(text);
		if (light) html = `<span class="${options.light}">${html}</span>`;
		buffer.push(html);
		output();
	};

	//prompt
	const prompt = () => print(options.prompt);

	//parse json
	const parseJSON = (str, _default=null) => {
		try {
			return JSON.parse(str);
		}
		catch {
			return _default;
		}
	};

	//abort exec
	const abort = () => state.controller ? state.controller.abort() : null;

	//exec cmd
	const exec = async cmd => {
		busy();
		print('\n...', 1);
		let loading = true;

		//stop loading
		const stopLoading = () => {
			if (!loading) return;
			loading = false;
			remove();
		};

		//request body
		const requestBody = obj => {
			let params = [];
			for (let key in obj){
				if (!obj.hasOwnProperty(key)) continue;
				params.push(`${key}=${encodeURIComponent(obj[key])}`);
			}
			return params.join('&');
		};

		//controller
		const controller = new AbortController();
		const { signal } = controller;
		state.controller = controller;

		//fetch request
		const res = await fetch(options.handler, {
			signal,
			method: 'post',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
			},
			keepalive: true,
			body: requestBody({cmd, cwd: options.cwd}),
		})
		.then(async res => {
			
			//response
			if (!res.ok) throw new Error(await res.text());
			
			//stop loading
			stopLoading();

			//set header options
			setHeaderOption('cwd', res.headers.get('x-cwd'));
			setHeaderOption('whoami', res.headers.get('x-whoami'));

			//output response
			let text = '';
			let reader = res.body.getReader();
			let decoder = new TextDecoder();
			//let prevOutput = null;
			return readChunk();

			//read chunk
			function readChunk(){
				return reader.read().then(appendChunks);
			}

			//append chunks
			function appendChunks(result){
				let chunk = decoder.decode(result.value || new Uint8Array, {stream: !result.done});
				
				//print output
				let output = chunk.trim();
				print(!text.length && output.length ? `\n${output}` : output, 1);

				//text buffer
				text += chunk;
				if (result.done) return text;
				else return readChunk();
			}
		})
		.catch(error => {
			
			//abort
			if ('object' === typeof error && error && error.name === 'AbortError'){
				print('');
				return;
			}
			
			//error result
			return {error};
		});
		
		//stop loading
		stopLoading();
		state.controller = null;

		//set response
		if ('object' === typeof res && res && 'error' in res){
			print(`${res.error}\n`, 1);
			console.error(res.error);
		}

		//done
		prompt();
		busy(0);
	};

	//logout
	const logout = () => {
		busy();
		print('\nlogout...', 1);
		localStorage.removeItem('commands');
		setTimeout(() => {
			location.href = '?logout';
		}, 500);
	};

	//get input
	const input = () => {
		let text = edit.innerText.slice(state.length).replace(/^[\r\n]*|[\r\n]*$/g, '');
		text = decodeURIComponent(encodeURIComponent(text).replaceAll('%C2%A0', '%20'));
		return text.trim();
	};

	//run input cmd
	const run = () => {
		let cmd = input();
		if (!cmd) return;
		
		//set cmd
		remove();
		print(`${options.prompt}${cmd}`);
		let index = commands.indexOf(cmd);
		if (index >= 0) commands.splice(index, 1);
		commands.push(cmd);
		localStorage.setItem('commands', JSON.stringify(commands));
		state.history = 0;

		//exit
		if (cmd === 'exit'){
			logout();
			return;
		}

		//clear
		if (['cls', 'clear', 'clsx'].includes(cmd)){
			if (cmd === 'clsx'){
				commands.splice(0);
				localStorage.removeItem('commands');
				console.debug('everything cleared.');
			}
			buffer.splice(0);
			prompt();
			return;
		}

		//cd
		if (cmd.match(/^cd\s*.*?$/)){
			let path = cmd.substr(2).trim();
			setHeaderOption('cwd', path);
			cmd = 'cd';
		}

		//exec
		if (cmd.length) exec(cmd);
	};

	//is input mode (check caret position)
	const isInputMode = (offset=0) => {
		let pos = caret.pos();
		let isLastLine = pos.lines.start === pos.lines.count || pos.after.trim() === '';
		let isAfterPrompt = pos.lines.startOffset > (options.prompt.length + offset);
		return isLastLine && isAfterPrompt;
	};

	//event handler - keydown
	const keydownHandler = e => {
		const key = e.key.toLowerCase();

		//escape
		if (key === 'escape'){
			e.preventDefault();
			abort();
			return;
		}
		
		//busy
		if (state.busy) return e.preventDefault();
		
		//enter
		if (key === 'enter'){
			e.preventDefault();
			run();
			return;
		}

		//home
		if (key === 'home'){
			e.preventDefault();
			caret.setPos(state.length);
			return;
		}

		//arrowleft
		if (key === 'arrowleft' && !isInputMode()) return e.preventDefault();
		
		//backspace
		if (key === 'backspace' && !isInputMode()) return e.preventDefault();

		//arrowup
		if (key === 'arrowup'){
			e.preventDefault();
			history();
			return;
		}

		//arrowdown
		if (key === 'arrowdown'){
			e.preventDefault();
			history(1);
			return;
		}
	};

	//event handler - keypress
	const keypressHandler = e => {
		if (!isInputMode(-1)){
			e.preventDefault();
			edit.innerHTML += e.key;
			caretEnd();
		}
	};

	//init edit
	edit.addEventListener('keydown', keydownHandler, false);
	edit.addEventListener('keypress', keypressHandler, false);
	setTimeout(() => prompt());

	//init commands
	let cachedCommands = parseJSON(localStorage.getItem('commands'));
	if (Array.isArray(cachedCommands)) commands.push(...cachedCommands);
	
	//init header option - cwd
	if ('string' === typeof options.cwdEl) options.cwdEl = document.querySelector(options.cwdEl);
	setHeaderOption('cwd');

	//init header option - whoami
	if ('string' === typeof options.whoamiEl) options.whoamiEl = document.querySelector(options.whoamiEl);
	setHeaderOption('whoami');

	//init logout
	if ('string' === typeof options.logoutEl) options.logoutEl = document.querySelector(options.logoutEl);
	if (isElement(options.logoutEl)) options.logoutEl.addEventListener('click', logout, false);

	//result
	return {
		edit, caret, commands, buffer, state, options,
		caretEnd, busy, remove, output, history,
		print, prompt, setHeaderOption, exec, logout,
		input, run, isInputMode
	};
};