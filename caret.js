'use strict';

//edit caret
const Caret = edit => {
	
	//set input
	const input = edit;
	const isInput = 'object' === typeof input && input && 'nodeName' in input && ['input', 'textarea'].includes(input.nodeName.toLowerCase());
	
	//check if input is editable
	const isEditable = () => {
		if (isInput || 'object' === typeof input && input && input.isContentEditable) return true;
		return console.error(`Caret edit content is not editable!`, input);
	};

	//input focus
	const inputFocus = () => document.activeElement !== input ? input.focus() : null;

	//get window selection
	const getWinSelection = () => {
		const selection = window.getSelection();
		if (!selection.rangeCount) throw new Error('Failed to get window selection range count.');
		return selection;
	};

	//get caret position << {start, end, text, content, before, after, lines}
	const pos = () => {
		if (!isEditable()) return;
		inputFocus();
		
		//input caret position
		if (isInput){
			
			//caret position
			const start = input.selectionStart;
            const end = input.selectionEnd;

			//caret content
			const text = input.value;
			const content = text.substr(start, end - start);
			const before = text.substr(0, start);
			const after = text.substr(end);
			const beforeLines = before.split('\n');
			const contentLines = text.substr(0, end).split('\n');

			//caret lines
			const lines = {};
			lines.count = text.split('\n').length;
			lines.start = beforeLines.length || 1;
			lines.startOffset = beforeLines.slice(-1)[0].length;
			lines.end = contentLines.length || 1;
			lines.endOffset = contentLines.slice(-1)[0].length;

			//result position
			return {start, end, text, content, before, after, lines};
		}

		//contentEditable caret position - get selection range
		const sel = getWinSelection();
		const range = sel.getRangeAt(0);
		const rng = range.cloneRange();

		//caret content
		range.selectNodeContents(input);
		range.setEnd(rng.endContainer, rng.endOffset);
		const pre = window.getSelection().toString();
		range.setStart(rng.startContainer, rng.startOffset);
		const content = window.getSelection().toString();
		const text = input.innerText;
		const before = pre.slice(0, pre.length - content.length);
		const after = text.substr(pre.length);

		//caret offset
		const start = pre.length - content.length;
		const end = start + content.length;

		//caret lines
		const lines = {};
		lines.count = text.split('\n').length;
		lines.start = before.split('\n').length || 1;
		lines.startContainer = rng.startContainer;
		lines.startOffset = rng.startOffset;
		lines.end = pre.split('\n').length || 1;
		lines.endOffset = rng.endOffset;
		lines.endContainer = rng.endContainer;

		//result position
		return {start, end, text, content, before, after, lines};
	};

	//new line
	const newLine = (node) => {
		let display = String(window.getComputedStyle(node, null).display).toLowerCase();
		let styles = ['-webkit-box','box','list-item','grid','flow-root','block','flex'];
		return styles.includes(display);
	};

	//contenteditable get position node
	const getPosNode = (pos, parent, buffer) => {
		parent = parent || input;
		buffer = buffer || {len: 0};

		//parse children
		let count = parent.childNodes.length;
		for (let i = 0; i < count; i ++){

			//buffer done break
			if (buffer.done) break;

			//set node
			const node = parent.childNodes[i];
			const nodeName = node.nodeName.toLowerCase();

			//filter TEXT_NODE/br
			if (node.nodeType === Node.TEXT_NODE || nodeName === 'br'){
				
				//set length
				const len = nodeName === 'br' ? 1 : node.length;
				const buffer_len = buffer.len + len;

				//check if position node found
				if (buffer.len <= pos && pos < buffer_len || pos === buffer_len && i === (count - 1)){

					//set offset/done
					buffer.offset = nodeName === 'br' ? 0 : pos - buffer.len;
					buffer.done = true;
					buffer.node = node;
					break;
				}

				//increment buffer length
				buffer.len += len;
			}
			else if (node.nodeType === Node.ELEMENT_NODE){

				//new line nodes - increment buffer length
				if (newLine(node)) buffer.len += 1;
				
				//recurse buffer
				getPosNode(pos, node, buffer);
			}
		}

		//result
		return buffer;
	};

	//set caret position << true
	const setPos = (start, end) => {
		if (!isEditable()) return;
		inputFocus();
		
		//normalize position start/end
		const max = isInput ? input.value.length : input.innerText.length;
		start = Number.isInteger(start) && start >= 0 ? start : 0;
		if (start > max) start = max;
		end = Number.isInteger(end) && end >= 0 ? end : start;
		if (end > max) end = max;

		//input set position
		if (isInput){
			if ('selectionStart' in input){
				setTimeout(() => {
					input.selectionStart = start;
					input.selectionEnd = end;
				});
				return true;
			}
			else if (input.createTextRange){
				const range = input.createTextRange();
				range.moveStart('character', start);
				range.collapse();
				range.moveEnd('character', end - start);
				range.select();
				return true;
			}
			return console.error('Failed to set caret position.', {input, start, end});
		}

		//contenteditable set position
		const sel = getWinSelection();
		if (sel.rangeCount === 0) return console.error('Failed to get selection range count.', {sel, input});
		let startNode = getPosNode(start);
		let endNode = start === end ? startNode : getPosNode(end);
		if (!startNode.node) return console.error(`Failed to get node at start position ${start}.`, input.childNodes);
		if (!endNode.node) return console.error(`Failed to get node at end position ${end}.`, input.childNodes);
		let range = new Range();
		range.setStart(startNode.node, startNode.offset);
		range.setEnd(endNode.node, endNode.offset);
		sel.removeAllRanges();
		sel.addRange(range);
		return true;
	};

	//set start position
	const start = () => setPos(0);

	//set end position
	const end = () => setPos(isInput ? input.value.length : input.innerText.length);

	//result methods
	return {pos, setPos, start, end};
};