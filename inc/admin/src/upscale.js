window.imageEdit.remove_background = function (postid, nonce, scale) {
	var t = this,
		data,
		w,
		h,
		fw,
		fh;

	if (t.notsaved(postid)) {
		return false;
	}

	data = {
		action: 'image-editor',
		_ajax_nonce: nonce,
		postid: postid,
	};

	(w = jQuery('#imgedit-scale-width-' + postid)),
		(h = jQuery('#imgedit-scale-height-' + postid)),
		(fw = t.intval(w.val())),
		(fh = t.intval(h.val()));

	data['remove_background'] = true;
	data['do'] = 'scale';
	data.fwidth = fw / 2;
	data.fheight = fh / 2;
	data.target = "all";

	t.toggleEditor(postid, 1);
	jQuery
		.post(ajaxurl, data, function (response) {
			jQuery('#image-editor-' + postid)
				.empty()
				.append(response.data.html);
			t.toggleEditor(postid, 0, true);
			// Refresh the attachment model so that changes propagate.
			if (t._view) {
				t._view.refresh();
			}
		})
		.done(function (response) {
			// Whether the executed action was `scale` or `restore`, the response does have a message.
			if (response && response.data.message.msg) {
				wp.a11y.speak(response.data.message.msg);
				return;
			}

			if (response && response.data.message.error) {
				wp.a11y.speak(response.data.message.error);
			}
		});
};

window.imageEdit.upscale = function (postid, nonce) {
	var t = this,
		data,
		w,
		h,
		fw,
		fh;

	if (t.notsaved(postid)) {
		return false;
	}

	data = {
		action: 'image-editor',
		_ajax_nonce: nonce,
		postid: postid,
	};

	(w = jQuery('#imgedit-scale-width-' + postid)),
		(h = jQuery('#imgedit-scale-height-' + postid)),
		(fw = t.intval(w.val())),
		(fh = t.intval(h.val()));

	data['upscale'] = scale;
	data['do'] = 'scale';
	data.fwidth = fw / 2;
	data.fheight = fh / 2;
	data.scale = scale;

	t.toggleEditor(postid, 1);
	jQuery
		.post(ajaxurl, data, function (response) {
			jQuery('#image-editor-' + postid)
				.empty()
				.append(response.data.html);
			t.toggleEditor(postid, 0, true);
			// Refresh the attachment model so that changes propagate.
			if (t._view) {
				t._view.refresh();
			}
		})
		.done(function (response) {
			// Whether the executed action was `scale` or `restore`, the response does have a message.
			if (response && response.data.message.msg) {
				wp.a11y.speak(response.data.message.msg);
				return;
			}

			if (response && response.data.message.error) {
				wp.a11y.speak(response.data.message.error);
			}
		});
};

var observer = new MutationObserver(function (mutations) {
	mutations.forEach(function (mutation) {
		if (mutation.type !== 'childList') {
			return;
		}
		mutation.addedNodes.forEach(function (addedNode) {
			if (
				addedNode.nodeType === Node.ELEMENT_NODE &&
				addedNode.matches('.imgedit-wrap')
			) {
				// Element matching the specific selector is added
				let scale = addedNode.querySelector('.imgedit-scale');
				let button = document.createElement('button');
				button.innerHTML = 'Upscale';
				button.className = 'imgedit-scale button'
				// Onclick looks like onclick="imageEdit.action(13, 'e55784bb04', 'scale')"
				let onclick = document.getElementById('imgedit-scale-button').attributes.onclick;
				let [ _, postid, nonce ] = onclick.value.match(/imageEdit.action\((\d+), '([^']+)'/);

				button.onclick = function() {
					window.imageEdit.upscale(postid, nonce, prompt('Upscale by how much? 2, 4 or 8 times.' ) );
				}
				scale.after( button );

				// BG removal
				let removeBgbutton = document.createElement('button');
				removeBgbutton.innerHTML = 'Remove Background';
				removeBgbutton.className = 'imgedit-scale button'
				// Onclick looks like onclick="imageEdit.action(13, 'e55784bb04', 'scale')"

				removeBgbutton.onclick = function() {
					window.imageEdit.remove_background(postid, nonce);
				}

				button.after( removeBgbutton );
			}
		});
	});
});
var config = { childList: true, subtree: true };
observer.observe(document.body, config);
