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
	};
	const options = Object.assign({}, {
		prompt: '> ',
		handler: '',
		chdir: '',
		chdirEl: null,
		closeEl: null,
	}, opts);

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
		if (light) html = `<span class="text-light">${html}</span>`;
		buffer.push(html);
		output();
	};

	//prompt
	const prompt = () => print(options.prompt);

	//chdir set
	const chdir = dir => {
		if ('string' === typeof dir && dir !== options.chdir) options.chdir = dir.trim();
		if (isElement(options.chdirEl)) options.chdirEl.innerHTML = options.chdir;
		return options.chdir;
	};

	//parse json
	const parseJSON = (str, _default=null) => {
		try {
			return JSON.parse(str);
		}
		catch {
			return _default;
		}
	};

	//exec cmd
	const exec = async cmd => {
		busy();
		print('...', 1);
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

		//fetch request
		const res = await fetch(options.handler, {
			method: 'post',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
			},
			body: requestBody({cmd, chdir: options.chdir}),
		})
		.then(async res => {
			
			//response
			if (!res.ok) throw new Error(await res.text());
			
			//stop loading
			stopLoading();

			//read response
			let chdir = res.headers.get('x-chdir');
			let text = '';
			let reader = res.body.getReader();
			let decoder = new TextDecoder();
			return readChunk();

			//read chunk
			function readChunk(){
				return reader.read().then(appendChunks);
			}

			//append chunks
			function appendChunks(result){
				let chunk = decoder.decode(result.value || new Uint8Array, {stream: !result.done});

				//print output
				let output = chunk.replace(/^[\n\r]/g, '').trimEnd();
				print(!text.length && output.length ? `\n${output}` : output, 1);
				
				//text buffer
				text += chunk;
				if (result.done) return {text, chdir};
				else return readChunk();
			}
		})
		.catch(err => ({error: `Fetch Error: ${err}`}));
		
		//stop loading
		stopLoading();

		//handle response
		if ('object' === typeof res && res){
			if ('error' in res){
				print(`\n${res.error}\n`, 1);
				console.error(res.error);
			}
			if ('chdir' in res) chdir(res.chdir);
		}

		//done
		prompt();
		busy(0);
	};

	//get input
	const input = () => {
		let text = edit.innerText.slice(state.length).replace(/^[\r\n]*|[\r\n]*$/g, '');
		text = decodeURIComponent(encodeURIComponent(text).replaceAll('%C2%A0', '%20'));
		return text;
	};

	//run input cmd
	const run = () => {
		let cmd = input();

		//set cmd history
		let index = commands.indexOf(cmd);
		if (index >= 0) commands.splice(index, 1);
		commands.push(cmd);
		localStorage.setItem('commands', JSON.stringify(commands));
		state.history = 0;

		//clear
		if (['cls', 'clear', 'clsx'].includes(cmd)){
			if (cmd === 'clsx'){
				commands.splice(0);
				localStorage.removeItem('commands');
			}
			buffer.splice(0);
			prompt();
			return;
		}

		//cd
		if (cmd.match(/^cd\s*.*?$/)){
			let path = cmd.substr(2).trim();
			chdir(path);
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
		if (state.busy) return e.preventDefault();
		const key = e.key.toLowerCase();

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
		if (!isInputMode()){
			e.preventDefault();
			edit.innerHTML += e.key;
			caretEnd();
		}
	};

	//initialize
	let cachedCommands = parseJSON(localStorage.getItem('commands'));
	if (Array.isArray(cachedCommands)) commands.push(...cachedCommands);
	if ('string' === typeof options.chdirEl) options.chdirEl = document.querySelector(options.chdirEl);
	if ('string' === typeof options.closeEl) options.closeEl = document.querySelector(options.closeEl);
	if (isElement(options.closeEl)) options.closeEl.addEventListener('click', () => {
		localStorage.removeItem('commands');
	}, false);
	edit.addEventListener('keydown', keydownHandler, false);
	edit.addEventListener('keypress', keypressHandler, false);
	setTimeout(() => prompt());
	chdir();

	//terminal object
	const terminal = {
		edit, caret, commands, buffer, state, options,
		caretEnd, busy, remove, output, history,
		print, prompt, chdir, exec, input, run, isInputMode
	};

	//result
	return terminal;
};