<?php


/**
 * @file plugins/generic/grobid/GrobidPlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University Library
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief plugin for conversion to JATS XML through Grobid;
 * Requires Grobid to be installed and Grobid's Web Service to be launched as a separate instance
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class GrobidPlugin extends GenericPlugin {

	// TODO Grobid may introduce support for other formats, like OOXML
	const GROBID_SERVICE_FILE_TYPES = array("application/pdf");

	const GROBID_SERVICE_API_PATH = "/api/processFulltextDocumentJATS";

	const GROBID_RESPONSE_XML_TYPES = array("https://jats.nlm.nih.gov/publishing/1.2/JATS-journalpublishing1.dtd");

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.grobid.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.grobid.description');
	}

	/**
	 * Register the plugin
	 *
	 * @param $category string Plugin category
	 * @param $path string Plugin path
	 *
	 * @return bool True on successful registration false otherwise
	 */
	function register($category, $path) {
		if (parent::register($category, $path)) {
			if ($this->getEnabled()) {
				// Register callbacks.
				HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));
				HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
				$this->_registerTemplateResource();
			}
			return true;
		}
		return false;
	}

	/**
	 * Get plugin URL
	 * @param $request PKPRequest
	 * @return string
	 */
	function getPluginUrl($request) {
		return $request->getBaseUrl() . '/' . $this->getPluginPath();
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
				$this->import('GrobidSettingsForm');
				$form = new GrobidSettingsForm($this, $context->getId());
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	public function callbackLoadHandler($hookName, $args) {
		$page = $args[0];
		$op = $args[1];

		if ($page == "grobid" && $op == "process") {
			define('HANDLER_CLASS', 'GrobidHandler');
			define('GROBID_PLUGIN_NAME', $this->getName());
			$args[2] = $this->getPluginPath() . '/' . 'GrobidHandler.inc.php';
		}

		return false;
	}

	/**
	 * Adds additional links to submission files grid row
	 * @param $hookName string The name of the invoked hook
	 * @param $args array Hook parameters
	 */
	public function templateFetchCallback($hookName, $params) {

		$request = $this->getRequest();
		$context = $request->getContext();
		if (!$this->getSetting($context->getId(), "host")) return false;

		$dispatcher = $request->getDispatcher();

		$templateMgr = $params[0];
		$resourceName = $params[1];
		if ($resourceName == 'controllers/grid/gridRow.tpl') {
			/* @var $row GridRow */
			$row = $templateMgr->get_template_vars('row');
			$data = $row->getData();

			if (is_array($data) && (isset($data['submissionFile']))) {
				$submissionFile = $data['submissionFile'];
				$fileExtension = strtolower($submissionFile->getExtension());

				if (strtolower($fileExtension) == 'pdf') {

					$stageId = (int) $request->getUserVar('stageId');
					//$path = $router->url($request, null, 'converter', 'parse', null, $actionArgs);
					$path = $dispatcher->url($request, ROUTE_PAGE, null, 'grobid', 'process', null,
						array(
							'submissionId' => $submissionFile->getSubmissionId(),
							'fileId' => $submissionFile->getFileId(),
							'stageId' => $stageId
						));
					$pathRedirect = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'access',
						array(
							'submissionId' => $submissionFile->getSubmissionId(),
							'fileId' => $submissionFile->getFileId(),
							'stageId' => $stageId
						));

					import('lib.pkp.classes.linkAction.request.AjaxAction');
					$linkAction = new LinkAction(
						'process',
						new PostAndRedirectAction($path, $pathRedirect),
						__('plugins.generic.grobid.button.parseDocument')
					);
					$row->addAction($linkAction);
				}
			}
		}
	}
}
