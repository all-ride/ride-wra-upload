<?php

namespace ride\service;

use ride\library\system\exception\FileSystemException;
use ride\library\system\file\File;
use ride\library\StringHelper;

/**
 * Service to process file uploads
 */
class UploadService {

    /**
     * Constructs a new file upload service
     * @param \ride\library\system\file\File $uploadDirectory
     * @return null
     */
    public function __construct(File $uploadDirectory) {
        $this->setUploadDirectory($uploadDirectory);
    }

    /**
     * Sets the upload directory
     * @return \ride\library\system\file\File
     */
    public function setUploadDirectory(File $uploadDirectory) {
        if (!$uploadDirectory->exists()) {
            $uploadDirectory->create();
        } elseif (!$uploadDirectory->isDirectory()) {
            throw new FileSystemException('Could not set upload directory: ' . $file . ' is not a directory');
        }

        $this->uploadDirectory = $uploadDirectory;
    }

    /**
     * Gets the upload directory
     * @return \ride\library\system\file\File
     */
    public function getUploadDirectory() {
        return $this->uploadDirectory;
    }

    /**
     * Gets an uploaded file
     * @param string $name Name of the file
     * @return \ride\library\system\file\File|null
     */
    public function getFile($name) {
        $file = $this->uploadDirectory->getChild($name);
        if (!$file->exists()) {
            return null;
        }

        return $file;
    }

    /**
     * Handles a file upload
     * @param string $fileNameOrg Original filename
     * @param string $fileNameTmp Temporary filename
     * @param int $fileError File error code
     * @return \ride\library\system\file\File
     * @throws \ride\library\system\exception\FileSystemException
     */
    public function handleFileUpload($fileNameOrg, $fileNameTmp, $fileError) {
        $this->checkUploadFile($fileNameOrg, $fileNameTmp, $fileError);

        // prepare file name
        $uploadFileName = StringHelper::safeString($fileNameOrg, '_', false);

        $uploadFile = $this->uploadDirectory->getChild($uploadFileName);
        $uploadFile = $uploadFile->getCopyFile();

        // move file from temp to upload path
        if (!move_uploaded_file($fileNameTmp, $uploadFile->getPath())) {
            throw new FileSystemException('Could not move the uploaded file ' . $fileNameTmp . ' to ' . $uploadFile->getPath());
        }

        $uploadFile->setPermissions(0644);

        return $uploadFile;
    }

    /**
     * Checks whether a file upload error occured
     * @param int $fileError File error code
     * @return null
     * @throws \ride\library\system\exception\FileSystemException when a upload
     * error occured
     */
    protected function checkUploadFile($fileError) {
        if ($fileError != UPLOAD_ERR_OK) {
            switch ($fileError) {
                case UPLOAD_ERR_NO_FILE:
                    $message = 'No file uploaded';

                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $message = 'The uploaded file exceeds the maximum upload size';

                    break;
                case UPLOAD_ERR_INI_SIZE:
                    $message = 'The uploaded file was only partially uploaded';

                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = 'No temporary directory to upload the file to';

                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = 'Failed to write file to disk';

                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = 'The upload was stopped by a PHP extension';

                    break;
                default:
                    $message = 'The upload was stopped by an unknown error';

                    break;
            }

            throw new FileSystemException($message);
        }
    }
}
