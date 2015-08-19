<?php

namespace ride\web\rest\controller;

use ride\library\system\exception\FileSystemException;

use ride\service\UploadService;

use ride\web\mvc\controller\AbstractController;

/**
 * Controller for the file upload endpoint of the Ride REST
 */
class UploadController extends AbstractController {

    /**
     * Action to handle a file upload
     * @param \ride\service\UploadService $uploadService
     * @return null
     */
    public function uploadAction(UploadService $uploadService) {
        $file = $this->request->getBodyParameter('file');
        if (!is_array($file)) {
            $this->response->setBadRequest();
            $this->setJsonView(array('error' => 'no file uploaded'));

            return;
        }

        try {
            $uploadedFile = $uploadService->uploadFile($file);
        } catch (FileSystemException $exception) {
            $this->response->setBadRequest();
            $this->setJsonView(array('error' => $exception->getMessage()));

            return;
        }

        $this->setJsonView(array(
            'file' => $uploadedFile->getName(),
            'type' => $file['type'],
            'size' => $file['size'],
        ));
    }

}
