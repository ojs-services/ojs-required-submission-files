/**
 * @file plugins/generic/requiredFiles/js/requiredFiles.js
 *
 * Copyright (c) 2026 OJS Services
 *
 * Required Submission Files - client-side upload handler.
 */
(function() {
	'use strict';

	var RequiredFiles = {
		apiUrl: '',
		submissionId: null,
		requiredGenres: [],
		existingFiles: [],
		labels: {},
		_fileListObserver: null,

		init: function() {
			var section = document.getElementById('rf-required-section');
			if (!section) return;

			this.apiUrl = section.getAttribute('data-api-url');
			this.submissionId = parseInt(section.getAttribute('data-submission-id'), 10);
			this.requiredGenres = this._parseJson(section.getAttribute('data-required-genres'));
			this.existingFiles = this._parseJson(section.getAttribute('data-existing-files'));

			this.labels = {
				uploaded: section.getAttribute('data-label-uploaded') || 'Uploaded',
				notUploaded: section.getAttribute('data-label-not-uploaded') || 'Not uploaded',
				replace: section.getAttribute('data-label-replace') || 'Replace',
				uploadError: section.getAttribute('data-label-upload-error') || 'Upload failed'
			};

			this._populateExistingFiles();
			this._bindEvents();
			this._watchOjsFileList();
		},

		_populateExistingFiles: function() {
			var slots = document.querySelectorAll('.rf-genre-slot');
			for (var i = 0; i < slots.length; i++) {
				var genreId = parseInt(slots[i].getAttribute('data-genre-id'), 10);
				var matchingFiles = [];
				for (var j = 0; j < this.existingFiles.length; j++) {
					if (this.existingFiles[j].genreId === genreId) {
						matchingFiles.push(this.existingFiles[j]);
					}
				}
				if (matchingFiles.length > 0) {
					this._markSlotAsUploaded(slots[i], matchingFiles[0].name);
				}
			}
		},

		_bindEvents: function() {
			var self = this;
			var fileFields = document.querySelectorAll('.rf-file-field');
			for (var i = 0; i < fileFields.length; i++) {
				(function(input) {
					input.addEventListener('change', function() {
						if (input.files && input.files.length > 0) {
							var slot = input.closest('.rf-genre-slot');
							self._uploadFile(slot);
						}
					});
				})(fileFields[i]);
			}
		},

		_uploadFile: function(slot) {
			var genreId = parseInt(slot.getAttribute('data-genre-id'), 10);
			var fileInput = slot.querySelector('.rf-file-field');
			if (!fileInput.files || !fileInput.files.length) return;

			var file = fileInput.files[0];
			var formData = new FormData();
			formData.append('file', file);
			formData.append('fileStage', 2);
			formData.append('genreId', genreId);

			var progressDiv = slot.querySelector('.rf-genre-progress');
			var uploadDiv = slot.querySelector('.rf-genre-upload');
			var errorDiv = slot.querySelector('.rf-genre-error');
			uploadDiv.style.display = 'none';
			progressDiv.style.display = 'flex';
			errorDiv.style.display = 'none';

			var xhr = new XMLHttpRequest();
			var self = this;
			var fileName = file.name;

			xhr.upload.addEventListener('progress', function(e) {
				if (e.lengthComputable) {
					var pct = Math.round((e.loaded / e.total) * 100);
					var fill = slot.querySelector('.rf-progress-fill');
					var text = slot.querySelector('.rf-progress-text');
					if (fill) fill.style.width = pct + '%';
					if (text) text.textContent = pct + '%';
				}
			});

			xhr.addEventListener('load', function() {
				progressDiv.style.display = 'none';
				if (xhr.status >= 200 && xhr.status < 300) {
					self._markSlotAsUploaded(slot, fileName);
					self._syncWithVueComponent();
				} else {
					self._showSlotError(slot, self.labels.uploadError + ' (' + xhr.status + ')');
					uploadDiv.style.display = 'flex';
				}
			});

			xhr.addEventListener('error', function() {
				progressDiv.style.display = 'none';
				uploadDiv.style.display = 'flex';
				self._showSlotError(slot, self.labels.uploadError);
			});

			xhr.open('POST', this.apiUrl, true);

			if (window.pkp && window.pkp.currentUser && window.pkp.currentUser.csrfToken) {
				xhr.setRequestHeader('X-Csrf-Token', window.pkp.currentUser.csrfToken);
			}

			xhr.withCredentials = true;
			xhr.send(formData);
		},

		/**
		 * Mark a slot as having a successfully uploaded file.
		 * @param {HTMLElement} slot
		 * @param {string} fileName
		 */
		_markSlotAsUploaded: function(slot, fileName) {
			var uploadDiv = slot.querySelector('.rf-genre-upload');
			var progressDiv = slot.querySelector('.rf-genre-progress');
			if (uploadDiv) uploadDiv.style.display = 'none';
			if (progressDiv) progressDiv.style.display = 'none';

			var uploadedDiv = slot.querySelector('.rf-genre-uploaded');
			uploadedDiv.style.display = 'flex';

			while (uploadedDiv.firstChild) {
				uploadedDiv.removeChild(uploadedDiv.firstChild);
			}

			var icon = document.createElement('span');
			icon.className = 'rf-uploaded-icon';
			icon.textContent = '\u2713';
			uploadedDiv.appendChild(icon);

			var nameSpan = document.createElement('span');
			nameSpan.className = 'rf-uploaded-name';
			nameSpan.textContent = fileName || '';
			uploadedDiv.appendChild(nameSpan);

			var replaceBtn = document.createElement('button');
			replaceBtn.type = 'button';
			replaceBtn.className = 'rf-replace-btn pkp_button';
			replaceBtn.textContent = this.labels.replace;
			uploadedDiv.appendChild(replaceBtn);

			var status = slot.querySelector('.rf-genre-status');
			if (status) {
				status.className = 'rf-genre-status rf-status-uploaded';
				status.textContent = this.labels.uploaded;
			}

			var self = this;
			replaceBtn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				self._resetSlot(slot);
			});

			this._checkAllUploaded();
		},

		_resetSlot: function(slot) {
			var uploadDiv = slot.querySelector('.rf-genre-upload');
			var uploadedDiv = slot.querySelector('.rf-genre-uploaded');
			var fileInput = slot.querySelector('.rf-file-field');
			var nameSpan = slot.querySelector('.rf-file-name');
			var status = slot.querySelector('.rf-genre-status');

			if (uploadedDiv) uploadedDiv.style.display = 'none';
			if (uploadDiv) uploadDiv.style.display = 'flex';
			if (fileInput) fileInput.value = '';
			if (nameSpan) nameSpan.textContent = '';
			if (status) {
				status.className = 'rf-genre-status rf-status-missing';
				status.textContent = this.labels.notUploaded;
			}

			this._checkAllUploaded();
		},

		_checkAllUploaded: function() {
			var slots = document.querySelectorAll('.rf-genre-slot');
			var allUploaded = true;
			for (var i = 0; i < slots.length; i++) {
				var uploadedDiv = slots[i].querySelector('.rf-genre-uploaded');
				if (!uploadedDiv || uploadedDiv.style.display === 'none' || uploadedDiv.style.display === '') {
					allUploaded = false;
					break;
				}
			}

			var errorBanner = document.getElementById('rf-validation-error');
			if (errorBanner) {
				errorBanner.style.display = allUploaded ? 'none' : 'flex';
			}

			var infoBanner = document.getElementById('rf-info-banner');
			if (infoBanner) {
				if (allUploaded) {
					infoBanner.className = 'rf-info-banner rf-info-complete';
				} else {
					infoBanner.className = 'rf-info-banner';
				}
			}
		},

		_watchOjsFileList: function() {
			var self = this;
			var container = document.getElementById('submission-files-container');
			if (!container) return;

			if (this._fileListObserver) {
				this._fileListObserver.disconnect();
			}

			var debounceTimer = null;
			this._fileListObserver = new MutationObserver(function() {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function() {
					self._recheckSlotsFromOjsList();
				}, 500);
			});

			this._fileListObserver.observe(container, {
				childList: true,
				subtree: true,
				characterData: true
			});
		},

		_recheckSlotsFromOjsList: function() {
			var container = document.getElementById('submission-files-container');
			if (!container) return;

			var self = this;
			var slots = document.querySelectorAll('.rf-genre-slot');

			var vueItems = this._getVueFileItems();
			var genreIdsInList = {};
			var genreFileNames = {};

			if (vueItems && vueItems.length > 0) {
				for (var j = 0; j < vueItems.length; j++) {
					var item = vueItems[j];
					if (item.genreId) {
						genreIdsInList[item.genreId] = true;
						var locale = document.documentElement.lang || 'en_US';
						var fname = '';
						if (item.name) {
							fname = (typeof item.name === 'object')
								? (item.name[locale] || item.name['en_US'] || item.name[Object.keys(item.name)[0]] || '')
								: item.name;
						}
						if (!genreFileNames[item.genreId]) {
							genreFileNames[item.genreId] = fname;
						}
					}
				}
			} else {
				var ojsListText = container.textContent || '';
				for (var k = 0; k < slots.length; k++) {
					var gName = slots[k].getAttribute('data-genre-name');
					var gId = parseInt(slots[k].getAttribute('data-genre-id'), 10);
					if (gName && ojsListText.indexOf(gName) !== -1) {
						genreIdsInList[gId] = true;
					}
				}
			}

			for (var i = 0; i < slots.length; i++) {
				var slot = slots[i];
				var genreId = parseInt(slot.getAttribute('data-genre-id'), 10);
				var uploadedDiv = slot.querySelector('.rf-genre-uploaded');
				var isCurrentlyUploaded = uploadedDiv && uploadedDiv.style.display !== 'none' && uploadedDiv.style.display !== '';
				var existsInOjsList = !!genreIdsInList[genreId];

				if (isCurrentlyUploaded && !existsInOjsList) {
					self._resetSlot(slot);
				} else if (!isCurrentlyUploaded && existsInOjsList) {
					var displayName = genreFileNames[genreId] || slot.getAttribute('data-genre-name');
					self._markSlotAsUploaded(slot, displayName);
				}
			}
		},

		_getVueFileItems: function() {
			try {
				var vm = window.pkp.registry._instances['submission-files-container'];
				if (vm && vm.components && vm.components.submissionFiles) {
					return vm.components.submissionFiles.items || [];
				}
			} catch (e) {}
			return null;
		},

		_syncWithVueComponent: function() {
			if (!window.pkp || !window.pkp.registry || !window.pkp.registry._instances) return;
			var vm = window.pkp.registry._instances['submission-files-container'];
			if (!vm || typeof vm.set !== 'function') return;

			var apiUrl = this.apiUrl;
			var csrfToken = (window.pkp.currentUser && window.pkp.currentUser.csrfToken) || '';

			var xhr = new XMLHttpRequest();
			xhr.open('GET', apiUrl, true);
			xhr.withCredentials = true;
			if (csrfToken) {
				xhr.setRequestHeader('X-Csrf-Token', csrfToken);
			}
			xhr.addEventListener('load', function() {
				if (xhr.status >= 200 && xhr.status < 300) {
					try {
						var data = JSON.parse(xhr.responseText);
						var items = data.items || data;
						vm.set('submissionFiles', { items: items });
					} catch (e) {}
				}
			});
			xhr.send();
		},

		_showSlotError: function(slot, message) {
			var errorDiv = slot.querySelector('.rf-genre-error');
			if (errorDiv) {
				errorDiv.textContent = message;
				errorDiv.style.display = 'block';
			}
		},

		_escapeHtml: function(str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		},

		_parseJson: function(jsonStr) {
			try {
				return JSON.parse(jsonStr) || [];
			} catch (e) {
				return [];
			}
		}
	};

	function tryInit() {
		var section = document.getElementById('rf-required-section');
		if (section && !section.getAttribute('data-rf-initialized')) {
			section.setAttribute('data-rf-initialized', '1');
			RequiredFiles.init();
		}
	}

	function startWatching() {
		tryInit();

		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function() {
				tryInit();
			});
			observer.observe(document.body || document.documentElement, {
				childList: true,
				subtree: true
			});
		} else {
			setInterval(function() {
				tryInit();
			}, 1000);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startWatching);
	} else {
		startWatching();
	}
})();
