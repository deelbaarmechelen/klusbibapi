<?php
namespace Api\Upload;


class UploadHandler
{
	private $logger;
	private $uploadedFileName;
	
	function __construct($logger = NULL) {
		$this->logger = $logger;
	}
	
	function uploadFiles($files) {
		if (empty($files['newfile'])) {
			$this->logger->error("Upload error: Expected a newfile");
			throw new Exception('Expected a newfile');
		}
		
		$newfile = $files['newfile'];
		return $this->uploadFile($newfile);
	}
	
	function uploadFile($newfile) {
		if ($newfile->getError() === UPLOAD_ERR_OK) {
			$this->uploadFileName = $newfile->getClientFilename();
			$newfile->moveTo(__DIR__ . "/../../../public/uploads/$this->uploadFileName");
		} else {
			$this->formatErrorMessage($newfile->getError());
		}
	}
	
	private function formatErrorMessage($error) {
		switch ($error) {
			case UPLOAD_ERR_OK:
				$this->logger->info("Upload successful");
				break;
			case UPLOAD_ERR_INI_SIZE:
				$this->logger->error("Upload error $error: The uploaded file exceeds the upload_max_filesize directive in php.ini");
				break;
			case UPLOAD_ERR_FORM_SIZE:
				$this->logger->error("Upload error $error: The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form");
				break;
			case UPLOAD_ERR_PARTIAL:
				$this->logger->error("Upload error $error: The uploaded file was only partially uploaded");
				break;
			case UPLOAD_ERR_NO_FILE:
				$this->logger->error("Upload error $error: No file was uploaded");
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				$this->logger->error("Upload error $error: Missing a temporary folder.");
				break;
			case UPLOAD_ERR_CANT_WRITE:
				$this->logger->error("Upload error $error: Failed to write file to disk");
				break;
			case UPLOAD_ERR_EXTENSION:
				$this->logger->error("Upload error $error: A PHP extension stopped the file upload");
				break;
			}
	}
	
	function getUploadedFileName() {
		return $this->uploadFileName;
	}
}
