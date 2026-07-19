<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This JavaScript handles the main page interactions:
** - Tab switching (file upload vs online table)
** - CSV file parsing (client-side)
** - Online table row management
** - Source host info loading (AJAX)
** - CSV template download
** - Form submission to preview page
*/
?>

(function($) {
	'use strict';

	$(function() {
		var data = window.clonehostsData || {};
		var sourceHostid = '';
		var parsedRows = [];

		// ===== i18n =====
		var L = data.lang || {};

		function t(key) {
			return L[key] || key;
		}

		// ===== CSV Column Definitions =====
		var CSV_COLUMNS = [
			'host',          // Host Name (*)
			'visible_name',  // Visible Name
			'ip',            // Interface IP (*)
			'port',          // Port
			'groups',        // Host Groups (semicolon-separated)
			'templates',     // Templates (semicolon-separated)
			'tags',          // Tags (tag=value;tag=value)
			'macros',        // Macros ({$MACRO}=value;{$MACRO}=value)
			'description'    // Description
		];

		function getCSVHeaders() {
			return [
				t('csv.header.host'),
				t('csv.header.visible_name'),
				t('csv.header.ip'),
				t('csv.header.port'),
				t('csv.header.groups'),
				t('csv.header.templates'),
				t('csv.header.tags'),
				t('csv.header.macros'),
				t('csv.header.description')
			];
		}

		// ===== Tab Switching =====
		$('.tab-btn').on('click', function() {
			var tab = $(this).data('tab');
			$('.tab-btn').removeClass('active');
			$(this).addClass('active');
			$('.tab-content').removeClass('active').hide();
			$('#tab-' + tab).addClass('active').show();
			updatePreviewButton();
		});

	// ===== Dual Multiselect: Host Group + Host (with linkage) =====
	var allHosts = data.hosts || [];
	var allGroups = data.hostGroups || [];
	var selectedGroups = []; // [{id, name}]
	var selectedHosts = [];  // [{id, name, host}]
	var msInputTimer = null;
	var msDropdownVisible = false;
	var msActiveItemIndex = -1;
	var msCurrentFilteredItems = [];
	var msActiveContext = null; // 'group' or 'host'

	// Popup dialog state
	var popupContext = null;     // 'group' or 'host'
	var popupSelected = {};      // id -> {id, name} (temp selections in popup)
	var popupFilteredItems = []; // current filtered list in popup

	function getHostLabel(host) {
		var label = host.name || '';
		if (host.host && host.host !== host.name) {
			label += ' (' + host.host + ')';
		}
		return label;
	}

	function fuzzyMatch(text, keyword) {
		if (!keyword) return true;
		text = (text || '').toLowerCase();
		keyword = keyword.toLowerCase().trim();
		return text.indexOf(keyword) >= 0;
	}

	// ===== Get hosts filtered by selected groups =====
	function getHostsFilteredByGroups() {
		if (selectedGroups.length === 0) {
			return allHosts.slice();
		}
		var groupIds = selectedGroups.map(function(g) { return g.id; });
		return allHosts.filter(function(h) {
			if (!h.groupids || h.groupids.length === 0) return false;
			for (var i = 0; i < h.groupids.length; i++) {
				if (groupIds.indexOf(h.groupids[i]) >= 0) return true;
			}
			return false;
		});
	}

	// ===== Render Selected Tags =====
	function renderGroupTags() {
		var $container = $('#group-ms-selected');
		$container.empty();
		$.each(selectedGroups, function(i, g) {
			var html = '<span class="ms-tag" data-type="group" data-id="' + escapeHtml(g.id) + '">' +
				'<span class="ms-tag-icon">G</span>' +
				'<span class="ms-tag-name">' + escapeHtml(g.name) + '</span>' +
				'<span class="ms-tag-remove" title="' + t('search.remove') + '">&times;</span>' +
				'</span>';
			$container.append(html);
		});
	}

	function renderHostTags() {
		var $container = $('#host-ms-selected');
		$container.empty();
		$.each(selectedHosts, function(i, h) {
			var label = h.name;
			if (h.host && h.host !== h.name) {
				label = h.name + ' (' + h.host + ')';
			}
			var html = '<span class="ms-tag" data-type="host" data-id="' + escapeHtml(h.id) + '">' +
				'<span class="ms-tag-icon">H</span>' +
				'<span class="ms-tag-name">' + escapeHtml(label) + '</span>' +
				'<span class="ms-tag-remove" title="' + t('search.remove') + '">&times;</span>' +
				'</span>';
			$container.append(html);
		});
	}

	// ===== Add/Remove Tags =====
	function addGroup(id, name) {
		id = String(id);
		for (var i = 0; i < selectedGroups.length; i++) {
			if (String(selectedGroups[i].id) === id) return;
		}
		selectedGroups.push({ id: id, name: name });
		renderGroupTags();
		pruneHostsByGroups();
		renderHostTags();
		applyFilters();
	}

	function addHost(id, name, host) {
		id = String(id);
		selectedHosts = [{ id: id, name: name, host: host || '' }];
		renderHostTags();
		applyFilters();
	}

	function pruneHostsByGroups() {
		if (selectedGroups.length === 0) return;
		var filteredHosts = getHostsFilteredByGroups();
		var validIds = filteredHosts.map(function(h) { return h.hostid; });
		selectedHosts = selectedHosts.filter(function(h) {
			return validIds.indexOf(h.id) >= 0;
		});
	}

	function removeGroup(id) {
		id = String(id);
		selectedGroups = selectedGroups.filter(function(g) { return String(g.id) !== id; });
		renderGroupTags();
		pruneHostsByGroups();
		renderHostTags();
		applyFilters();
	}

	function removeHost(id) {
		id = String(id);
		selectedHosts = selectedHosts.filter(function(h) { return String(h.id) !== id; });
		renderHostTags();
		applyFilters();
	}

	// ===== Inline Dropdown for Group Search =====
	function renderGroupDropdown(keyword) {
		var $dd = $('#group-ms-dropdown');
		keyword = (keyword || '').toLowerCase().trim();

		var matched = allGroups.filter(function(g) {
			if (!fuzzyMatch(g.name, keyword)) return false;
			for (var i = 0; i < selectedGroups.length; i++) {
				if (selectedGroups[i].id === g.groupid) return false;
			}
			return true;
		});

		msCurrentFilteredItems = [];
		$dd.empty();

		if (matched.length === 0) {
			$dd.append('<div class="ms-dropdown-no-match">' + t('search.no_match') + '</div>');
		} else {
			$.each(matched, function(i, g) {
				var $item = $('<div class="ms-dropdown-item"></div>')
					.data('id', g.groupid)
					.data('name', g.name);
				$item.append('<span class="ms-item-icon group-icon"></span>');
				$item.append('<span class="ms-item-name">' + escapeHtml(g.name) + '</span>');
				$dd.append($item);
				msCurrentFilteredItems.push($item[0]);
			});
		}

		$dd.show();
		msDropdownVisible = true;
		msActiveItemIndex = -1;
	}

	// ===== Inline Dropdown for Host Search =====
	function renderHostDropdown(keyword) {
		var $dd = $('#host-ms-dropdown');
		keyword = (keyword || '').toLowerCase().trim();

		var availableHosts = getHostsFilteredByGroups();

		var matched = availableHosts.filter(function(h) {
			var label = getHostLabel(h);
			if (!fuzzyMatch(label, keyword) && !fuzzyMatch(h.host, keyword)) return false;
			for (var i = 0; i < selectedHosts.length; i++) {
				if (selectedHosts[i].id === h.hostid) return false;
			}
			return true;
		});

		msCurrentFilteredItems = [];
		$dd.empty();

		if (matched.length === 0) {
			$dd.append('<div class="ms-dropdown-no-match">' + t('search.no_match') + '</div>');
		} else {
			$.each(matched, function(i, h) {
				var label = getHostLabel(h);
				var $item = $('<div class="ms-dropdown-item"></div>')
					.data('id', h.hostid)
					.data('name', h.name)
					.data('host', h.host);
				$item.append('<span class="ms-item-icon host-icon"></span>');
				$item.append('<span class="ms-item-name">' + escapeHtml(label) + '</span>');
				$dd.append($item);
				msCurrentFilteredItems.push($item[0]);
			});
		}

		$dd.show();
		msDropdownVisible = true;
		msActiveItemIndex = -1;
	}

	function hideMsDropdown() {
		$('.multiselect-dropdown').hide();
		msDropdownVisible = false;
		msActiveItemIndex = -1;
	}

	function setMsActiveItem(index) {
		$(msCurrentFilteredItems).removeClass('active');
		if (index >= 0 && index < msCurrentFilteredItems.length) {
			msActiveItemIndex = index;
			$(msCurrentFilteredItems[msActiveItemIndex]).addClass('active');
			msCurrentFilteredItems[msActiveItemIndex].scrollIntoView({ block: 'nearest' });
		} else {
			msActiveItemIndex = -1;
		}
	}

	// ===== Keyboard Navigation for Multiselect Inputs =====
	function bindMsKeyboardNavigation($input, context) {
		$input.on('keydown', function(e) {
			if (!msDropdownVisible || msActiveContext !== context) {
				if (e.which === 40) { // down arrow opens dropdown
					if (context === 'group') renderGroupDropdown($(this).val());
					else renderHostDropdown($(this).val());
					msActiveContext = context;
					e.preventDefault();
				}
				return;
			}

			if (e.which === 40) { // down
				e.preventDefault();
				var next = msActiveItemIndex + 1;
				if (next >= msCurrentFilteredItems.length) next = 0;
				setMsActiveItem(next);
			} else if (e.which === 38) { // up
				e.preventDefault();
				var prev = msActiveItemIndex - 1;
				if (prev < 0) prev = msCurrentFilteredItems.length - 1;
				setMsActiveItem(prev);
			} else if (e.which === 13) { // enter
				e.preventDefault();
				if (msActiveItemIndex >= 0 && msActiveItemIndex < msCurrentFilteredItems.length) {
					var $item = $(msCurrentFilteredItems[msActiveItemIndex]);
					if (context === 'group') {
						addGroup($item.data('id'), $item.data('name'));
						$('#group-ms-input').val('');
					} else {
						addHost($item.data('id'), $item.data('name'), $item.data('host'));
						$('#host-ms-input').val('');
					}
					hideMsDropdown();
				}
			} else if (e.which === 27) { // escape
				e.preventDefault();
				hideMsDropdown();
			}
		});
	}

	bindMsKeyboardNavigation($('#group-ms-input'), 'group');
	bindMsKeyboardNavigation($('#host-ms-input'), 'host');

	// ===== Group Input Events =====
	$('#group-ms-input').on('focus', function() {
		if (!msDropdownVisible || msActiveContext !== 'group') {
			renderGroupDropdown($(this).val());
			msActiveContext = 'group';
		}
	});

	$('#group-ms-input').on('input', function() {
		clearTimeout(msInputTimer);
		var val = $(this).val();
		msInputTimer = setTimeout(function() {
			renderGroupDropdown(val);
			msActiveContext = 'group';
		}, 150);
	});

	// ===== Host Input Events =====
	$('#host-ms-input').on('focus', function() {
		if (!msDropdownVisible || msActiveContext !== 'host') {
			renderHostDropdown($(this).val());
			msActiveContext = 'host';
		}
	});

	$('#host-ms-input').on('input', function() {
		clearTimeout(msInputTimer);
		var val = $(this).val();
		msInputTimer = setTimeout(function() {
			renderHostDropdown(val);
			msActiveContext = 'host';
		}, 150);
	});

	// ===== Dropdown Item Click =====
	$(document).on('mousedown', '#group-ms-dropdown .ms-dropdown-item', function(e) {
		e.preventDefault();
		var $item = $(this);
		addGroup($item.data('id'), $item.data('name'));
		$('#group-ms-input').val('').focus();
		hideMsDropdown();
	});

	$(document).on('mousedown', '#host-ms-dropdown .ms-dropdown-item', function(e) {
		e.preventDefault();
		var $item = $(this);
		addHost($item.data('id'), $item.data('name'), $item.data('host'));
		$('#host-ms-input').val('').focus();
		hideMsDropdown();
	});

	// ===== Remove Tag =====
	$(document).on('click', '.ms-tag-remove', function(e) {
		e.stopPropagation();
		var $tag = $(this).closest('.ms-tag');
		var id = $tag.data('id');
		var type = $tag.data('type');
		if (type === 'group') removeGroup(id);
		else removeHost(id);
	});

	// ===== Click Outside to Close Dropdown =====
	$(document).on('click', function(e) {
		var $target = $(e.target);
		if (!$target.closest('.multiselect-wrapper').length) {
			hideMsDropdown();
		}
	});

	// ===== Popup Dialog for "Select" Button =====
	function openPopup(context) {
		popupContext = context;
		popupSelected = {};

		if (context === 'group') {
			$.each(selectedGroups, function(i, g) { popupSelected[String(g.id)] = { id: String(g.id), name: g.name }; });
			$('#ms-popup-title').text(t('multiselect.popup_title_group'));
		} else {
			$.each(selectedHosts, function(i, h) { popupSelected[String(h.id)] = { id: String(h.id), name: h.name, host: h.host || '' }; });
			$('#ms-popup-title').text(t('multiselect.popup_title_host'));
		}

		$('#ms-popup-search-input').val('');
		$('#ms-popup-select-all').prop('checked', false);
		renderPopupList('');
		$('#ms-popup-overlay').show();
		$('#ms-popup-search-input').focus();
	}

	function renderPopupList(keyword) {
		keyword = (keyword || '').toLowerCase().trim();
		var $tbody = $('#ms-popup-tbody');
		$tbody.empty();
		popupFilteredItems = [];

		var items;
		if (popupContext === 'group') {
			items = allGroups.map(function(g) { return { id: g.groupid, name: g.name, host: '' }; });
		} else {
			items = getHostsFilteredByGroups().map(function(h) {
				return { id: h.hostid, name: h.name, host: h.host || '' };
			});
		}

		var matched = items.filter(function(item) {
			var label = item.name;
			if (item.host && item.host !== item.name) label += ' (' + item.host + ')';
			return fuzzyMatch(label, keyword) || fuzzyMatch(item.host, keyword);
		});

		if (matched.length === 0) {
			$tbody.append('<tr><td colspan="2" class="ms-popup-no-match">' + t('search.no_match') + '</td></tr>');
			updatePopupCount();
			return;
		}

		$.each(matched, function(i, item) {
			var isChecked = popupSelected[String(item.id)] !== undefined;
			var label = item.name;
			if (item.host && item.host !== item.name) label += ' (' + item.host + ')';
			var $tr = $('<tr></tr>')
				.data('id', item.id)
				.data('name', item.name)
				.data('host', item.host);
			$tr.append('<td class="ms-popup-check-col"><input type="checkbox" class="ms-popup-checkbox"' + (isChecked ? ' checked' : '') + ' /></td>');
			$tr.append('<td class="ms-popup-name-col">' + escapeHtml(label) + '</td>');
			$tbody.append($tr);
			popupFilteredItems.push(item);
		});

		var allChecked = matched.length > 0 && matched.every(function(item) { return popupSelected[String(item.id)] !== undefined; });
		$('#ms-popup-select-all').prop('checked', allChecked);
		updatePopupCount();
	}

	function updatePopupCount() {
		var count = Object.keys(popupSelected).length;
		$('#ms-popup-count').text(t('multiselect.popup_count').replace('{count}', count));
	}

	// Popup "Select" button click
	$('#group-ms-btn').on('click', function() { openPopup('group'); });
	$('#host-ms-btn').on('click', function() { openPopup('host'); });

	// Popup close
	$('#ms-popup-close').on('click', closePopup);
	$('#ms-popup-cancel').on('click', closePopup);
	$('#ms-popup-overlay').on('click', function(e) {
		if (e.target === this) closePopup();
	});

	function closePopup() {
		$('#ms-popup-overlay').hide();
		popupContext = null;
		popupSelected = {};
	}

	// Popup search
	$('#ms-popup-search-input').on('input', function() {
		renderPopupList($(this).val());
	});

	// Popup checkbox toggle (row click)
	$(document).on('click', '#ms-popup-tbody tr', function(e) {
		if ($(e.target).is('input[type="checkbox"]')) return;
		var $cb = $(this).find('.ms-popup-checkbox');
		if (popupContext === 'host') {
			$('.ms-popup-checkbox').prop('checked', false);
			$cb.prop('checked', true);
			popupSelected = {};
			var $tr = $(this);
			var id = String($tr.data('id'));
			popupSelected[id] = { id: id, name: $tr.data('name'), host: $tr.data('host') };
			updatePopupCount();
			$('#ms-popup-select-all').prop('checked', false);
			applyPopupSelection();
			closePopup();
		} else {
			$cb.prop('checked', !$cb.prop('checked'));
			$cb.trigger('change');
			applyPopupSelection();
			closePopup();
		}
	});

	$(document).on('change', '.ms-popup-checkbox', function() {
		var $tr = $(this).closest('tr');
		var id = String($tr.data('id'));
		var name = $tr.data('name');
		var host = $tr.data('host');
		if ($(this).prop('checked')) {
			popupSelected[id] = { id: id, name: name, host: host };
		} else {
			delete popupSelected[id];
		}
		updatePopupCount();
		var allChecked = popupFilteredItems.length > 0 && popupFilteredItems.every(function(item) { return popupSelected[String(item.id)] !== undefined; });
		$('#ms-popup-select-all').prop('checked', allChecked);
	});

	// Popup select-all
	$('#ms-popup-select-all').on('change', function() {
		var checked = $(this).prop('checked');
		$.each(popupFilteredItems, function(i, item) {
			var sid = String(item.id);
			if (checked) {
				popupSelected[sid] = { id: sid, name: item.name, host: item.host };
			} else {
				delete popupSelected[sid];
			}
		});
		$('.ms-popup-checkbox').prop('checked', checked);
		updatePopupCount();
	});

	function applyPopupSelection() {
		if (popupContext === 'group') {
			selectedGroups = Object.values(popupSelected).map(function(g) {
				return { id: String(g.id), name: g.name };
			}).sort(function(a, b) { return a.name.localeCompare(b.name); });
			renderGroupTags();
			pruneHostsByGroups();
			renderHostTags();
		} else {
			selectedHosts = Object.values(popupSelected).map(function(h) {
				return { id: String(h.id), name: h.name, host: h.host || '' };
			}).sort(function(a, b) { return a.name.localeCompare(b.name); });
			renderHostTags();
		}
		applyFilters();
	}

	$('#ms-popup-apply').on('click', function() {
		applyPopupSelection();
		closePopup();
	});

	// ===== Filter Source Host List =====
	function applyFilters() {
		if (selectedHosts.length > 0) {
			var firstHost = selectedHosts[0];
			sourceHostid = firstHost.id;
			$('#hidden-source-hostid').val(sourceHostid);
			loadSourceHostInfo(sourceHostid);
		} else {
			sourceHostid = '';
			$('#hidden-source-hostid').val('');
			$('#source-host-info').hide().empty();
		}
		updatePreviewButton();
	}

	// ===== Source Host Info Loading =====
	function loadSourceHostInfo(hostid) {
		if (!hostid) return;

		$('#source-host-info').show().html('<div class="loading-msg">' + t('step1.loading') + '</div>');

		$.ajax({
			url: 'zabbix.php?action=clonehosts.source',
			method: 'POST',
			data: {
				source_hostid: hostid
			},
			dataType: 'json',
			success: function(resp) {
				if (resp.success && resp.source_host) {
					displaySourceHostInfo(resp.source_host);
				} else {
					$('#source-host-info').show().html(
						'<div class="error-msg">' + (resp.error || t('step1.load_failed')) + '</div>'
					);
				}
			},
			error: function() {
				$('#source-host-info').show().html(
					'<div class="error-msg">' + t('step1.load_failed_network') + '</div>'
				);
			}
		});
	}

		function displaySourceHostInfo(host) {
			var typeNames = {1: t('iface.Agent'), 2: t('iface.SNMP'), 3: t('iface.IPMI'), 4: t('iface.JMX')};
			var html = '<h4>' + t('step1.source_host_label') + escapeHtml(host.name) + '</h4>';
			html += '<table class="list-table source-info-table">';

			// Interfaces
			html += '<tr><th>' + t('preview.interfaces') + '</th><td>';
			if (host.interfaces && host.interfaces.length > 0) {
				var ifaces = [];
				$.each(host.interfaces, function(i, iface) {
					var typeName = typeNames[iface.type] || (t('iface.Type') + iface.type);
					var addr = iface.useip == 1 ? iface.ip : iface.dns;
					ifaces.push(typeName + ': ' + addr + ':' + iface.port);
				});
				html += ifaces.join('<br/>');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Host Groups
			html += '<tr><th>' + t('col.host_groups') + '</th><td>';
			if (host.hostgroups && host.hostgroups.length > 0) {
				html += $.map(host.hostgroups, function(g) { return escapeHtml(g.name); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Templates
			html += '<tr><th>' + t('col.templates') + '</th><td>';
			if (host.parenttemplates && host.parenttemplates.length > 0) {
				html += $.map(host.parenttemplates, function(tp) { return escapeHtml(tp.name); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Tags
			html += '<tr><th>' + t('col.tags') + '</th><td>';
			if (host.tags && host.tags.length > 0) {
				html += $.map(host.tags, function(tp) { return escapeHtml(tp.tag + '=' + tp.value); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Macros
			html += '<tr><th>' + t('col.macros') + '</th><td>';
			if (host.macros && host.macros.length > 0) {
				html += $.map(host.macros, function(m) { return escapeHtml(m.macro + '=' + m.value); }).join(', ');
			} else {
				html += t('preview.none');
			}
			html += '</td></tr>';

			// Description
			html += '<tr><th>' + t('col.description') + '</th><td>' + escapeHtml(host.description || '') + '</td></tr>';

			html += '</table>';
			html += '<p class="inherited-note">' + t('step1.inherited_note') + '</p>';

			$('#source-host-info').show().html(html);
		}

		// ===== CSV File Upload =====
		$('#csv-file-input').on('change', function() {
			var file = this.files[0];
			$('#parse-csv-btn').prop('disabled', !file);
		});

		$('#parse-csv-btn').on('click', function() {
			var file = $('#csv-file-input')[0].files[0];
			if (!file) return;

			var encoding = $('#csv-encoding-select').val() || 'UTF-8';
			var reader = new FileReader();
			reader.onload = function(e) {
				var text = e.target.result;
				// Strip UTF-8 BOM if present
				if (text.charCodeAt(0) === 0xFEFF) {
					text = text.substring(1);
				}
				parsedRows = parseCSV(text);

				var resultDiv = $('#csv-parse-result');
				if (parsedRows.length === 0) {
					resultDiv.show().html('<div class="error-msg">' + t('csv.no_data') + '</div>');
					return;
				}

				// Validate rows
				var validCount = 0;
				var invalidCount = 0;
				$.each(parsedRows, function(i, row) {
					if (row.host && row.ip) {
						validCount++;
					} else {
						invalidCount++;
						row._invalid = true;
					}
				});

				var html = '<div class="csv-parse-summary">';
				html += '<span class="summary-item">' + t('csv.parsed_rows') + parsedRows.length + '</span>';
				html += '<span class="summary-item summary-ok">' + t('csv.valid_rows') + validCount + '</span>';
				if (invalidCount > 0) {
					html += '<span class="summary-item summary-conflict">' + t('csv.invalid_rows') + invalidCount + '</span>';
				}
				html += '</div>';

				// Show parsed data preview (first 10 rows)
				var csvHeaders = getCSVHeaders();
				html += '<div class="table-wrapper"><table class="list-table csv-preview-table"><thead><tr>';
				$.each(csvHeaders, function(i, h) {
					html += '<th>' + h + '</th>';
				});
				html += '</tr></thead><tbody>';
				var previewCount = Math.min(parsedRows.length, 10);
				for (var i = 0; i < previewCount; i++) {
					var row = parsedRows[i];
					var cls = row._invalid ? 'row-conflict' : 'row-ok';
					html += '<tr class="' + cls + '">';
					html += '<td>' + escapeHtml(row.host || '') + '</td>';
					html += '<td>' + escapeHtml(row.visible_name || '') + '</td>';
					html += '<td>' + escapeHtml(row.ip || '') + '</td>';
					html += '<td>' + escapeHtml(row.port || '') + '</td>';
					html += '<td>' + escapeHtml(row.groups || '') + '</td>';
					html += '<td>' + escapeHtml(row.templates || '') + '</td>';
					html += '<td>' + escapeHtml(row.tags || '') + '</td>';
					html += '<td>' + escapeHtml(row.macros || '') + '</td>';
					html += '<td>' + escapeHtml(row.description || '') + '</td>';
					html += '</tr>';
				}
				if (parsedRows.length > 10) {
					html += '<tr><td colspan="9" class="more-rows">' + t('csv.more_rows') + (parsedRows.length - 10) + t('csv.more_rows_suffix') + '</td></tr>';
				}
				html += '</tbody></table></div>';

				// Also populate the online table with parsed data
				populateTableFromRows(parsedRows);

				resultDiv.show().html(html);
				updatePreviewButton();
			};
			reader.readAsText(file, encoding);
		});

		// ===== CSV Parsing =====
		function parseCSV(text) {
			var rows = [];
			var lines = text.split(/\r\n|\r|\n/);
			var startIdx = 0;

			// Detect and skip header row
			if (lines.length > 0) {
				var firstLine = lines[0].toLowerCase().replace(/\s/g, '');
				if (firstLine.indexOf('hostname') >= 0 || firstLine.indexOf('host_name') >= 0 ||
					firstLine.indexOf('host') >= 0 || firstLine.indexOf('主机名称') >= 0) {
					startIdx = 1;
				}
			}

			for (var i = startIdx; i < lines.length; i++) {
				var line = lines[i].trim();
				if (line === '') continue;

				var fields = parseCSVLine(line);
				if (fields.length === 0) continue;

				// Pad fields to match column count
				while (fields.length < CSV_COLUMNS.length) {
					fields.push('');
				}

				var row = {};
				for (var j = 0; j < CSV_COLUMNS.length; j++) {
					row[CSV_COLUMNS[j]] = (fields[j] || '').trim();
				}

				// Skip completely empty rows
				var hasData = false;
				for (var key in row) {
					if (row[key] !== '') { hasData = true; break; }
				}
				if (hasData) {
					rows.push(row);
				}
			}

			return rows;
		}

		function parseCSVLine(line) {
			var fields = [];
			var current = '';
			var inQuotes = false;

			for (var i = 0; i < line.length; i++) {
				var char = line[i];

				if (char === '"') {
					if (inQuotes && i + 1 < line.length && line[i + 1] === '"') {
						// Escaped quote
						current += '"';
						i++;
					} else {
						inQuotes = !inQuotes;
					}
				} else if (char === ',' && !inQuotes) {
					fields.push(current);
					current = '';
				} else {
					current += char;
				}
			}

			fields.push(current);
			return fields;
		}

		// ===== CSV Template Download =====
		$('#download-template-btn').on('click', function() {
			var csvHeaders = getCSVHeaders();
			var csv = csvHeaders.join(',') + '\n';
			// Add example rows
			csv += 'web-server-01,Web Server 01,192.168.1.10,10050,Servers;Web Servers,Linux by Zabbix Agent;Nginx by HTTP,env=prod;os=linux,{$SNMP_COMMUNITY}=public,Web server 01\n';
			csv += 'db-server-01,DB Server 01,192.168.1.20,10050,Servers;Database Servers,MySQL by Zabbix Agent,env=prod;os=linux;role=db,{$MYSQL_PORT}=3306,Database server 01\n';

			var blob = new Blob(['\ufeff' + csv], {type: 'text/csv;charset=utf-8;'});
			var link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = 'clonehosts_template.csv';
			link.click();
		});

		// ===== Online Table Management =====
		$('#add-row-btn').on('click', function() {
			addTableRow();
		});

		$('#remove-row-btn').on('click', function() {
			$('#host-data-tbody input.row-checkbox:checked').each(function() {
				$(this).closest('tr').remove();
			});
			updateRowCount();
			updatePreviewButton();
		});

		$('#clear-table-btn').on('click', function() {
			if (confirm(t('table.clear_confirm'))) {
				$('#host-data-tbody').empty();
				addTableRow();
				updateRowCount();
				updatePreviewButton();
			}
		});

		$('#select-all-rows').on('change', function() {
			var checked = $(this).prop('checked');
			$('#host-data-tbody input.row-checkbox').prop('checked', checked);
		});

		function addTableRow(rowData) {
			rowData = rowData || {};
			var phRequired = t('table.placeholder.required');
			var phInherited = t('table.placeholder.inherited');
			var row = '<tr class="data-row">' +
				'<td class="col-checkbox"><input type="checkbox" class="row-checkbox" /></td>' +
				'<td><input type="text" class="input-host" value="' + escapeHtml(rowData.host || '') + '" placeholder="' + phRequired + '" /></td>' +
				'<td><input type="text" class="input-visible-name" value="' + escapeHtml(rowData.visible_name || '') + '" /></td>' +
				'<td><input type="text" class="input-ip" value="' + escapeHtml(rowData.ip || '') + '" placeholder="' + phRequired + '" /></td>' +
				'<td><input type="text" class="input-port" value="' + escapeHtml(rowData.port || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-groups" value="' + escapeHtml(rowData.groups || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-templates" value="' + escapeHtml(rowData.templates || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-tags" value="' + escapeHtml(rowData.tags || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-macros" value="' + escapeHtml(rowData.macros || '') + '" placeholder="' + phInherited + '" /></td>' +
				'<td><input type="text" class="input-description" value="' + escapeHtml(rowData.description || '') + '" placeholder="' + phInherited + '" /></td>' +
				'</tr>';
			$('#host-data-tbody').append(row);
			updateRowCount();
			updatePreviewButton();
		}

		function populateTableFromRows(rows) {
			$('#host-data-tbody').empty();
			$.each(rows, function(i, row) {
				addTableRow(row);
			});
			if (rows.length === 0) {
				addTableRow();
			}
		}

		function updateRowCount() {
			$('#row-count').text($('#host-data-tbody tr').length);
		}

		// ===== Preview Button Management =====
		function updatePreviewButton() {
			var hasSource = !!sourceHostid;
			var hasData = false;

			// Check if there's data in the table
			$('#host-data-tbody tr').each(function() {
				var host = $(this).find('.input-host').val();
				var ip = $(this).find('.input-ip').val();
				if (host && host.trim() && ip && ip.trim()) {
					hasData = true;
					return false; // break
				}
			});

			// Also check parsed CSV rows
			if (!hasData && parsedRows.length > 0) {
				$.each(parsedRows, function(i, row) {
					if (row.host && row.ip) {
						hasData = true;
						return false; // break
					}
				});
			}

			$('#preview-btn').prop('disabled', !(hasSource && hasData));

			if (!hasSource) {
				$('.preview-hint').text(t('preview.hint_no_source'));
			} else if (!hasData) {
				$('.preview-hint').text(t('preview.hint_no_data'));
			} else {
				$('.preview-hint').text(t('preview.hint_ready'));
			}
		}

		// Live validation
		$(document).on('input', '#host-data-tbody input', function() {
			updatePreviewButton();
		});

		// ===== Form Submission to Preview =====
		$('#preview-btn').on('click', function() {
			var hostData = collectHostData();
			if (hostData.length === 0) {
				alert(t('preview.alert_no_data'));
				return;
			}

			$('#hidden-source-hostid').val(sourceHostid);
			$('#hidden-host-data').val(JSON.stringify(hostData));
			$('#preview-form').submit();
		});

		function collectHostData() {
			var rows = [];
			$('#host-data-tbody tr').each(function() {
				var host = $(this).find('.input-host').val();
				var ip = $(this).find('.input-ip').val();

				if (!host || !ip) return; // skip
				host = host.trim();
				ip = ip.trim();
				if (!host || !ip) return; // skip

				rows.push({
					host: host,
					visible_name: ($(this).find('.input-visible-name').val() || '').trim(),
					ip: ip,
					port: ($(this).find('.input-port').val() || '').trim(),
					groups: ($(this).find('.input-groups').val() || '').trim(),
					templates: ($(this).find('.input-templates').val() || '').trim(),
					tags: ($(this).find('.input-tags').val() || '').trim(),
					macros: ($(this).find('.input-macros').val() || '').trim(),
					description: ($(this).find('.input-description').val() || '').trim()
				});
			});
			return rows;
		}

		// ===== Utility Functions =====
		function escapeHtml(str) {
			if (str === null || str === undefined) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		// ===== Initialize =====
	// Handle return-from-preview: restore source host selection + table data.
	var returnHostData = data.returnHostData || [];
	var returnSourceHostid = data.returnSourceHostid || '';

	if (returnSourceHostid && returnHostData.length > 0) {
		// Find the source host in the allHosts list to restore its tag.
		for (var i = 0; i < allHosts.length; i++) {
			if (String(allHosts[i].hostid) === String(returnSourceHostid)) {
				selectedHosts = [{
					id: String(allHosts[i].hostid),
					name: allHosts[i].name,
					host: allHosts[i].host || ''
				}];
				renderHostTags();
				break;
			}
		}

		// Switch to the online table tab and populate rows.
		$('.tab-btn').removeClass('active');
		$('.tab-btn[data-tab="table"]').addClass('active');
		$('.tab-content').removeClass('active').hide();
		$('#tab-table').addClass('active').show();

		populateTableFromRows(returnHostData);
	}

	// Populate source host list (all hosts initially)
	applyFilters();

	// Start with one empty row (only if table body is empty and no return data)
	if ($('#host-data-tbody tr').length === 0) {
		addTableRow();
	}
	updatePreviewButton();
});

})(jQuery);
