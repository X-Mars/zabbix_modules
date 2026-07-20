<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This JavaScript handles the preview page interactions:
** - AJAX-based host import (one at a time for progress feedback)
** - Progress bar updates
** - Results table population
** - Download report (CSV)
*/
?>

(function($) {
	'use strict';

	$(function() {
		var config = window.clonehostsPreview || {};
		var hostData = config.hostData || [];
		var totalCount = config.totalCount || 0;
		var sourceHostid = config.sourceHostid || '';
		var ajaxUrl = config.ajaxUrl || 'zabbix.php?action=clonehosts.import';

		// ===== i18n =====
		var L = config.lang || {};

		function t(key) {
			return L[key] || key;
		}

		var successCount = 0;
	var failedCount = 0;
	var processedCount = 0;
	var results = [];
	var importing = false;
	var importQueue = []; // Only hosts selected for import

	// ===== Update import button label based on selected rows =====
	function updateImportButton() {
		var selectedCount = $('#preview-table input.row-select:checked').length;
		$('#start-import-btn').text(t('preview.import_selected').replace('{count}', selectedCount));
	}

	function updateSelectAllCheckbox() {
		var $readyBoxes = $('#preview-table input.row-select[data-ready="1"]');
		var totalReady = $readyBoxes.length;
		var checkedReady = $readyBoxes.filter(':checked').length;
		$('#preview-select-all').prop('checked', totalReady > 0 && totalReady === checkedReady);
	}

	// Select / deselect all ready rows
	$('#preview-select-all').on('change', function() {
		var checked = $(this).prop('checked');
		$('#preview-table input.row-select[data-ready="1"]').prop('checked', checked);
		updateImportButton();
	});

	// Re-count when a checkbox changes
	$(document).on('change', '#preview-table input.row-select', function() {
		updateSelectAllCheckbox();
		updateImportButton();
	});

	// Initialize select-all state
	updateSelectAllCheckbox();

	// ===== Start Import =====
	$('#start-import-btn').on('click', function() {
		if (importing) return;

		// Collect only checked & ready rows
		importQueue = [];
		$('#preview-table tbody tr').each(function() {
			var $cb = $(this).find('input.row-select');
			if ($cb.length && $cb.prop('checked') && $cb.data('ready') == 1) {
				var idx = parseInt($(this).data('index'), 10);
				if (!isNaN(idx) && hostData[idx]) {
					importQueue.push({ rowNum: idx + 1, host: hostData[idx] });
				}
			}
		});

		if (importQueue.length === 0) {
			alert(t('preview.no_selected'));
			return;
		}

		importing = true;
		$('#start-import-btn').prop('disabled', true).text(t('import.importing'));
		$('#import-progress-section').show();
		$('#import-results-section').hide();

		// Reset counters
		successCount = 0;
		failedCount = 0;
		processedCount = 0;
		results = [];
		$('#results-tbody').empty();
		$('#progress-bar').css('width', '0%');
		$('#progress-text').text('0 / ' + importQueue.length);
		$('#total-count').text(importQueue.length);

		// Start processing hosts sequentially
		processNextHost(0);
	});

	function processNextHost(queueIndex) {
		if (queueIndex >= importQueue.length) {
			// All done
			finishImport();
			return;
		}

		var item = importQueue[queueIndex];
		var rowNum = item.rowNum;
		var host = item.host;

		$.ajax({
			url: ajaxUrl,
			method: 'POST',
			data: {
				source_hostid: sourceHostid,
				host_data: host
			},
			dataType: 'json',
			success: function(resp) {
				processedCount++;

				if (resp.success) {
					successCount++;
					addResultRow(rowNum, 'success', host.host, host.ip, resp.hostid || '', '');
					results.push({
						row: rowNum,
						result: 'success',
						host: host.host,
						ip: host.ip,
						hostid: resp.hostid || '',
						error: ''
					});
				} else {
					failedCount++;
					var errorMsg = resp.error || t('import.unknown_error');
					addResultRow(rowNum, 'failed', host.host, host.ip, '', errorMsg);
					results.push({
						row: rowNum,
						result: 'failed',
						host: host.host,
						ip: host.ip,
						hostid: '',
						error: errorMsg
					});
				}

				updateProgress();
			},
			error: function(xhr, status, error) {
				processedCount++;
				failedCount++;
				var errorMsg = t('import.ajax_error') + (error || status || t('import.unknown_error'));
				addResultRow(rowNum, 'failed', host.host, host.ip, '', errorMsg);
				results.push({
					row: rowNum,
					result: 'failed',
					host: host.host,
					ip: host.ip,
					hostid: '',
					error: errorMsg
				});
				updateProgress();
			},
			complete: function() {
				// Process next host (small delay to allow UI update)
				setTimeout(function() {
					processNextHost(queueIndex + 1);
				}, 50);
			}
		});
	}

	function updateProgress() {
		var percent = Math.round((processedCount / importQueue.length) * 100);
		$('#progress-bar').css('width', percent + '%');
		$('#progress-text').text(processedCount + ' / ' + importQueue.length);
		$('#progress-success').text(successCount);
		$('#progress-failed').text(failedCount);
		$('#progress-pending').text(importQueue.length - processedCount);
		$('#success-count').text(successCount);
		$('#failed-count').text(failedCount);
	}

		function addResultRow(rowNum, result, hostName, ip, hostid, error) {
			var resultClass = result === 'success' ? 'result-success' : 'result-failed';
			var resultIcon = result === 'success'
				? '&#10003; ' + t('import.result_success')
				: '&#10007; ' + t('import.result_failed');

			var html = '<tr class="' + resultClass + '">' +
				'<td>' + rowNum + '</td>' +
				'<td>' + resultIcon + '</td>' +
				'<td>' + escapeHtml(hostName) + '</td>' +
				'<td>' + escapeHtml(ip) + '</td>' +
				'<td>' + (hostid ? escapeHtml(hostid) : '-') + '</td>' +
				'<td>' + (error ? '<span class="error-msg">' + escapeHtml(error) + '</span>' : '-') + '</td>' +
				'</tr>';

			$('#results-tbody').append(html);
		}

		function finishImport() {
			importing = false;
			$('#start-import-btn').text(t('import.complete'));
			$('#import-results-section').show();

			// Show/hide download report button
			if (results.length > 0) {
				$('#download-report-btn').show();
			}

			// Scroll to results
			$('html, body').animate({
				scrollTop: $('#import-results-section').offset().top - 50
			}, 500);
		}

		// ===== Download Report =====
		$('#download-report-btn').on('click', function() {
			var csv = t('report.col_row') + ',' + t('report.col_result') + ',' + t('report.col_host') + ',' + t('report.col_ip') + ',' + t('report.col_hostid') + ',' + t('report.col_error') + '\n';
			$.each(results, function(i, r) {
				csv += r.row + ',' +
					r.result + ',' +
					'"' + r.host.replace(/"/g, '""') + '",' +
					'"' + r.ip.replace(/"/g, '""') + '",' +
					(r.hostid || '') + ',' +
					'"' + (r.error || '').replace(/"/g, '""') + '"\n';
			});

			var blob = new Blob(['\ufeff' + csv], {type: 'text/csv;charset=utf-8;'});
			var link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = 'host_import_report_' + Date.now() + '.csv';
			link.click();
		});

		// ===== Utility =====
		function escapeHtml(str) {
			if (str === null || str === undefined) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}
	});

})(jQuery);
