<?php

namespace ride\service;

use ride\library\system\exception\FileSystemException;
use ride\library\system\file\FileSystem;
use ride\library\system\file\File;
use ride\library\StringHelper;
use ride\library\http\DataUri;
use ride\library\http\exception\HttpException;

use ride\service\MimeService;

/**
 * Service to process file uploads
 */
class UploadService {

    /**
     * @var \ride\library\system\file\FileSystem
     */
    protected $fileSystem;

    /**
     * @var \ride\service\MimeService
     */
    protected $mimeService;

    /**
     * Temporary upload directory
     * @var \ride\library\system\file\File
     */
    protected $uploadDirectoryTemporary;

    /**
     * Permanent upload directory
     * @var \ride\library\system\file\File
     */
    protected $uploadDirectoryPermanent;

    /**
     * Absolute paths which should be made relative
     * @var array
     */
    protected $absolutePaths = array();

    /**
     * Constructs a new file upload service
     * @param \ride\library\system\file\FileSystem $fileSystem
     * @param \ride\service\MimeService $mimeService
     * @param \ride\library\system\file\File $uploadDirectoryTemporary
     * @param \ride\library\system\file\File $uploadDirectoryPermanent
     * @return null
     */
    public function __construct(
        FileSystem $fileSystem,
        MimeService $mimeService,
        File $uploadDirectoryTemporary,
        File $uploadDirectoryPermanent
    ) {
        $this->fileSystem = $fileSystem;
        $this->mimeService = $mimeService;

        $this->setUploadDirectoryTemporary($uploadDirectoryTemporary);
        $this->setUploadDirectoryPermanent($uploadDirectoryPermanent);
    }

    /**
     * Sets the temporary upload directory
     * @return null
     */
    protected function setUploadDirectoryTemporary(File $directory) {
        if (!$directory->exists()) {
            $directory->create();
        } elseif (!$directory->isDirectory()) {
            throw new FileSystemException('Could not set upload directory: ' . $directory . ' is not a directory');
        }

        $this->uploadDirectoryTemporary = $directory;
    }

    /**
     * Gets the temporary upload directory
     * @return \ride\library\system\file\File
     */
    public function getUploadDirectoryTemporary() {
        return $this->uploadDirectoryTemporary;
    }

    /**
     * Sets the permanent upload directory
     * @return null
     */
    protected function setUploadDirectoryPermanent(File $directory) {
        if (!$directory->exists()) {
            $directory->create();
        } elseif (!$directory->isDirectory()) {
            throw new FileSystemException('Could not set upload directory: ' . $directory . ' is not a directory');
        }

        $this->uploadDirectoryPermanent = $directory;
    }

    /**
     * Gets the permanent upload directory
     * @return \ride\library\system\file\File
     */
    public function getUploadDirectoryPermanent() {
        return $this->uploadDirectoryPermanent;
    }

    /**
     * Move temporary file to the permanent directory, which can be overridden
     * @param \ride\library\system\file\File $oldFile
     * @param \ride\library\system\file\File $permanentDirectory
     * @return \ride\library\system\file\File
     */
    public function moveTemporaryToPermanent(File $oldFile, File $permanentDirectory) {
        $newFile = $permanentDirectory->getChild($oldFile->getName());

        $this->fileSystem->move($oldFile, $newFile);

        return $newFile;
    }

    /**
     * Adds a absolute path
     * @param string|\ride\library\system\file\File $path
     * @return null
     */
    public function addAbsolutePath($path) {
        if ($path instanceof File) {
            $path = $path->getAbsolutePath();
        }

        $this->absolutePaths[] = $path;
    }

    /**
     * Get the relative path of a file
     * @param \ride\library\system\file\File $file
     * @return string
     */
    public function getRelativePath(File $file) {
        $relativePath = $file->getAbsolutePath();
        foreach ($this->absolutePaths as $absolutePath) {
            if (strpos($relativePath, $absolutePath) === 0) {
                $relativePath = str_replace($absolutePath . '/', '', $relativePath);
                break;
            }
        }
        return $relativePath;
    }

    /**
     * Gets an uploaded file
     * @param string $name Name of the file
     * @return \ride\library\system\file\File|null
     */
    public function getTemporaryFile($name) {
        $file = $this->getUploadDirectoryTemporary()->getChild($name);
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
        $this->checkUploadFile($fileNameOrg);

        // prepare file name
        $uploadFileName = StringHelper::safeString($fileNameOrg, '_', false);

        $uploadFile = $this->getUploadDirectoryTemporary()->getChild($uploadFileName);
        $uploadFile = $uploadFile->getCopyFile();

        // move file from temp to upload path
        if (!move_uploaded_file($fileNameTmp, $uploadFile->getPath())) {
            throw new FileSystemException('Could not move the uploaded file ' . $fileNameTmp . ' to ' . $uploadFile->getPath());
        }

        $uploadFile->setPermissions(0644);

        return $uploadFile;
    }

    /**
     * Handles a file upload via a dataURI string
     * @param string $fileName Filename
     * @param string $dataUri DataURI with file content
     * @return \ride\library\system\file\File
     * @throws \ride\library\system\exception\FileSystemException
     */
    public function handleDataUri($fileName, $dataUriString) {
        // decode dataUri
        $uploadFile = null;
        $dataUri = null;
        try {
            $dataUri = DataUri::decode($dataUriString);
        } catch (HttpException $err) { }

        if ($dataUri instanceof DataUri) {
            // add extension based on claimed mime type
            $mimeType = $dataUri->getMimeType();
            $mimeExtension = $this->mimeService->getExtensionForMediaType($mimeType);
            if (is_string($mimeExtension)) {
                $fileName .= '.' . $mimeExtension;
            }

            // prepare file name
            $fileName = StringHelper::safeString($fileName, '_', false);

            $uploadFile = $this->getUploadDirectoryTemporary()->getChild($fileName);
            $uploadFile = $uploadFile->getCopyFile();

            $uploadFile->write($dataUri->getData());

            $uploadFile->setPermissions(0644);
        }

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
