<?php

/**
 * @file plugins/generic/requiredFiles/RequiredFilesSettingsForm.inc.php
 *
 * Copyright (c) 2026 OJS Services
 *
 * @class RequiredFilesSettingsForm
 * @ingroup plugins_generic_requiredFiles
 * @brief Form for editors to select which file types are required during submission.
 */

import('lib.pkp.classes.form.Form');

class RequiredFilesSettingsForm extends Form {

	/** @var int Associated context ID */
	private $_contextId;

	/** @var RequiredFilesPlugin */
	private $_plugin;

	/**
	 * Constructor.
	 * @param $plugin RequiredFilesPlugin
	 * @param $contextId int
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		$contextId = $this->_contextId;
		$requiredGenresJson = $this->_plugin->getSetting($contextId, 'requiredGenres');
		$requiredGenres = $requiredGenresJson ? json_decode($requiredGenresJson, true) : array();
		if (!is_array($requiredGenres)) {
			$requiredGenres = array();
		}
		$this->setData('requiredGenres', $requiredGenres);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('requiredGenres'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());

		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genresIterator = $genreDao->getEnabledByContextId($this->_contextId);
		$genres = array();
		while ($genre = $genresIterator->next()) {
			if ($genre->getDependent()) {
				continue;
			}
			$genres[] = array(
				'id' => $genre->getId(),
				'name' => $genre->getLocalizedName(),
				'isSupplementary' => (bool) $genre->getSupplementary(),
			);
		}
		$templateMgr->assign('genres', $genres);

		$requiredGenres = $this->getData('requiredGenres');
		if (!is_array($requiredGenres)) {
			$requiredGenres = array();
		}
		$templateMgr->assign('requiredGenres', $requiredGenres);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$contextId = $this->_contextId;
		$requiredGenres = $this->getData('requiredGenres');
		if (!is_array($requiredGenres)) {
			$requiredGenres = array();
		}

		$requiredGenres = array_map('intval', $requiredGenres);

		$genreDao = DAORegistry::getDAO('GenreDAO');
		$validGenres = array();
		foreach ($requiredGenres as $genreId) {
			$genre = $genreDao->getById($genreId, $contextId);
			if ($genre && $genre->getEnabled() && !$genre->getDependent()) {
				$validGenres[] = (int)$genre->getId();
			}
		}

		$this->_plugin->updateSetting($contextId, 'requiredGenres', json_encode($validGenres));
		parent::execute(...$functionArgs);
	}
}
