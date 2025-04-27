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

    public function actionCreate(): \yii\web\Response
    {
        $formTest = new \boost\craftguardian\models\FormTest();

        return $this->renderTemplate('craft-guardian/form-tests/edit', [
            'formTest' => $formTest,
        ]);
    }

    public function actionEdit(int $formTestId): \yii\web\Response
    {
        $formTest = Plugin::getInstance()->formTests->getTestById($formTestId);

        if (!$formTest) {
            throw new \yii\web\NotFoundHttpException('Form Test not found.');
        }

        return $this->renderTemplate('craft-guardian/form-tests/edit', [
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
            'testFields' => json_decode($request->getBodyParam('testFieldsJson'), true) ?? [],
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

    public function actionRunTest(): \yii\web\Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $formTestId = $request->getRequiredBodyParam('id');

        $result = Plugin::getInstance()->formTests->runTestById($formTestId);

        if ($result['success']) {
            Craft::$app->getSession()->setNotice('Form test ran successfully.');
        } else {
            Craft::$app->getSession()->setError('Form test failed: ' . $result['message']);
        }

        return $this->redirect('craft-guardian/form-tests');
    }


}
