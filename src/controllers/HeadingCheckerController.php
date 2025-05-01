<?php

namespace boost\craftguardian\controllers;

use Craft;
use craft\web\Controller;
use boost\craftguardian\Plugin;

class HeadingCheckerController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex(): \yii\web\Response
    {
        $results = Plugin::getInstance()->headingChecker->checkAll();

        return $this->renderTemplate('craft-guardian/heading-checker/index', [
            'results' => $results,
        ]);
    }

    public function actionScan(): \yii\web\Response
    {
        $this->requirePostRequest();

        $results = Plugin::getInstance()->headingChecker->checkAll();

        return $this->renderTemplate('craft-guardian/heading-checker/index', [
            'results' => $results,
        ]);
    }

}
