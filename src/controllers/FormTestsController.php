<?php

namespace boost\craftguardian\controllers;

use Craft;
use craft\web\Controller;
use boost\craftguardian\Plugin;

class FormTestsController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex(): \yii\web\Response
    {
        $tests = Plugin::getInstance()->formTests->getAllTests();
        return $this->renderTemplate(Plugin::HANDLE . '/form-tests/index', [
            'tests' => $tests,
        ]);
    }

    public function actionEdit(int $formTestId = null): \yii\web\Response
    {
        $formTest = $formTestId ? Plugin::getInstance()->formTests->getTestById($formTestId) : null;
        return $this->renderTemplate(Plugin::HANDLE . '/form-tests/edit', [
            'formTest' => $formTest,
        ]);
    }

    public function actionSave(): \yii\web\Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $formTest = new \boost\craftguardian\models\FormTest([
            'id' => $request->getBodyParam('id'),
            'formName' => $request->getBodyParam('formName'),
            'formUrl' => $request->getBodyParam('formUrl'),
            'expectedSuccessText' => $request->getBodyParam('expectedSuccessText'),
            'testFields' => $request->getBodyParam('testFields', []),
            'sendEmailCheck' => (bool)$request->getBodyParam('sendEmailCheck'),
            'testInterval' => (int)$request->getBodyParam('testInterval'),
            'enabled' => (bool)$request->getBodyParam('enabled'),
        ]);

        if (\boost\craftguardian\Plugin::getInstance()->formTests->saveFormTest($formTest)) {
            Craft::$app->getSession()->setNotice('Form test saved.');
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError('Could not save form test.');
        Craft::$app->getUrlManager()->setRouteParams([
            'formTest' => $formTest,
        ]);

        return $this->redirectToPostedUrl();
    }

}
