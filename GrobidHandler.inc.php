<?php

/**
 * @file plugins/generic/grobid/GrobidHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University Library
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief handler for the grid's conversion
 */

import('classes.handler.Handler');

class GrobidHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->_plugin = PluginRegistry::getPlugin('generic', GROBID_PLUGIN_NAME);
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('process')
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_READ));
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function process($args, $request) {

		$user = $request->getUser();
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$filePath = $submissionFile->getFilePath();

		$notificationMgr = new NotificationManager();

		$mimeType = htmlentities($submissionFile->getFileType());
		if (!in_array($mimeType, $this->_plugin::GROBID_SERVICE_FILE_TYPES)) {
			$errorMsg = __('plugins.generic.grobid.msg.fileTypeError');
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $errorMsg . ": " . $mimeType));
			return DAO::getDataChangedEvent();
		}

		$response = $this->_sendRequest($filePath, $mimeType, $request);
		if (!$response) {
			$errorMsg = __('plugins.generic.grobid.msg.requestFailedError');
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $errorMsg));
			return DAO::getDataChangedEvent();
		}

		$jatsXML = new \DOMDocument();
		$submissionDao = Application::getSubmissionDAO();
		$submissionId = $submissionFile->getSubmissionId();
		$submission = $submissionDao->getById($submissionId);
		$tmpfname = tempnam(sys_get_temp_dir(), 'grobid');
		file_put_contents($tmpfname, $jatsXML->saveXML());
		$genreId = $submissionFile->getGenreId();
		$fileSize = filesize($tmpfname);

		$originalFileInfo = pathinfo($submissionFile->getOriginalFileName());

		/* @var $newSubmissionFile SubmissionFile */
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$newSubmissionFile = $submissionFileDao->newDataObjectByGenreId($genreId);
		$newSubmissionFile->setSubmissionId($submission->getId());
		$newSubmissionFile->setSubmissionLocale($submission->getLocale());
		$newSubmissionFile->setGenreId($genreId);
		$newSubmissionFile->setFileStage($submissionFile->getFileStage());
		$newSubmissionFile->setDateUploaded(Core::getCurrentDate());
		$newSubmissionFile->setDateModified(Core::getCurrentDate());
		$newSubmissionFile->setOriginalFileName($originalFileInfo['filename'] . ".xml");
		$newSubmissionFile->setUploaderUserId($user->getId());
		$newSubmissionFile->setFileSize($fileSize);
		$newSubmissionFile->setFileType("text/xml");
		$newSubmissionFile->setSourceFileId($submissionFile->getFileId());
		$newSubmissionFile->setSourceRevision($submissionFile->getRevision());
		$newSubmissionFile->setRevision(1);
		$insertedSubmissionFile = $submissionFileDao->insertObject($newSubmissionFile, $tmpfname);
		unlink($tmpfname);

		return new JSONMessage(true, array(
			'submissionId' => $insertedSubmissionFile->getSubmissionId(),
			'fileId' => $insertedSubmissionFile->getFileIdAndRevision(),
			'fileStage' => $insertedSubmissionFile->getFileStage(),
		));
	}

	/**
	 * @param $filepath string
	 * @param $mimeType string
	 * @return string|null
	 *
	 */
	private function _sendRequest($filepath, $mimeType, $request) {
		$contextId = $request->getContext()->getId();
		/* @var $plugin GrobidPlugin */
		$plugin = $this->_plugin;
		$url = $plugin->getSetting($contextId, "host") . $plugin::GROBID_SERVICE_API_PATH;

		$fileContent = file_get_contents($filepath);
		$boundary = "----" . hash("sha256", random_int(PHP_INT_MIN, PHP_INT_MAX)); // Check for exception? Should be secured?

		// Should work and for https protocol; see HTTP context options
		$contextOptions = array(
			"http" => array(
				"method" => "POST",
				"header" => "Content-Type: " . "multipart/form-data; boundary=" . $boundary . "\r\n",
				"content" => $boundary . "\r\n" .
					"Content-Length: " . filesize($filepath). "\r\n" .
					"Content-Disposition: form-data; name=\"input\"; filename=\"" . basename($filepath). "\"\r\n" .
					$fileContent . "\r\n" .
					$boundary . "\r\n"
			)
		);

		$context = stream_context_create($contextOptions);

		$response = null;

		if (!$fp = fopen($url, "rb", false, $context)) {
			return null;
		} else {
			$response = stream_get_contents($fp);
			fclose($fp);
		}

	}

}
