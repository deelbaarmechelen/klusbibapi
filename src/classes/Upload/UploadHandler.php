<?php
namespace Api\Upload;


class UploadHandler
{
	private $logger;
	private $uploadFileName;

    const UPLOAD_DIR = 'uploads/';

    function __construct($logger = NULL) {
		$this->logger = $logger;
	}
	
	function uploadFiles($files) {

		if (empty($files['newfile'])) {
			$this->logger->error("Upload error: Expected a newfile");
			throw new \Exception('Expected a newfile');
		}
		
		$newfile = $files['newfile'];
		return $this->uploadFile($newfile);
	}
	
	function uploadFile($newfile, $filename = NULL) {
		if ($newfile->getError() === UPLOAD_ERR_OK) {
		    if (is_null($filename)) {
                $this->uploadFileName = $newfile->getClientFilename();
            } else {
		        $this->uploadFileName = $filename;
            }
            $uploadFileParts = pathinfo($this->uploadFileName);
            if (empty($uploadFileParts['extension'])) {
                $clientNameParts = pathinfo($newfile->getClientFilename());
                $this->uploadFileName .= '.' . $clientNameParts['extension'];
            }
			$newfile->moveTo(__DIR__ . "/../../../public/" . self::UPLOAD_DIR . $this->uploadFileName);
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

	function getUploadPublicUrl() {
        $publicUrl = !empty($_SERVER['HTTPS']) ? 'https' : 'http'; //HTTPS or HTTP
        $publicUrl .= '://' . $_SERVER['HTTP_HOST']; //HOST
        $publicUrl .= '/' . self::UPLOAD_DIR;
        $publicUrl .= $this->getUploadedFileName();
        return $publicUrl;
    }
}
