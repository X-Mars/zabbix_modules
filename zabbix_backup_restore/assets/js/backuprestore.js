document.addEventListener('DOMContentLoaded', () => {
	const page = document.getElementById('zbr');
	const exportForm = document.getElementById('zbr-export');

	if (!page || !exportForm) {
		return;
	}

	const exportError = document.getElementById('zbr-export-error');
	const exportButton = exportForm.querySelector('[value="export"]');
	const selectAll = document.getElementById('zbr-select-all');
	const types = Array.from(exportForm.querySelectorAll('[name="types[]"]'));

	const restoreForm = document.getElementById('zbr-restore');
	const restoreError = document.getElementById('zbr-restore-error');
	const restoreComplete = document.getElementById('zbr-restore-complete');
	const fileInput = document.getElementById('zbr-file');
	const upload = fileInput.closest('label');
	const fileName = document.getElementById('zbr-file-name');
	const update = restoreForm.elements.update_existing;
	const previewButton = document.getElementById('zbr-preview-button');
	const applyButton = document.getElementById('zbr-apply-button');
	const review = document.getElementById('zbr-review');
	const notes = document.getElementById('zbr-notes');
	let selectedFile = null;
	let proof = '';
	let restoreBusy = false;

	const alert = (element, lines) => {
		element.replaceChildren(...lines.map(line => {
			const paragraph = document.createElement('p');
			paragraph.textContent = line;
			return paragraph;
		}));
		element.hidden = !lines.length;
	};

	const syncSelection = () => {
		const selected = types.filter(type => type.checked).length;
		selectAll.checked = selected === types.length;
		selectAll.indeterminate = selected > 0 && selected < types.length;
		exportButton.disabled = selected === 0 || exportForm.getAttribute('aria-busy') === 'true';
	};

	const request = async (form, body, contentType) => {
		const response = await fetch(form.action, {method: 'POST', body, credentials: 'same-origin'});
		const type = response.headers.get('Content-Type') || '';
		if (response.ok && type.startsWith(contentType)) {
			return response;
		}
		if (type.startsWith('application/json')) {
			const result = await response.json();
			throw new Error(result.error || page.dataset.requestError);
		}
		if (response.status === 413) {
			throw new Error(page.dataset.sizeError);
		}
		if (response.status >= 500) {
			throw new Error(page.dataset.serverError.replace('%s', response.status));
		}
		throw new Error(page.dataset.requestError);
	};

	const submit = async (form, button, errorBox, run) => {
		if (form.getAttribute('aria-busy') === 'true') {
			return;
		}
		const label = button.textContent;
		alert(errorBox, []);
		form.setAttribute('aria-busy', 'true');
		button.disabled = true;
		button.textContent = button.dataset.busy;

		try {
			await run();
		}
		catch (error) {
			alert(errorBox, [error.message || page.dataset.requestError]);
		}
		finally {
			form.setAttribute('aria-busy', 'false');
			button.textContent = label;
			button.disabled = false;
		}
	};

	selectAll.addEventListener('change', () => {
		types.forEach(type => { type.checked = selectAll.checked; });
		syncSelection();
	});
	types.forEach(type => type.addEventListener('change', syncSelection));
	syncSelection();

	exportForm.addEventListener('submit', async event => {
		event.preventDefault();
		if (!types.some(type => type.checked)) {
			return;
		}

		const data = new FormData(exportForm);
		data.set('operation', 'export');
		await submit(exportForm, exportButton, exportError, async () => {
			const response = await request(exportForm, data, 'application/yaml');
			const url = URL.createObjectURL(await response.blob());
			const download = document.createElement('a');
			const name = (response.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
			download.href = url;
			download.download = name ? name[1] : 'zabbix_backup.yaml';
			document.body.append(download);
			download.click();
			download.remove();
			setTimeout(() => URL.revokeObjectURL(url), 1000);
		});
		syncSelection();
	});

	const syncUpload = () => {
		previewButton.disabled = restoreBusy || !selectedFile;
		applyButton.disabled = restoreBusy || !proof;
		fileInput.disabled = restoreBusy;
		update.disabled = restoreBusy;
	};

	const clearReview = () => {
		proof = '';
		review.hidden = true;
		alert(restoreError, []);
		alert(restoreComplete, []);
	};

	const pickFile = file => {
		if (restoreBusy) {
			return;
		}
		clearReview();
		selectedFile = null;
		if (file && (!/\.ya?ml$/i.test(file.name) || file.size === 0)) {
			alert(restoreError, [page.dataset.uploadError]);
		}
		else if (file && file.size > Number(page.dataset.uploadLimit)) {
			alert(restoreError, [page.dataset.sizeError]);
		}
		else {
			selectedFile = file;
		}
		fileName.textContent = selectedFile ? selectedFile.name : '';
		syncUpload();
	};

	fileInput.addEventListener('change', () => pickFile(fileInput.files[0]));
	update.addEventListener('change', () => {
		clearReview();
		syncUpload();
	});
	upload.addEventListener('dragover', event => {
		event.preventDefault();
		upload.classList.toggle('zbr-dragging', !restoreBusy);
	});
	upload.addEventListener('dragleave', () => upload.classList.remove('zbr-dragging'));
	upload.addEventListener('drop', event => {
		event.preventDefault();
		upload.classList.remove('zbr-dragging');
		pickFile(event.dataTransfer.files[0]);
	});

	restoreForm.addEventListener('submit', async event => {
		event.preventDefault();
		const button = event.submitter || previewButton;
		const restoring = button === applyButton;
		if (!selectedFile || restoreBusy || (restoring && !proof)) {
			return;
		}

		const data = new FormData(restoreForm);
		data.set('yaml', selectedFile, selectedFile.name);
		data.set('operation', restoring ? 'restore' : 'preview');
		if (restoring) {
			data.set('proof', proof);
		}
		else {
			clearReview();
		}

		restoreBusy = true;
		syncUpload();
		await submit(restoreForm, button, restoreError, async () => {
			const response = await request(restoreForm, data, 'application/json');
			const result = await response.json();
			if (restoring) {
				clearReview();
				alert(restoreComplete, [result.message, ...result.notes]);
				return;
			}

			proof = result.proof;
			document.getElementById('zbr-source-version').textContent = result.source;
			document.getElementById('zbr-target-version').textContent = result.target;
			document.getElementById('zbr-counts').replaceChildren(...result.counts.map(row => {
				const tr = document.createElement('tr');
				tr.insertCell().textContent = row.name;
				tr.insertCell().textContent = row.count;
				return tr;
			}));
			alert(notes, result.notes);
			review.hidden = false;
			document.getElementById('zbr-review-title').focus();
		});
		restoreBusy = false;
		syncUpload();
	});
});
