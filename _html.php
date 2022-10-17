<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="google" content="notranslate">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo _option('title'); ?></title>
	<script type="text/javascript">
		window.CONFIG = <?php echo json_encode(['resume' => _option('resume')]); ?>;
	</script>
	<style>
		.container {
			min-width: 70%;
		}
		@media only screen and (max-width: 768px){
			.container {
				min-width: 90%;
			}
		}
		.input {
			margin: 0;
			color: teal;
			outline: none;
			flex-grow: 1;
			border: 1px solid #ddd;
			padding: 4px 8px;
			font-size: 14px;
			font-weight: bold;
			font-family: 'monospace', consolas;
		}
		.label {
			width: 50px;
			color: #555;
			font-size: 14px;
			font-weight: bold;
			cursor: pointer;
		}
		.btn {
			padding: 4px 8px;
			min-width: 100px;
		}
	</style>
</head>
<body style="position:fixed;top:0;left:0;width:100%;height:100%;margin:0;font-family:'monospace',consolas;background-color:#eee;">
	<div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;">
		<div class="container" style="flex-grow:1;display:flex;flex-direction:column;background:#fff;border:1px solid #ddd;margin:20px auto;">
			
			<!-- head -->
			<div style="padding:10px;text-align:center;">
				<h2 style="margin:0;font-size:16px;color:#888"><?php echo _option('title'); ?></h2>
			</div>
			
			<!-- output -->
			<div style="position:relative;flex-grow:1;">
				<div id="output_wrapper" style="display:flex;flex-direction:column;overflow:scroll;position:absolute;top:0;left:0;width:100%;height:100%;">
					<div id="output" style="flex-grow:1;background:#000;color:#9f9;padding:10px;white-space:pre-wrap;font-size:12px;"></div>
				</div>
			</div>
			
			<!-- form -->
			<form id="cmd_form" action="" method="post" style="font-size:12px;display:flex;flex-direction:column;">
				
				<!-- inputs -->	
				<div style="padding:10px;display:flex;flex-direction:column;gap:4px">
					<div style="display:flex;flex-direction:row;align-items:center;">
						<label for="input_cmd" class="label" title="Type command here">CMD:</label>
						<input id="input_cmd" class="input" style="color:blue;" type="text" value="" />
					</div>
					<div style="display:flex;flex-direction:row;align-items:center;">
						<label for="input_cwd" class="label" title="Working directory">CWD:</label>
						<input id="input_cwd" class="input" type="text" readonly="readonly" value="<?php echo _option('cwd'); ?>" />
					</div>
				</div>

				<!-- buttons -->
				<div style="padding:10px;gap:10px;display:flex;flex-direction:row;flex-wrap:wrap;border-top:1px solid #ddd;justify-content:start;">
					<button id="btn_requirements" class="btn" type="button" title="Test Requirements">Test Requirements</button>
					<button id="btn_install_composer" class="btn" type="button" title="Install Composer">Install Composer</button>
					<button id="btn_clear" class="btn" type="button" title="Clear Output">Clear</button>
					<button id="btn_cancel" class="btn" type="button" title="Cancel Running">Cancel</button>
					<div style="flex-grow:1;display:inline-flex;flex-direction:row;justify-content:end;">
						<button id="btn_run" class="btn" type="submit" title="Run Command">Run</button>
					</div>
				</div>
			</form>
		</div>
	</div>
	<script type="text/javascript">
		let IS_CANCELLED, IS_DISABLED, ENDPOINT = window.location.href, RESUME = window.CONFIG?.resume;
		
		//output wrapper - scroll
		const output_wrapper = document.getElementById('output_wrapper');
		let output_scroll_ignore = 0;
		output_wrapper.addEventListener('scroll', () => {
			if (output_scroll_ignore === 1) return output_scroll_ignore = 0;
			output_scroll_ignore = 2;
			const el = output_wrapper, offset = 20;
			if (el.scrollHeight < el.offsetHeight || (el.scrollHeight - el.scrollTop - el.offsetHeight) < offset) output_scroll_ignore = 0;
		}, {passive: true});
		const outputScrollBottom = () => {
			if (output_scroll_ignore > 1 || output_wrapper.scrollHeight < output_wrapper.scrollHeight) return;
			output_scroll_ignore = 1;
			output_wrapper.scrollTop = output_wrapper.scrollHeight;
		};

		//output - text
		const output = document.getElementById('output');
		const output_prompt = '>_';
		const outputClear = text => output.innerText = output_prompt;
		const outputText = text => {
			output.innerText = output.innerText.replace(new RegExp(`${output_prompt}\s*$`, 'g'), '') + String(text);
			outputScrollBottom();
		};
		const outputPrompt = () => {
			output.innerText = output.innerText.trim() + '\n' + output_prompt;
			outputScrollBottom();
		};
		const outputCmd = cmd => outputText((output.innerText.indexOf('\n') > -1 ? '\n' : '') + '> ' + cmd + '\n');

		//controls
		const cmd_form = document.getElementById('cmd_form');
		const input_cmd = document.getElementById('input_cmd');
		const btn_requirements = document.getElementById('btn_requirements');
		const btn_install_composer = document.getElementById('btn_install_composer');
		const btn_clear = document.getElementById('btn_clear');
		const btn_run = document.getElementById('btn_run');
		const btn_cancel = document.getElementById('btn_cancel');

		//disabled
		const setDisabled = (disabled=true, cancel) => {
			IS_DISABLED = disabled = !!disabled;
			cancel = cancel === undefined ? !disabled : !!cancel;
			[btn_requirements, btn_install_composer, btn_clear, btn_run].forEach(element => {
				if (disabled) element.setAttribute('disabled', 'disabled');
				else element.removeAttribute('disabled');
			});
			if (cancel) btn_cancel.setAttribute('disabled', 'disabled');
			else btn_cancel.removeAttribute('disabled');
			if (disabled) input_cmd.setAttribute('readonly', 'readonly');
			else input_cmd.removeAttribute('readonly');
		};
		const isDisabled = () => {
			if (IS_DISABLED){
				console.warn('Controls are disabled.');
				return true;
			}
			return false;
		};

		//fetch request
		const fetchRequest = async (url, options, onRead) => {
			let RESPONSE, RESPONSE_DATA;
			onRead = 'function' === typeof onRead ? onRead : undefined;
			return window.fetch(url, options).then(async res => {
				RESPONSE = res.clone();
				const decoder = new TextDecoder();
				const reader = res.body.getReader();
				const data = {text: '', contentType: res.headers.get('Content-Type')};
				return await readChunk();
				async function readChunk(){
					return reader.read().then(appendChunks);
				}
				async function appendChunks(result){
					const done = Boolean(result.done);
					const chunk = decoder.decode(result.value || new Uint8Array(), {stream: !done});
					if (onRead) onRead({buffer: chunk, response: RESPONSE});
					data.text += chunk;
					if (!done) return readChunk();
					return data;
				}
			})
			.then(data => {
				const {contentType, text} = data;
				data.json = undefined;
				RESPONSE_DATA = data;
				if (String(contentType).toLowerCase().indexOf('application/json') > -1){
					try {
						data.json = JSON.parse(text);
					}
					catch (e){
						console.warn(`Error parsing json (${contentType}) text.`, e);
					}
				}
				if (!RESPONSE.ok || !(RESPONSE.status >= 200 && RESPONSE.status < 300)){
					let err = `Request error ${RESPONSE.status}: ${RESPONSE.statusText}`;
					console.warn(err, {RESPONSE, data})
					throw new Error(err);
				}
				return {error: undefined, data, response: RESPONSE};
			})
			.catch(error => Promise.reject({error, data: RESPONSE_DATA, response: RESPONSE}));
		};

		//fetch exec
		let ABORT_CONTROLLER, FETCH_EXEC = 0;
		const fetchAbort = () => ABORT_CONTROLLER?.abort?.();
		const fetchExec = async (cmd, is_cancel=false) => {
			
			//fetch busy
			let signal;
			if (!is_cancel){
				if (FETCH_EXEC) return console.warn('Fetch exec is busy.');
				FETCH_EXEC = 1;
				setDisabled(true);
				outputCmd(cmd);
				ABORT_CONTROLLER = new AbortController();
				signal = ABORT_CONTROLLER.signal;
			}

			//fetch request
			const formData = new FormData();
			formData.append('cmd', cmd);
			const options = {
				signal,
				method: 'POST',
				body: formData,
			};

			//result - fetch request promise
			return fetchRequest(ENDPOINT, options, ({buffer, response}) => {
				if (response.ok) outputText(buffer);
			})
			.catch(err => {
				if (err?.error instanceof Error){
					if (err.error.name !== 'AbortError') console.warn(err.error);
					return;
				}
				return Promise.reject(err);
			})
			.finally(() => {
				if (is_cancel) return;
				FETCH_EXEC = 0;
				if (IS_CANCELLED) return IS_CANCELLED();
				setDisabled(false);
				outputPrompt();
				input_cmd.focus();
			});
		}
		const fetchCancel = async () => {
			if (IS_CANCELLED) return console.warn('Fetch cancel is busy.');

			//cancel busy
			const callback = () => {
				setDisabled(false);
				outputPrompt();
				input_cmd.focus();
				IS_CANCELLED = undefined;
			};
			IS_CANCELLED = () => callback();
			setDisabled(true, true);
			outputCmd('cancel');

			//cancel fetch
			if (FETCH_EXEC){
				
				//aborting
				outputText('Aborting...\n');
				fetchAbort();
				
				//result - fetch request promise
				return fetchExec('cancel', true)
				.finally(() => {
					if (FETCH_EXEC) return;
					callback();
				});
			}

			//cancel done
			return callback();
		};

		//run test
		const runTest = () => {
			setDisabled(true);
			outputCmd('test');
			let x = 0, max = 100, interval = setInterval(() => {
				x ++;
				outputText(`[${x}/${max}] - test line.\n`);
				if (x === max || IS_CANCELLED){
					clearInterval(interval);
					if (IS_CANCELLED) return IS_CANCELLED();
					setDisabled(false);
					outputText(output_prompt);
				}
			}, 200);
		};

		//run actions
		const ACTIONS = {
			'cancel': () => fetchCancel(),
			'clear': () => {
				if (isDisabled()) return;
				fetchExec('clear')
				.then(() => {
					outputClear();
					input_cmd.value = '';
					input_cmd.focus();
				});
			},
			'exec': (cmd) => {
				if (isDisabled()) return;
				fetchExec(cmd);
			},
			'cmd': () => {
				if (isDisabled()) return;
				const cmd = input_cmd.value.trim();
				input_cmd.value = '';
				if (!cmd.length) return;
				let tmp = cmd.toLowerCase();
				if (ACTIONS.hasOwnProperty(tmp)) return ACTIONS[tmp]();
				return ACTIONS.exec(cmd);
			},
			'test': () => ACTIONS.exec('test'),
			'run-test': () => {
				if (isDisabled()) return;
				runTest();
			},
			'requirements': () => ACTIONS.exec('requirements'),
			'install-composer': () => ACTIONS.exec('install-composer'),
			'resume': () => ACTIONS.exec('resume'),
		};
		
		//action events
		cmd_form.addEventListener('submit', e => {
			e.preventDefault();
			ACTIONS.cmd();
		});
		btn_requirements.addEventListener('click', e => {
			e.preventDefault();
			ACTIONS.requirements();
		});
		btn_install_composer.addEventListener('click', e => {
			e.preventDefault();
			ACTIONS['install-composer']();
		});
		btn_clear.addEventListener('click', e => {
			e.preventDefault();
			ACTIONS.clear();
		});
		btn_cancel.addEventListener('click', e => {
			e.preventDefault();
			ACTIONS.cancel();
		});

		//initialize
		outputClear();
		input_cmd.placeholder = output_prompt;
		input_cmd.focus();
		setDisabled(false);

		//resume
		if (RESUME){
			console.debug('RESUME', RESUME);
			ACTIONS.resume();
		}
	</script>
</body>
</html>