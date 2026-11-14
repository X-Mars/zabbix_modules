<?php

use Modules\ZabbixBackupMysql\Lib\LanguageManager;
use Modules\ZabbixBackupMysql\Lib\SqlExport;

$t = [LanguageManager::class, 't'];
$eligibleTables = $data['exclude_history'] ? array_filter($data['tables'], static function (array $table): bool {
    return !SqlExport::isHistoryTable($table['name']);
}) : $data['tables'];
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$format = static function ($value): string {
    $bytes = (float) $value;
    foreach (['B', 'KiB', 'MiB', 'GiB', 'TiB'] as $unit) {
        if ($bytes < 1024 || $unit === 'TiB') {
            return number_format($bytes, $unit === 'B' ? 0 : 2) . ' ' . $unit;
        }
        $bytes /= 1024;
    }
};

ob_start();
?>
<div class="zbx-sql-backup">
    <?php if ($data['error'] !== ''): ?>
        <div class="zb-alert zb-error" role="alert"><?= $escape($data['error']) ?></div>
    <?php endif; ?>
    <?php if ($data['saved'] !== null): ?>
        <div class="zb-alert zb-success" role="status">
            <strong><?= $escape($t($data['saved']['automatic']
                ? 'Backup exceeded 500 MB and was saved on the server' : 'Backup saved on the server')) ?></strong>
            <div><code><?= $escape($data['saved']['path']) ?></code> · <?= $escape($format($data['saved']['bytes'])) ?></div>
        </div>
    <?php endif; ?>
    <?php if ($data['can_export']): ?>
        <form id="zb-export" class="zb-layout" action="zabbix.php?action=backupmysql" method="post">
            <input type="hidden" name="<?= $escape(CSRF_TOKEN_NAME) ?>" value="<?= $escape($data['token']) ?>">
            <section class="zb-card" aria-labelledby="zb-tables-heading">
                <header class="zb-card-header">
                    <h2 id="zb-tables-heading"><?= $escape($t('Database tables')) ?></h2>
                    <span class="zb-count" aria-live="polite"
                        data-format="<?= $escape($t('Selected tables: %d / %d')) ?>"><?= $escape(LanguageManager::tf('Selected tables: %d / %d',
                            $data['scope'] === 'all' ? count($eligibleTables)
                                : count(array_intersect(array_keys($eligibleTables), $data['selected'])),
                            count($data['tables']))) ?></span>
                </header>
                <div class="zb-table-tools">
                    <input class="zb-input" id="zb-search" type="search"
                        placeholder="<?= $escape($t('Search tables')) ?>" aria-label="<?= $escape($t('Search tables')) ?>">
                    <label class="zb-radio">
                        <input type="checkbox" id="zb-exclude-history" name="exclude_history" value="1"
                            <?= $data['exclude_history'] ? 'checked' : '' ?>>
                        <span><?= $escape($t('Exclude history records')) ?></span>
                    </label>
                    <small><?= $escape($t('Table sizes include data and indexes.')) ?></small>
                </div>
                <div class="zb-table-scroll">
                    <table class="zb-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="zb-select-visible"
                                    aria-label="<?= $escape($t('Select all filtered tables')) ?>"></th>
                                <th scope="col"><?= $escape($t('Table name')) ?></th>
                                <th scope="col"><?= $escape($t('Engine')) ?></th>
                                <th scope="col" class="zb-size"><?= $escape($t('Estimated size')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['tables'] as $table): ?>
                                <?php $history = SqlExport::isHistoryTable($table['name']); ?>
                                <tr data-table="<?= $escape($table['name']) ?>"<?= $history ? ' data-history' : '' ?>
                                    <?= $history && $data['exclude_history'] ? 'hidden' : '' ?>>
                                    <td><input type="checkbox" name="tables[]" value="<?= $escape($table['name']) ?>"
                                        aria-label="<?= $escape($table['name']) ?>"
                                        <?= $history && $data['exclude_history'] ? 'disabled' : '' ?>
                                        <?= in_array($table['name'], $data['selected'], true) ? 'checked' : '' ?>></td>
                                    <td><code><?= $escape($table['name']) ?></code></td>
                                    <td><?= $escape($table['engine']) ?></td>
                                    <td class="zb-size"><?= $escape($format($table['bytes'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="zb-no-tables" hidden>
                                <td colspan="4" class="zb-empty"><?= $escape($t('No matching tables.')) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="zb-card" aria-labelledby="zb-options-heading">
                <header class="zb-card-header">
                    <h2 id="zb-options-heading"><?= $escape($t('Export options')) ?></h2>
                </header>
                <div class="zb-options">
                    <?php foreach ([
                        'scope' => ['Table scope', ['all' => 'All tables', 'selected' => 'Selected tables']],
                        'mode' => ['Contents', ['both' => 'Structure and data', 'schema' => 'Structure only', 'data' => 'Data only']],
                        'destination' => ['Destination', ['download' => 'Download in browser', 'server' => 'Save on server']]
                    ] as $name => [$label, $options]): ?>
                        <fieldset class="zb-fieldset">
                            <legend><?= $escape($t($label)) ?></legend>
                            <?php foreach ($options as $value => $text): ?>
                                <label class="zb-radio">
                                    <input type="radio" name="<?= $escape($name) ?>" value="<?= $escape($value) ?>"
                                        <?= $data[$name] === $value ? 'checked' : '' ?>>
                                    <span><?= $escape($t($text)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <small><?= $escape($t('Unselected tables are exported as structure only.')) ?></small>
                    <label class="zb-label" for="zb-directory"><?= $escape($t('Server directory')) ?></label>
                    <input class="zb-input" type="text" id="zb-directory" name="directory"
                        value="<?= $escape($data['directory']) ?>" aria-describedby="zb-directory-help">
                    <small id="zb-directory-help"><?= $escape($t('SQL files over 500 MB are saved to this directory automatically.')) ?></small>
                    <button class="zb-button" type="submit"><?= $escape($t('Export SQL')) ?></button>
                </div>
            </section>
        </form>
    <?php elseif ($data['error'] === ''): ?>
        <div class="zb-alert" role="status"><?= $escape($t('No tables are available to export.')) ?></div>
    <?php endif; ?>
</div>
<?php
$markup = ob_get_clean();
// CHtmlPage accepts a CObject containing markup rendered and escaped above.
$content = new CObject();
$content->addItem($markup);
(new CHtmlPage())->setTitle($t('MySQL backup'))->addItem($content)->show();
