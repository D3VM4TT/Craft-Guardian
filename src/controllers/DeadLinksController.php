<?php

namespace boost\craftguardian\controllers;

use Craft;
use craft\web\Controller;
use boost\craftguardian\Plugin;
use yii\web\Response;

class DeadLinksController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex(): \yii\web\Response
    {
        $results = Plugin::getInstance()->deadLinks->getLastResults();

        return $this->renderTemplate('craft-guardian/dead-links/index', [
            'results' => $results,
        ]);
    }

    public function actionScan(): Response
    {
        $this->requirePostRequest();

        $results = Plugin::getInstance()->deadLinks->scanAllEntries();

        return $this->renderTemplate('craft-guardian/dead-links/index', [
            'results' => $results,
        ]);
    }
}
