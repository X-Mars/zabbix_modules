<?php

/**
 * Rebrand module - Branding configuration view script.
 *
 * @var CView $this
 */

use Modules\ZabbixRebrandLogo\Lib\ServerNameOverride;
use Modules\ZabbixRebrandLogo\Lib\LanguageManager;
?>

<script>
	(() => {
		const form = document.forms['rebrand-form'];
		const server_name = form.elements['server_name'];
		const custom_mode = <?= json_encode(ServerNameOverride::MODE_CUSTOM) ?>;

		// The name is only used by the Custom mode. A disabled field is not posted,
		// so the saved name is kept while another mode is selected.
		const update = () => {
			server_name.disabled = form.elements['server_name_mode'].value !== custom_mode;
		};

		form.addEventListener('change', (e) => {
			if (e.target.name === 'server_name_mode') {
				update();

				if (!server_name.disabled) {
					server_name.focus();
				}
			}
		});

		const labels = <?= json_encode(array_map([LanguageManager::class, 't'], [
			'Logo size (4:1, %s)', 'Apply crop', 'Zoom', 'Horizontal position', 'Vertical position',
			'Unable to load image.', 'Crop applied.',
			'The cropped image exceeds the upload limit.',
			'Image resized for upload.', 'Preparing image for upload…', 'Check the selected images and try again.',
			'Image scale', 'Image scaled for upload.'
		]), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
		const [sizeLabelFormat, applyLabel, zoomLabel, xLabel, yLabel, imageError, appliedLabel, limitError,
			resizedLabel, preparingLabel, uploadError, imageScaleLabel, scaledLabel] = labels;
		const sizes = {logo_main: [120, 30], logo_sidebar: [91, 24], logo_compact: [24, 24], favicon: [32, 32]};
		const sizeSlider = form.elements['logo_main_width'];
		const uploadLimit = <?= json_encode($data['logo_upload_limit']) ?>;
		const postBudget = <?= json_encode($data['logo_post_budget']) ?>;
		const updateButton = form.elements['update'];
		const updateStatus = document.getElementById('rebrand-update-status');
		const uploads = [];
		let pendingCrops = 0;
		let preparingSubmit = false;

		function loginLogoSize() {
			const width = sizeSlider.valueAsNumber;
			return [width, width / Number(sizeSlider.dataset.aspectRatio)];
		}

		function getUploadLimit() {
			const files = uploads.map(upload => upload.getFile()).filter(Boolean);
			const total = files.reduce((sum, file) => sum + file.size, 0);
			if (postBudget !== null && total > postBudget) {
				return Math.min(uploadLimit, Math.floor(postBudget / files.length));
			}
			return uploadLimit;
		}

		form.addEventListener('submit', async event => {
			if (pendingCrops || preparingSubmit) {
				event.preventDefault();
				return;
			}
			updateStatus.textContent = '';
			const limit = getUploadLimit();
			const pendingUploads = uploads.filter(upload => upload.needsCrop(limit));
			if (!pendingUploads.length) {
				return;
			}

			event.preventDefault();
			preparingSubmit = true;
			updateButton.disabled = true;
			updateStatus.textContent = preparingLabel;
			try {
				for (const upload of pendingUploads) {
					if (!await upload.prepare(limit)) {
						updateStatus.textContent = uploadError;
						return;
					}
				}

				const finalLimit = getUploadLimit();
				if (finalLimit < limit) {
					// Resizing a saved image can add a file to the request.
					for (const upload of uploads.filter(upload => upload.needsCrop(finalLimit))) {
						if (!await upload.prepare(finalLimit)) {
							updateStatus.textContent = uploadError;
							return;
						}
					}
				}
				if (uploads.some(upload => upload.needsCrop(getUploadLimit()))) {
					updateStatus.textContent = uploadError;
					return;
				}

				updateStatus.textContent = '';
				if (form.reportValidity()) {
					// The user-initiated submit already ran validation. Send the
					// prepared file without dispatching another submit event.
					HTMLFormElement.prototype.submit.call(form);
				}
			}
			catch (error) {
				updateStatus.textContent = uploadError;
			}
			finally {
				updateButton.disabled = false;
				preparingSubmit = false;
			}
		});

		form.querySelectorAll('[data-rebrand-upload]').forEach(input => {
			const key = input.dataset.rebrandUpload;
			const isLoginLogo = key === 'logo_main';
			const isSidebar = key === 'logo_sidebar';
			const customSizing = isLoginLogo && Boolean(sizeSlider);
			const pixelDensity = Math.min(3, window.devicePixelRatio || 1);
			const savedLogo = form.querySelector('[data-rebrand-preview="' + key + '"]');
			const sizeLabel = customSizing ? sizeSlider.closest('label') : null;
			const uploadActions = input.closest('.rebrand-upload-actions');
			const canvas = document.createElement('canvas');
			canvas.className = 'rebrand-logo-preview';
			canvas.hidden = true;
			(savedLogo ?? uploadActions).before(canvas);

			let scaleSlider;
			let scaleValue;
			if (!isLoginLogo) {
				const viewport = document.createElement('div');
				viewport.className = 'rebrand-preview-viewport';
				canvas.before(viewport);
				viewport.append(canvas);
				if (savedLogo) {
					viewport.append(savedLogo);
					setPreviewSize(savedLogo, ...sizes[key]);
					if (isSidebar) {
						savedLogo.style.objectFit = 'cover';
					}
					savedLogo.addEventListener('load', updateImageScale);
				}
				setPreviewSize(canvas, ...sizes[key]);

				const label = document.createElement('label');
				label.className = 'rebrand-logo-size rebrand-preview-size';
				scaleValue = document.createElement('span');
				scaleSlider = document.createElement('input');
				scaleSlider.type = 'range';
				scaleSlider.min = '25';
				scaleSlider.max = '300';
				scaleSlider.step = '5';
				scaleSlider.value = '100';
				scaleSlider.setAttribute('aria-label', imageScaleLabel);
				label.append(scaleValue, scaleSlider);
				viewport.after(label);
				scaleSlider.addEventListener('input', () => {
					revision++;
					status.textContent = '';
					updateImageScale();
				});
			}

			const controls = document.createElement('div');
			controls.className = 'rebrand-crop-controls';

			const sliders = [zoomLabel, xLabel, yLabel].map((name, i) => {
				const label = document.createElement('label');
				label.textContent = name;
				const slider = document.createElement('input');
				slider.type = 'range';
				slider.min = i === 0 ? '1' : '0';
				slider.max = i === 0 ? '3' : '100';
				slider.step = i === 0 ? '0.01' : '1';
				slider.defaultValue = i === 0 ? '1' : '50';
				label.appendChild(slider);
				label.hidden = true;
				controls.appendChild(label);
				return slider;
			});
			if (sizeLabel) {
				controls.appendChild(sizeLabel);
			}

			const apply = document.createElement('button');
			apply.type = 'button';
			apply.className = 'rebrand-crop-apply';
			apply.textContent = applyLabel;
			apply.hidden = true;
			const status = document.createElement('span');
			status.setAttribute('role', 'status');
			status.className = 'rebrand-crop-status';
			controls.hidden = !sizeLabel;
			const cropActions = document.createElement('div');
			cropActions.className = 'rebrand-crop-actions';
			cropActions.append(apply, status);
			uploadActions.after(controls, cropActions);

			let img = null;
			let sourceUrl = null;
			let revision = 0;
			let encodedRevision = -1;
			let cropRequested = false;
			let scalePrepared = false;
			let selectedFile = null;
			let imageReady = Promise.resolve();
			const remove = form.elements['remove_' + key];
			updateImageScale();

			function setPreviewSize(preview, width, height) {
				preview.style.width = width + 'px';
				preview.style.aspectRatio = width + ' / ' + height;
			}

			function iconSource() {
				return img || (selectedFile ? null : savedLogo);
			}

			function needsScale() {
				return scaleSlider && (scaleSlider.valueAsNumber !== 100 || scalePrepared);
			}

			function scaledIconFrame(source) {
				if (isSidebar) {
					const [width, height] = sizes[key];
					const units = Math.max(1, Math.round(source.naturalWidth * scaleSlider.valueAsNumber / (100 * width)));
					return getCrop(source, [width * units, height * units]);
				}
				const frame = cropRequested ? getCrop() : imageFrame(source);
				const scale = scaleSlider.valueAsNumber / 100;
				return {...frame, width: Math.max(1, Math.round(frame.width * scale)),
					height: Math.max(1, Math.round(frame.height * scale))};
			}

			function updateImageScale() {
				if (!scaleSlider) {
					return;
				}
				const source = iconSource();
				const percentage = scaleSlider.valueAsNumber + '%';
				let value = percentage;
				if (source?.naturalWidth) {
					const {width, height} = scaledIconFrame(source);
					value += ', ' + width + ' × ' + height + ' px';
				}
				scaleValue.textContent = imageScaleLabel + ' (' + value + ')';
				scaleSlider.setAttribute('aria-valuetext', value);
			}

			function updateSavedPreview() {
				const [width, height] = loginLogoSize();
				const label = width + ' × ' + height + ' px';
				sizeLabel.querySelector('span').textContent = sizeLabelFormat.replace('%s', label);
				sizeSlider.setAttribute('aria-valuetext', label);
				if (savedLogo) {
					setPreviewSize(savedLogo, width, height);
				}
			}

			function showCrop(show) {
				canvas.hidden = apply.hidden = !show;
				controls.hidden = !show && !sizeLabel;
				sliders.forEach(slider => slider.parentElement.hidden = !show);
				if (savedLogo) {
					savedLogo.hidden = show;
				}
			}

			function getCrop(source = img, [width, height] = customSizing ? loginLogoSize() : sizes[key]) {
				const scale = Math.max(width / source.naturalWidth, height / source.naturalHeight) * sliders[0].valueAsNumber;
				const sourceWidth = width / scale;
				const sourceHeight = height / scale;
				const x = (source.naturalWidth - sourceWidth) * sliders[1].valueAsNumber / 100;
				const y = (source.naturalHeight - sourceHeight) * sliders[2].valueAsNumber / 100;
				return {width, height, sourceWidth, sourceHeight, x, y, scale, source};
			}

			function imageFrame(source, [width, height] = [source.naturalWidth, source.naturalHeight]) {
				const sourceWidth = source.naturalWidth;
				const sourceHeight = source.naturalHeight;
				const scale = Math.min(1, width / sourceWidth, height / sourceHeight);

				return {width: sourceWidth * scale, height: sourceHeight * scale, sourceWidth, sourceHeight,
					x: 0, y: 0, scale, source};
			}

			function render(target, crop, density) {
				target.width = Math.max(1, Math.round(crop.width * density));
				target.height = Math.max(1, Math.round(crop.height * density));
				const context = target.getContext('2d');
				context.imageSmoothingQuality = 'high';
				context.drawImage(crop.source ?? img, crop.x, crop.y, crop.sourceWidth, crop.sourceHeight,
					0, 0, target.width, target.height);
			}

			function draw() {
				revision++;
				status.textContent = '';
				if (!img) {
					return;
				}

				const crop = isSidebar ? scaledIconFrame(img)
					: (!isLoginLogo && !cropRequested ? imageFrame(img) : getCrop());
				renderPreview(crop);
				updateImageScale();
			}

			function renderPreview(crop) {
				if (isLoginLogo) {
					setPreviewSize(canvas, crop.width, crop.height);
					render(canvas, crop, Math.max(1, Math.min(pixelDensity, 1 / crop.scale)));
				}
				else {
					const [width, height] = sizes[key];
					const fitScale = Math.min(width / crop.width, height / crop.height);
					// Keep enough detail for the largest preview without allocating a full-size canvas.
					render(canvas, crop, Math.min(1 / crop.scale, 3 * pixelDensity * fitScale));
				}
			}

			function editCrop() {
				if (img || input.files.length) {
					cropRequested = true;
				}
				draw();
			}

			sliders.forEach(slider => slider.addEventListener('input', editCrop));
			if (customSizing) {
				sizeSlider.addEventListener('input', () => {
					updateSavedPreview();
					editCrop();
				});
				updateSavedPreview();
			}

			input.addEventListener('change', () => {
				if (sourceUrl) {
					URL.revokeObjectURL(sourceUrl);
				}
				sourceUrl = null;
				img = null;
				showCrop(false);
				revision++;
				cropRequested = false;
				scalePrepared = false;
				status.textContent = '';
				const file = input.files[0];
				selectedFile = file ?? null;
				if (scaleSlider) {
					scaleSlider.value = '100';
					updateImageScale();
				}
				imageReady = Promise.resolve();
				if (!file) {
					return;
				}

				const url = URL.createObjectURL(file);
				sourceUrl = url;
				imageReady = new Promise(resolve => {
					const next = new Image();
					next.onload = () => {
						if (sourceUrl === url) {
							img = next;
							sliders.forEach(slider => slider.value = slider.defaultValue);
							showCrop(true);
							draw();
						}
						URL.revokeObjectURL(url);
						resolve();
					};
					next.onerror = () => {
						if (sourceUrl === url) {
							status.textContent = imageError;
						}
						URL.revokeObjectURL(url);
						resolve();
					};
					next.src = url;
				});
			});

			async function encodeCrop(limit, {manual = false, resize = false} = {}) {
				const source = isLoginLogo ? img : iconSource();
				if (!source?.naturalWidth) {
					status.textContent = imageError;
					return false;
				}

				const scaleRequested = Boolean(needsScale());
				cropRequested = !resize;
				const crop = scaleRequested || isSidebar
					? scaledIconFrame(source)
					: (resize ? imageFrame(source, sizes[key]) : getCrop());
				const appliedRevision = revision;
				const output = document.createElement('canvas');
				const maxDensity = isLoginLogo ? (customSizing ? 2 : 1) : 3 * pixelDensity;
				const longestEdge = Math.max(crop.width, crop.height);
				const density = scaleRequested || isSidebar
					? Math.min(1, 4096 / longestEdge)
					: Math.max(1, Math.min(maxDensity, 1 / crop.scale));
				const sidebarUnits = isSidebar ? crop.width / sizes[key][0] : 0;
				// Both exported dimensions must use the same integer multiple of 91 × 24.
				const outputDensity = scale => isSidebar
					? Math.max(1, Math.floor(sidebarUnits * scale + Number.EPSILON)) / sidebarUnits
					: scale;
				const encode = (scale) => {
					render(output, crop, outputDensity(scale));
					return new Promise(resolve => output.toBlob(resolve, 'image/png'));
				};

				pendingCrops++;
				apply.disabled = true;
				try {
					let blob = await encode(density);
					let savedDensity = density;
					const minimumDensity = isSidebar ? 1 / sidebarUnits : 1 / Math.max(1, longestEdge);
					if (blob?.size > limit && density > minimumDensity && revision === appliedRevision) {
						// Start with a version that fits, then keep the largest successful encode.
						let low = minimumDensity;
						let high = density;
						blob = await encode(low);
						savedDensity = low;
						for (let attempt = 0; blob && blob.size <= limit && revision === appliedRevision
								&& attempt < 7 && (high - low) * longestEdge >= 1; attempt++) {
							const middle = (low + high) / 2;
							const candidate = await encode(middle);
							if (candidate && candidate.size <= limit) {
								blob = candidate;
								savedDensity = low = middle;
							}
							else {
								high = middle;
							}
						}
					}
					if (revision !== appliedRevision) {
						return false;
					}
					if (!blob || blob.size > limit) {
						status.textContent = blob ? limitError : imageError;
						return false;
					}

					const transfer = new DataTransfer();
					transfer.items.add(new File([blob], key + '.png', {type: 'image/png'}));
					input.files = transfer.files;
					if (remove && manual) {
						remove.checked = false;
					}
					encodedRevision = revision;
					scalePrepared = scaleRequested;
					renderPreview(crop);
					updateImageScale();
					const finalDensity = outputDensity(savedDensity);
					const dimensions = Math.max(1, Math.round(crop.width * finalDensity)) + ' × '
						+ Math.max(1, Math.round(crop.height * finalDensity)) + ' px';
					status.textContent = scaleRequested ? scaledLabel + ' ' + dimensions
						: (resize ? resizedLabel : appliedLabel);
					return true;
				}
				catch (error) {
					if (revision === appliedRevision) {
						status.textContent = imageError;
					}
					return false;
				}
				finally {
					pendingCrops--;
					apply.disabled = false;
				}
			}

			apply.addEventListener('click', () => {
				if (!preparingSubmit) {
					encodeCrop(uploadLimit, {manual: true});
				}
			});

			const getFile = () => remove?.checked ? null : input.files[0];
			function sidebarNeedsCrop() {
				if (!isSidebar) {
					return false;
				}
				const source = iconSource();
				const [width, height] = sizes[key];
				return (selectedFile && !source) || (source?.naturalWidth
					&& (source.naturalWidth * height !== source.naturalHeight * width || cropRequested));
			}

			uploads.push({
				getFile,
				needsCrop(limit) {
					const file = getFile();
					const changed = encodedRevision !== revision;
					return !remove?.checked && Boolean((file && file.size > limit)
						|| (file && isLoginLogo && cropRequested && changed)
						|| (sidebarNeedsCrop() && changed)
						|| ((selectedFile || savedLogo) && needsScale() && changed));
				},
				async prepare(limit) {
					const file = getFile();
					await imageReady;
					if (!selectedFile && savedLogo && (needsScale() || sidebarNeedsCrop())) {
						await savedLogo.decode();
					}
					if (remove?.checked) {
						return true;
					}
					// A new file may have been chosen while the previous one was decoding.
					if (input.files[0] !== file) {
						return false;
					}
					const extension = selectedFile && '.' + selectedFile.name.split('.').pop().toLowerCase();
					if (extension && !input.accept.split(',').includes(extension)) {
						status.textContent = imageError;
						return false;
					}
					if (!this.needsCrop(limit)) {
						return true;
					}
					return encodeCrop(limit, {resize: !isLoginLogo && !cropRequested});
				}
			});
			form.addEventListener('formdata', event => {
				if (remove?.checked) {
					event.formData.delete(input.name);
				}
			});
		});

		// Re-sync after the browser restores form state on back/forward navigation.
		update();
	})();
</script>
