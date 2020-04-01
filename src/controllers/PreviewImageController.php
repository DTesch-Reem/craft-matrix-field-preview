<?php

namespace weareferal\matrixfieldpreview\controllers;

use weareferal\matrixfieldpreview\MatrixFieldPreview;

use Craft;
use craft\web\Controller;
use craft\web\UploadedFile;
use craft\errors\UploadFailedException;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\elements\Asset;

use yii\web\NotFoundHttpException;

class PreviewImageController extends Controller
{

    protected $allowAnonymous = [];

    public function actionUploadPreviewImage()
    {
        $this->requireAcceptsJson();
        $this->requireLogin();

        $previewImageService = MatrixFieldPreview::getInstance()->previewImageService;
        $previewService = MatrixFieldPreview::getInstance()->previewService;

        $previewId = Craft::$app->getRequest()->getRequiredBodyParam('previewId');
        $preview = $previewService->getById((int) $previewId);
        if (!$preview) {
            throw new NotFoundHttpException('Invalid preview ID: ' . $previewId);
        }

        if (($file = UploadedFile::getInstanceByName('previewImage')) === null) {
            return null;
        }

        try {
            if ($file->getHasError()) {
                throw new UploadFailedException($file->error);
            }

            // Move to our own temp location
            $fileLocation = Assets::tempFilePath($file->getExtension());
            move_uploaded_file($file->tempName, $fileLocation);

            $previewImageService->savePreviewImage($fileLocation, $preview, $file->name);

            return $this->asJson([
                'html' => $this->_renderPreviewImageTemplate($preview),
            ]);
        } catch (\Throwable $exception) {
            /** @noinspection UnSafeIsSetOverArrayInspection - FP */
            if (isset($fileLocation)) {
                try {
                    FileHelper::unlink($fileLocation);
                } catch (\Throwable $e) {
                    // Let it go
                }
            }

            Craft::error('There was an error uploading the photo: ' . $exception->getMessage(), __METHOD__);

            return $this->asErrorJson(Craft::t('app', 'There was an error uploading your photo: {error}', [
                'error' => $exception->getMessage()
            ]));
        }
    }

    public function actionDeletePreviewImage()
    {
        $this->requireAcceptsJson();
        $this->requireLogin();

        $previewService = MatrixFieldPreview::getInstance()->previewService;
        $previewId = Craft::$app->getRequest()->getRequiredBodyParam('previewId');
        $preview = $previewService->getById((int) $previewId);

        if (!$preview) {
            throw new NotFoundHttpException('Invalid preview ID: ' . $previewId);
        }

        if ($preview->previewImageId) {
            Craft::$app->getElements()->deleteElementById($preview->previewImageId, Asset::class);
        }

        $preview->previewImageId = null;
        $preview->save();

        return $this->asJson([
            'html' => $this->_renderPreviewImageTemplate($preview),
        ]);
    }

    private function _renderPreviewImageTemplate($preview): string
    {
        $settings = MatrixFieldPreview::getInstance()->getSettings();

        $view = $this->getView();
        $templateMode = $view->getTemplateMode();
        return $view->renderTemplate('matrix-field-preview/_includes/preview-image', [
            'settings' => $settings,
            'preview' => $preview
        ], $templateMode);
    }
}