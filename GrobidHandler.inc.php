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

		// TODO Should other checks of the response be performed?
		$jatsXML = new \DOMDocument();
		$jatsXML->loadXML($response);

		if (!$this->_checkXML($jatsXML)) {
			$errorMsg = __('plugins.generic.grobid.msg.responseInvalidXML');
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $errorMsg));
			return DAO::getDataChangedEvent();
		}

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
	 * @param $request Request
	 * @return string|null
	 *
	 */
	private function _sendRequest($filepath, $mimeType, $request) {
		$contextId = $request->getContext()->getId();
		/* @var $plugin GrobidPlugin */
		$plugin = $this->_plugin;
		$url = trim($plugin->getSetting($contextId, "host")) . $plugin::GROBID_SERVICE_API_PATH;

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 1,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => array("input" => new CurlFile($filepath, $mimeType)),
			CURLOPT_HTTPHEADER => array(
				"Accept: application/xml",
				"Accept-Charset: utf-8",
				"Content-Type: multipart/form-data",
				"Referer: " . $request->getCompleteUrl(),
				"User-Agent: curl/" . curl_version()["version"]
			),

		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			error_log("Grobid CURL error: " . $err);
			return null;
		} else {
			return $response;
		}

	}

	/**
	 * @param $xml \DOMDocument
	 * @return bool
	 */
	private function _checkXML($xml) {
		$doctype = $xml->doctype;
		if (!empty($doctype) && !empty($doctype->systemId) && in_array($doctype->systemId, $this->_plugin::GROBID_RESPONSE_XML_TYPES)) {
			return true;
		}

		return false;
	}

}
