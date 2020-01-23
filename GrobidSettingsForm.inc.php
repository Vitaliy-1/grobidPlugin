<?php


/**
 * @file plugins/generic/grobid/GrobidSettingsForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GrobidSettingsForm
 * @ingroup plugins_generic_grobid
 *
 * @brief Form for journal managers to modify grobid plugin settings
 */

import('lib.pkp.classes.form.Form');

class GrobidSettingsForm extends Form {

	/** @var int */
	var $_journalId;

	/** @var object */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin GrobidPlugin
	 * @param $journalId int
	 */
	function __construct($plugin, $journalId) {
		$this->_journalId = $journalId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$contextId = $this->_journalId ;
		$plugin = $this->_plugin;

		$this->setData('host', $plugin->getSetting($contextId, 'host'));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('host'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$plugin = $this->_plugin;
		$contextId = $this->_journalId ;

		$plugin->updateSetting($contextId, 'host', $this->getData('host'));
	}
}
