<?php

/**
 * @file plugins/generic/requiredFiles/RequiredFilesPlugin.inc.php
 *
 * Copyright (c) 2026 OJS Services
 *
 * @class RequiredFilesPlugin
 * @ingroup plugins_generic_requiredFiles
 * @brief Enforces mandatory file type uploads during article submission.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class RequiredFilesPlugin extends GenericPlugin {

	/** @var bool Prevent recursive TemplateManager::fetch calls */
	private $_fetchingStep2 = false;

	/** @var array Missing genre names from last validation (for persistent error banner) */
	private $_lastMissingNames = array();

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);

		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
			return true;
		}

		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('SubmissionHandler::saveSubmit', array($this, 'handleSaveSubmit'));
			HookRegistry::register('TemplateManager::fetch', array($this, 'handleTemplateFetch'));
			HookRegistry::register('TemplateManager::display', array($this, 'registerAssets'));
		}

		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.requiredFiles.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.requiredFiles.description');
	}

	/**
	 * @copydoc Plugin::isSitePlugin()
	 */
	public function isSitePlugin() {
		return false;
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled() ? array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null,
							array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			) : array(),
			parent::getActions($request, $verb)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
				$this->import('RequiredFilesSettingsForm');
				$form = new RequiredFilesSettingsForm($this, $request->getContext()->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						import('classes.notification.NotificationManager');
						$notificationManager = new NotificationManager();
						$notificationManager->createTrivialNotification($request->getUser()->getId());
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * Hook callback: Inject required files panel into submission Step 2.
	 *
	 * @param $hookName string
	 * @param $args array [templateMgr, template, cache_id, compile_id, &result]
	 * @return bool
	 */
	public function handleTemplateFetch($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];

		if ($template !== 'submission/form/step2.tpl' || $this->_fetchingStep2) {
			return false;
		}

		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) return false;

		$contextId = $context->getId();

		$requiredGenresJson = $this->getSetting($contextId, 'requiredGenres');
		if (!$requiredGenresJson) return false;

		$requiredGenreIds = json_decode($requiredGenresJson, true);
		if (!is_array($requiredGenreIds) || empty($requiredGenreIds)) return false;

		$genreDao = DAORegistry::getDAO('GenreDAO');
		$requiredGenresData = array();
		foreach ($requiredGenreIds as $genreId) {
			$genre = $genreDao->getById((int)$genreId, $contextId);
			if ($genre && $genre->getEnabled()) {
				$requiredGenresData[] = array(
					'id' => (int)$genre->getId(),
					'name' => $genre->getLocalizedName(),
				);
			}
		}
		if (empty($requiredGenresData)) return false;

		$submissionId = (int)$request->getUserVar('submissionId');
		$apiUrl = '';
		if ($submissionId) {
			$apiUrl = $request->getDispatcher()->url(
				$request,
				ROUTE_API,
				$context->getPath(),
				'submissions/' . $submissionId . '/files'
			);
		}

		import('lib.pkp.classes.submission.SubmissionFile');
		$existingFilesData = array();
		if ($submissionId) {
			$submissionFilesIterator = Services::get('submissionFile')->getMany(array(
				'fileStages' => array(SUBMISSION_FILE_SUBMISSION),
				'submissionIds' => array($submissionId),
			));
			foreach ($submissionFilesIterator as $submissionFile) {
				$existingFilesData[] = array(
					'id' => $submissionFile->getId(),
					'genreId' => (int)$submissionFile->getData('genreId'),
					'name' => $submissionFile->getLocalizedData('name'),
				);
			}
		}

		// Assign template variables for the panel
		$rfValidationErrorStr = '';
		if (!empty($this->_lastMissingNames)) {
			$rfValidationErrorStr = __('plugins.generic.requiredFiles.validation.missingFiles',
				array('fileTypes' => implode(', ', $this->_lastMissingNames)));
		}

		$templateMgr->assign(array(
			'rfRequiredGenres' => $requiredGenresData,
			'rfSubmissionId' => $submissionId,
			'rfApiUrl' => $apiUrl,
			'rfExistingFiles' => $existingFilesData,
			'rfExistingFilesJson' => json_encode($existingFilesData),
			'rfRequiredGenresJson' => json_encode($requiredGenresData),
			'rfValidationErrors' => $this->_lastMissingNames,
			'rfValidationErrorStr' => $rfValidationErrorStr,
			'rfPluginUrl' => $request->getBaseUrl() . '/' . $this->getPluginPath(),
		));

		$cache_id = $args[2];
		$compile_id = $args[3];
		$this->_fetchingStep2 = true;
		$originalOutput = $templateMgr->fetch($template, $cache_id, $compile_id);
		$this->_fetchingStep2 = false;

		$panelOutput = $templateMgr->fetch($this->getTemplateResource('requiredFilesPanel.tpl'));

		$result =& $args[4];
		$result = str_replace(
			'<div id="submission-files-container">',
			$panelOutput . '<div id="submission-files-container">',
			$originalOutput
		);

		return true;
	}

	/**
	 * Hook callback: Register CSS and JavaScript assets.
	 *
	 * @param $hookName string
	 * @param $args array [templateMgr, template]
	 * @return bool
	 */
	public function registerAssets($hookName, $args) {
		$templateMgr = $args[0];
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) return false;

		$baseUrl = $request->getBaseUrl();
		$pluginPath = $this->getPluginPath();

		$templateMgr->addStyleSheet(
			'requiredFilesCss',
			$baseUrl . '/' . $pluginPath . '/css/requiredFiles.css',
			array('contexts' => array('frontend', 'backend'))
		);

		$templateMgr->addJavaScript(
			'requiredFilesJs',
			$baseUrl . '/' . $pluginPath . '/js/requiredFiles.js',
			array('contexts' => array('frontend', 'backend'))
		);

		return false;
	}

	/**
	 * Validate required files at submission Step 2.
	 *
	 * @param $hookName string
	 * @param $args array [step, &submission, &submitForm]
	 * @return bool
	 */
	public function handleSaveSubmit($hookName, $args) {
		$step = $args[0];
		$submission =& $args[1];
		$submitForm =& $args[2];

		if ($step != 2) {
			return false;
		}

		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) {
			return false;
		}

		$contextId = $context->getId();

		$requiredGenresJson = $this->getSetting($contextId, 'requiredGenres');
		if (!$requiredGenresJson) {
			return false;
		}

		$requiredGenreIds = json_decode($requiredGenresJson, true);
		if (!is_array($requiredGenreIds) || empty($requiredGenreIds)) {
			return false;
		}

		import('lib.pkp.classes.submission.SubmissionFile');
		$submissionFilesIterator = Services::get('submissionFile')->getMany(array(
			'fileStages' => array(SUBMISSION_FILE_SUBMISSION),
			'submissionIds' => array($submission->getId()),
		));

		$uploadedGenreIds = array();
		foreach ($submissionFilesIterator as $submissionFile) {
			$genreId = $submissionFile->getData('genreId');
			if ($genreId) {
				$uploadedGenreIds[$genreId] = true;
			}
		}

		$missingGenreIds = array_diff($requiredGenreIds, array_keys($uploadedGenreIds));

		if (!empty($missingGenreIds)) {
			$genreDao = DAORegistry::getDAO('GenreDAO');
			$missingNames = array();
			foreach ($missingGenreIds as $genreId) {
				$genre = $genreDao->getById($genreId, $contextId);
				if ($genre && $genre->getEnabled()) {
					$missingNames[] = $genre->getLocalizedName();
				}
			}

			if (!empty($missingNames)) {
				$this->_lastMissingNames = $missingNames;

				$submitForm->addError('files',
					__('plugins.generic.requiredFiles.validation.missingFiles',
						array('fileTypes' => implode(', ', $missingNames))));

				if ($submission->getSubmissionProgress() > 2 || $submission->getSubmissionProgress() == 0) {
					$submission->setSubmissionProgress(2);
					$submissionDao = DAORegistry::getDAO('SubmissionDAO');
					$submissionDao->updateObject($submission);
				}
			}
		}

		return false;
	}
}
