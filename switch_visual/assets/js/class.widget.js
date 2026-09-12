class CWidgetSwitchVisual extends CWidget {

	onActivate() {
		this._body.addEventListener('click', (e) => {
			const port = e.target.closest('[data-href]');
			if (port) {
				window.open(port.dataset.href, '_blank', 'noopener');
			}
		});
	}
}
