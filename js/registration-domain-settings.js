/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function() {
	'use strict';

	var appName = 'registration';
	var domainInputSelector = 'input[placeholder="nextcloud.com;*.example.com"]';
	var groups = [];

	function translate(text) {
		if (typeof window.t === 'function') {
			return window.t(appName, text);
		}
		return text;
	}

	function loadInitialState(key, fallback) {
		var cacheKey = '#initial-state-' + appName + '-' + key;
		if (window._nc_initial_state && window._nc_initial_state.has(cacheKey)) {
			return window._nc_initial_state.get(cacheKey);
		}

		var input = document.querySelector(cacheKey);
		if (!input) {
			return fallback;
		}

		try {
			var state = JSON.parse(window.atob(input.value));
			if (!window._nc_initial_state) {
				window._nc_initial_state = new Map();
			}
			window._nc_initial_state.set(cacheKey, state);
			return state;
		} catch (error) {
			console.error('Could not load registration initial state', error);
			return fallback;
		}
	}

	function normalizeDomain(domain) {
		return domain.trim().toLowerCase();
	}

	function parseDomains(domains) {
		return domains
			.split(';')
			.map(function(domain) {
				return domain.trim();
			})
			.filter(function(domain) {
				return domain !== '';
			});
	}

	function getPayloadRows(rows) {
		var seen = {};
		var payloadRows = [];
		rows.forEach(function(row) {
			var domain = normalizeDomain(row.domain);
			if (domain === '' || seen[domain]) {
				return;
			}
			seen[domain] = true;
			payloadRows.push({
				domain: domain,
				group: row.group,
			});
		});
		return payloadRows;
	}

	function generateUrl(path) {
		if (window.OC && typeof window.OC.generateUrl === 'function') {
			return window.OC.generateUrl(path);
		}

		return (window._oc_webroot || '') + '/index.php' + path;
	}

	function generateOcsUrl(path, params) {
		var root = window._oc_webroot || '';
		var searchParams = new URLSearchParams(params || {});
		searchParams.set('format', 'json');
		return root + '/ocs/v2.php' + path + '?' + searchParams.toString();
	}

	function getRequestToken() {
		return window.OC && window.OC.requestToken ? window.OC.requestToken : '';
	}

	function mergeGroups(newGroups) {
		var knownGroups = {};
		groups.forEach(function(group) {
			knownGroups[group.id] = group;
		});
		newGroups.forEach(function(group) {
			if (group && group.id) {
				knownGroups[group.id] = group;
			}
		});
		groups = Object.keys(knownGroups).map(function(groupId) {
			return knownGroups[groupId];
		}).sort(function(a, b) {
			return String(a.displayname || a.id).localeCompare(String(b.displayname || b.id));
		});
	}

	function saveDomainGroups(rows) {
		var payloadRows = getPayloadRows(rows);
		var domainGroups = {};
		payloadRows.forEach(function(row) {
			if (row.group) {
				domainGroups[row.domain] = row.group;
			}
		});

		return window.fetch(generateUrl('/apps/registration/settings/domain-groups'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'requesttoken': getRequestToken(),
			},
			body: JSON.stringify({
				allowed_domains: payloadRows.map(function(row) {
					return row.domain;
				}).join(';'),
				domain_groups: JSON.stringify(domainGroups),
			}),
		}).then(function(response) {
			if (!response.ok) {
				throw new Error('Could not save registration domain groups');
			}
		}).catch(function(error) {
			console.error(error);
		});
	}

	function fetchGroups(render) {
		return window.fetch(generateOcsUrl('/cloud/groups/details', {
			search: '',
			limit: '200',
			offset: '0',
		}), {
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'OCS-APIRequest': 'true',
				'requesttoken': getRequestToken(),
			},
		}).then(function(response) {
			if (!response.ok) {
				throw new Error('Could not fetch groups');
			}
			return response.json();
		}).then(function(data) {
			mergeGroups((data.ocs && data.ocs.data && data.ocs.data.groups) || []);
			render();
		}).catch(function(error) {
			console.error(error);
		});
	}

	function createGroupSelect(row, rows, scheduleSave) {
		var select = document.createElement('select');
		select.className = 'domain-list__group-select';
		select.disabled = false;
		select.setAttribute('aria-label', translate('Domain group'));

		var emptyOption = document.createElement('option');
		emptyOption.value = '';
		emptyOption.textContent = translate('No group');
		select.appendChild(emptyOption);

		groups.forEach(function(group) {
			var option = document.createElement('option');
			option.value = group.id;
			option.textContent = group.displayname || group.id;
			option.selected = group.id === row.group;
			select.appendChild(option);
		});

		select.addEventListener('change', function() {
			row.group = select.value;
			scheduleSave(rows, true);
		});

		return select;
	}

	function enhance(domainInput) {
		if (document.querySelector('.domain-list--enhanced')) {
			return;
		}

		var originalField = domainInput.closest('.input-field') || domainInput.parentElement;
		if (!originalField || !originalField.parentElement) {
			return;
		}

		var domainGroups = loadInitialState('domain_groups', {});
		mergeGroups(Object.keys(domainGroups).map(function(domain) {
			return domainGroups[domain];
		}));

		var rows = parseDomains(domainInput.value).map(function(domain) {
			var normalizedDomain = normalizeDomain(domain);
			return {
				domain: domain,
				group: domainGroups[normalizedDomain] ? domainGroups[normalizedDomain].id : '',
			};
		});
		if (rows.length === 0) {
			rows.push({ domain: '', group: '' });
		}

		var container = document.createElement('div');
		container.className = 'domain-list domain-list--enhanced';
		originalField.insertAdjacentElement('afterend', container);
		originalField.style.display = 'none';

		var saveTimer = null;
		function syncOriginalInput() {
			domainInput.value = getPayloadRows(rows).map(function(row) {
				return row.domain;
			}).join(';');
			domainInput.dispatchEvent(new Event('input', { bubbles: true }));
		}

		function scheduleSave(currentRows, immediate) {
			syncOriginalInput();
			if (saveTimer) {
				window.clearTimeout(saveTimer);
			}
			saveTimer = window.setTimeout(function() {
				saveDomainGroups(currentRows);
			}, immediate ? 0 : 600);
		}

		function render() {
			container.replaceChildren();

			var header = document.createElement('div');
			header.className = 'domain-list__header';
			header.setAttribute('aria-hidden', 'true');
			var domainHeader = document.createElement('span');
			domainHeader.textContent = translate('Allowed email domains');
			var groupHeader = document.createElement('span');
			groupHeader.textContent = translate('Domain group');
			header.appendChild(domainHeader);
			header.appendChild(groupHeader);
			container.appendChild(header);

			rows.forEach(function(row, index) {
				var line = document.createElement('div');
				line.className = 'domain-list__row';

				var input = document.createElement('input');
				input.type = 'text';
				input.className = 'domain-list__domain-input';
				input.placeholder = 'nextcloud.com';
				input.value = row.domain;
				input.setAttribute('aria-label', translate('Allowed email domains'));
				input.addEventListener('input', function() {
					row.domain = input.value;
					scheduleSave(rows, false);
				});

				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'domain-list__remove';
				remove.disabled = rows.length === 1;
				remove.setAttribute('aria-label', translate('Remove domain'));
				remove.innerHTML = '<span aria-hidden="true">&times;</span>';
				remove.addEventListener('click', function() {
					rows.splice(index, 1);
					if (rows.length === 0) {
						rows.push({ domain: '', group: '' });
					}
					render();
					scheduleSave(rows, true);
				});

				line.appendChild(input);
				line.appendChild(createGroupSelect(row, rows, scheduleSave));
				line.appendChild(remove);
				container.appendChild(line);
			});

			var add = document.createElement('button');
			add.type = 'button';
			add.className = 'domain-list__add';
			add.innerHTML = '<span aria-hidden="true">+</span> ' + translate('Add domain');
			add.addEventListener('click', function() {
				rows.push({ domain: '', group: '' });
				render();
			});
			container.appendChild(add);
		}

		render();
		fetchGroups(render);
	}

	function init(tries) {
		var domainInput = document.querySelector(domainInputSelector);
		if (domainInput) {
			enhance(domainInput);
			return;
		}

		if (tries > 0) {
			window.setTimeout(function() {
				init(tries - 1);
			}, 250);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			init(40);
		});
	} else {
		init(40);
	}
})();
