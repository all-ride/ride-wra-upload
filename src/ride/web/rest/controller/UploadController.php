<?php

namespace ride\web\rest\controller;

use ride\library\http\jsonapi\JsonApi;
use ride\library\http\Header;
use ride\library\http\Response;
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
        $api = new JsonApi();
        $document = $api->createDocument();

        $file = $this->request->getBodyParameter('file');
        if (!is_array($file)) {
            $document->addError($api->createError(Response::STATUS_CODE_BAD_REQUEST, 'file.upload.none', 'No file uploaded'));
        } else {
            try {
                $uploadedFile = $uploadService->uploadFile($file);

                $resource = $api->createResource('uploads', $uploadedFile->getName());
                $resource->setAttribute('name', $uploadedFile->getName());
                $resource->setAttribute('mime', $file['type']);
                $resource->setAttribute('size', $file['size']);

                $document->setResourceData('uploads', $resource);
            } catch (FileSystemException $exception) {
                $document->addError($api->createError(Response::STATUS_CODE_BAD_REQUEST, 'file.upload.error', 'Error occured while processing the file upload', $exception->getMessage()));
            }
        }

        $this->setJsonView($document);
        $this->response->setStatusCode($document->getStatusCode());
        $this->response->setHeader(Header::HEADER_CONTENT_TYPE, JsonApi::CONTENT_TYPE);
    }

}
