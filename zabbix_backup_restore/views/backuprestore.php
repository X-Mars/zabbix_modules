<?php
require_once dirname(__DIR__).'/lib/ModuleNotice.php';
echo \ZabbixModuleNotice::reminder(dirname(__DIR__));

require_once dirname(__DIR__).'/lib/SecurityToken.php';

use Modules\ZabbixBackupRestore\Lib\LanguageManager;

$t = static function (string $text): string  { return htmlspecialchars(LanguageManager::t($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$escape = static function (string $text): string  { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };

ob_start();
?>
<div id="zbr" class="zbr"
		data-request-error="<?= $t('The request failed. Check the file size and sign-in status, then try again.') ?>"
		data-server-error="<?= $t('The server could not complete the request (HTTP %s).') ?>"
		data-upload-limit="<?= $data['upload_limit'] ?>"
		data-upload-error="<?= $t('Upload a valid YAML backup file.') ?>"
		data-size-error="<?= $t('The backup exceeds the upload size limit.') ?>">
	<?php if (!$data['groups']): ?>
		<div class="zbr-alert zbr-error" role="alert"><?= $t('This module supports Zabbix 6.0, 7.0 and 8.0.') ?></div>
	<?php else: ?>
		<div class="zbr-alert zbr-warning">
			<?= $t('Backup files may contain credentials and commands. Store them securely and restore only trusted files.') ?>
		</div>
		<div class="zbr-layout">
			<section class="zbr-card" aria-labelledby="zbr-export-title">
				<header><h2 id="zbr-export-title"><?= $t('Export backup') ?></h2></header>
				<form id="zbr-export" action="zabbix.php?action=backuprestore" method="post">
					<input type="hidden" name="<?= $escape(\Modules\ZabbixBackupRestore\Lib\SecurityToken::name()) ?>" value="<?= $escape($data['token']) ?>">
					<div class="zbr-section-heading">
						<h3><?= $t('Configuration to export') ?></h3>
						<label class="zbr-choice"><input type="checkbox" id="zbr-select-all"><?= $t('Select all') ?></label>
					</div>
					<?php foreach ($data['groups'] as $group => $types): ?>
						<fieldset>
							<legend><?= $t($group) ?></legend>
							<div class="zbr-choices">
								<?php foreach ($types as $type => $label): ?>
									<label class="zbr-choice">
										<input type="checkbox" name="types[]" value="<?= $escape($type) ?>"
											<?= in_array($type, ['hosts', 'templates'], true) ? 'aria-describedby="zbr-owners"' : '' ?>>
										<span><?= $t($label) ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>
					<p class="zbr-help" id="zbr-owners"><?= $t('Includes items, triggers, low-level discovery and user macros.') ?></p>
					<p class="zbr-help"><?= $t('Include related configuration or make sure it already exists on the destination.') ?></p>
					<div id="zbr-export-error" class="zbr-alert zbr-error" role="alert" hidden></div>
					<div class="zbr-footer">
						<button class="zbr-button zbr-primary" type="submit" name="operation" value="export"
							data-busy="<?= $t('Preparing backup…') ?>"><?= $t('Download YAML') ?></button>
					</div>
				</form>
			</section>
			<section class="zbr-card" aria-labelledby="zbr-restore-title">
				<header><h2 id="zbr-restore-title"><?= $t('Restore backup') ?></h2></header>
				<form id="zbr-restore" action="zabbix.php?action=backuprestore" method="post" enctype="multipart/form-data">
					<input type="hidden" name="<?= $escape(\Modules\ZabbixBackupRestore\Lib\SecurityToken::name()) ?>" value="<?= $escape($data['token']) ?>">
					<label class="zbr-upload" for="zbr-file">
						<input id="zbr-file" type="file" name="yaml" accept=".yaml,.yml" aria-describedby="zbr-file-help">
						<span><strong><?= $t('Choose a YAML file') ?></strong> <?= $t('or drag it here') ?></span>
						<small id="zbr-file-help"><?= $escape(LanguageManager::format(
							'Choose a .yaml or .yml backup file (up to %s MiB).',
							(string) (floor($data['upload_limit'] / 104857.6) / 10)
						)) ?></small>
						<span id="zbr-file-name" class="zbr-file-name" aria-live="polite"></span>
					</label>
					<label class="zbr-choice zbr-update">
						<input type="checkbox" name="update_existing" value="1">
						<span><?= $t('Update existing configuration') ?></span>
					</label>
					<div id="zbr-restore-error" class="zbr-alert zbr-error" role="alert" hidden></div>
					<div id="zbr-restore-complete" class="zbr-alert zbr-success" role="status" hidden></div>
					<button id="zbr-preview-button" class="zbr-button" type="submit" name="operation" value="preview"
						data-busy="<?= $t('Checking backup…') ?>" disabled><?= $t('Preview restore') ?></button>

					<div id="zbr-review" class="zbr-review" aria-labelledby="zbr-review-title" hidden>
						<h3 id="zbr-review-title" tabindex="-1"><?= $t('Preview ready') ?></h3>
						<dl class="zbr-versions">
							<div><dt><?= $t('Source version') ?></dt><dd id="zbr-source-version"></dd></div>
							<div><dt><?= $t('Destination version') ?></dt><dd id="zbr-target-version"></dd></div>
						</dl>
						<div class="zbr-table">
							<table>
								<thead><tr><th scope="col"><?= $t('Configuration') ?></th>
									<th scope="col"><?= $t('Records in file') ?></th></tr></thead>
								<tbody id="zbr-counts"></tbody>
							</table>
						</div>
						<div id="zbr-notes" class="zbr-alert zbr-warning" hidden></div>
						<p class="zbr-help"><?= $t('API validation runs during restore. A failed operation rolls back the entire batch.') ?></p>
						<div class="zbr-footer">
							<button id="zbr-apply-button" class="zbr-button zbr-primary" type="submit" name="operation"
								value="restore" data-busy="<?= $t('Restoring configuration…') ?>"><?= $t('Restore configuration') ?></button>
						</div>
					</div>
				</form>
			</section>
		</div>
	<?php endif; ?>
</div>
<?php
$content = new CObject();
$content->addItem(ob_get_clean());
$page = ((class_exists('CHtmlPage') ? new CHtmlPage() : new CWidget()))->setTitle(LanguageManager::t('Backup and restore'))->addItem($content);

// Zabbix 6.0 ignores manifest.assets; load scoped assets with this view.
if (!class_exists('CHtmlPage')) {
    $page->addItem(new CTag('style', true, (new CObject())->addItem(file_get_contents(dirname(__DIR__).'/assets/css/backuprestore.css'))));
    $page->addItem((new CTag('script', true))->setAttribute('src',
        'modules/'.basename(dirname(__DIR__)).'/assets/js/backuprestore.js?v='.filemtime(dirname(__DIR__).'/assets/js/backuprestore.js')));
}
$page->show();
