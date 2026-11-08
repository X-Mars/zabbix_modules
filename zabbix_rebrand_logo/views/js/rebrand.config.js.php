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
			'Preparing image for upload…', 'Check the login page logo and try again.'
		]), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
		const [sizeLabelFormat, applyLabel, zoomLabel, xLabel, yLabel, imageError, appliedLabel, limitError,
			preparingLabel, uploadError] = labels;
		const sizes = {logo_main: [120, 30], logo_sidebar: [91, 24], logo_compact: [24, 24], favicon: [32, 32]};
		const sizeSlider = form.elements['logo_main_width'];
		const uploadLimit = <?= json_encode($data['logo_upload_limit']) ?>;
		const updateButton = form.elements['update'];
		const updateStatus = document.getElementById('rebrand-update-status');
		let pendingCrops = 0;
		let loginUploadNeedsCrop = () => false;
		let prepareLoginUpload;
		let preparingSubmit = false;

		function loginLogoSize() {
			const width = sizeSlider.valueAsNumber;
			return [width, width / Number(sizeSlider.dataset.aspectRatio)];
		}

		form.addEventListener('submit', async event => {
			if (pendingCrops || preparingSubmit) {
				event.preventDefault();
				return;
			}
			updateStatus.textContent = '';
			if (!loginUploadNeedsCrop()) {
				return;
			}

			event.preventDefault();
			preparingSubmit = true;
			updateButton.disabled = true;
			updateStatus.textContent = preparingLabel;
			try {
				if (!await prepareLoginUpload()) {
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
			const customSizing = key === 'logo_main' && Boolean(sizeSlider);
			const savedLogo = form.querySelector('[data-rebrand-preview="' + key + '"]');
			const sizeLabel = customSizing ? sizeSlider.closest('label') : null;
			const canvas = document.createElement('canvas');
			canvas.className = 'rebrand-logo-preview';
			canvas.hidden = true;
			(savedLogo ?? input).before(canvas);

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
			input.after(controls, apply, status);

			let img = null;
			let sourceUrl = null;
			let revision = 0;
			let encodedRevision = -1;
			let cropRequested = false;
			let imageReady = Promise.resolve();
			const remove = form.elements['remove_' + key];

			function updateSavedPreview() {
				const [width, height] = loginLogoSize();
				const label = width + ' × ' + height + ' px';
				sizeLabel.querySelector('span').textContent = sizeLabelFormat.replace('%s', label);
				sizeSlider.setAttribute('aria-valuetext', label);
				if (savedLogo) {
					savedLogo.style.width = width + 'px';
					savedLogo.style.aspectRatio = width + ' / ' + height;
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

			function getCrop() {
				const [width, height] = customSizing ? loginLogoSize() : sizes[key];
				const scale = Math.max(width / img.naturalWidth, height / img.naturalHeight) * sliders[0].valueAsNumber;
				const sourceWidth = width / scale;
				const sourceHeight = height / scale;
				const x = (img.naturalWidth - sourceWidth) * sliders[1].valueAsNumber / 100;
				const y = (img.naturalHeight - sourceHeight) * sliders[2].valueAsNumber / 100;
				return {width, height, sourceWidth, sourceHeight, x, y, scale};
			}

			function render(target, crop, density) {
				target.width = Math.round(crop.width * density);
				target.height = Math.round(crop.height * density);
				const context = target.getContext('2d');
				context.imageSmoothingQuality = 'high';
				context.drawImage(img, crop.x, crop.y, crop.sourceWidth, crop.sourceHeight,
					0, 0, target.width, target.height);
			}

			function draw() {
				revision++;
				status.textContent = '';
				if (!img) {
					return;
				}

				const crop = getCrop();
				canvas.style.width = crop.width + 'px';
				canvas.style.aspectRatio = crop.width + ' / ' + crop.height;
				render(canvas, crop, Math.min(3, window.devicePixelRatio || 1));
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
				status.textContent = '';
				const file = input.files[0];
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

			async function encodeCrop() {
				if (!img) {
					status.textContent = imageError;
					return false;
				}

				cropRequested = true;
				const crop = getCrop();
				const appliedRevision = revision;
				const output = document.createElement('canvas');
				// Keep up to twice the display resolution when the source actually has that detail.
				const density = customSizing ? Math.max(1, Math.min(2, 1 / crop.scale)) : 1;
				const encode = () => new Promise(resolve => output.toBlob(resolve, 'image/png'));

				pendingCrops++;
				apply.disabled = true;
				try {
					render(output, crop, density);
					let blob = await encode();
					if (blob && blob.size > uploadLimit && density > 1 && revision === appliedRevision) {
						render(output, crop, 1);
						blob = await encode();
					}
					if (revision !== appliedRevision) {
						return false;
					}
					if (!blob || blob.size > uploadLimit) {
						status.textContent = blob ? limitError : imageError;
						return false;
					}

					const transfer = new DataTransfer();
					transfer.items.add(new File([blob], key + '.png', {type: 'image/png'}));
					input.files = transfer.files;
					if (remove) {
						remove.checked = false;
					}
					encodedRevision = revision;
					status.textContent = appliedLabel;
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

			apply.addEventListener('click', encodeCrop);
			if (key === 'logo_main') {
				loginUploadNeedsCrop = () => {
					const file = input.files[0];
					return Boolean(file && !remove?.checked && encodedRevision !== revision
						&& (cropRequested || file.size > uploadLimit));
				};

				prepareLoginUpload = async () => {
					const file = input.files[0];
					await imageReady;
					// A new file may have been chosen while the previous one was decoding.
					if (input.files[0] !== file) {
						return false;
					}
					if (remove?.checked) {
						return true;
					}
					if (!/\.(png|jpe?g|gif)$/i.test(file.name)) {
						status.textContent = imageError;
						return false;
					}
					return encodeCrop();
				};
			}
		});

		// Re-sync after the browser restores form state on back/forward navigation.
		update();
	})();
</script>
