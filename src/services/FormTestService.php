<?php

namespace boost\craftguardian\services;

use boost\craftguardian\models\FormTest;
use boost\craftguardian\records\FormTestRecord;
use Craft;
use craft\base\Component;

class FormTestService extends Component
{
    public function getAllTests(): array
    {
        $records = FormTestRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        return array_map(function(FormTestRecord $record) {
            return $this->createModelFromRecord($record);
        }, $records);
    }

    public function getTestById(int $id): ?FormTest
    {
        $record = FormTestRecord::findOne($id);

        if (!$record) {
            return null;
        }

        return $this->createModelFromRecord($record);
    }

    public function saveFormTest(FormTest $formTest): bool
    {
        $record = $formTest->id ? FormTestRecord::findOne($formTest->id) : new FormTestRecord();

        if (!$record) {
            $record = new FormTestRecord();
        }

        $record->formName = $formTest->formName;
        $record->formUrl = $formTest->formUrl;
        $record->submitUrl = $formTest->submitUrl;
        $record->method = $formTest->method;
        $record->expectedSuccessText = $formTest->expectedSuccessText;
        $record->testFields = $formTest->testFields;
        $record->sendEmailCheck = $formTest->sendEmailCheck;
        $record->testInterval = $formTest->testInterval;
        $record->enabled = $formTest->enabled;

        return (bool) $record->save();
    }

    private function createModelFromRecord(FormTestRecord $record): FormTest
    {
        return new FormTest([
            'id' => $record->id,
            'formName' => $record->formName,
            'formUrl' => $record->formUrl,
            'submitUrl' => $record->submitUrl,
            'method' => $record->method,
            'expectedSuccessText' => $record->expectedSuccessText,
            'testFields' => $record->testFields ?? [],
            'sendEmailCheck' => (bool) $record->sendEmailCheck,
            'testInterval' => (int) $record->testInterval,
            'lastRunAt' => $record->lastRunAt,
            'nextRunAt' => $record->nextRunAt,
            'enabled' => (bool) $record->enabled,
        ]);
    }

    public function runTestById(int $id): array
    {
        $formTest = $this->getTestById($id);

        if (!$formTest) {
            return ['success' => false, 'message' => 'Form Test not found.'];
        }

        try {
            $client = Craft::createGuzzleClient([
                'allow_redirects' => true,
                'cookies' => true,
            ]);

            $response = $client->request('GET', $formTest->formUrl);
            $html = (string)$response->getBody();

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $form = $xpath->query('//form')->item(0);

            if (!$form) {
                return ['success' => false, 'message' => 'No form element found on page.'];
            }

            $postData = [];

            foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if ($name) {
                    $postData[$name] = $value;
                }
            }

            foreach ($xpath->query('.//input[not(@type="hidden")]', $form) as $input) {
                $name = $input->getAttribute('name');
                if ($name && array_key_exists($name, $formTest->testFields)) {
                    $postData[$name] = $formTest->testFields[$name];
                }
            }

            foreach ($xpath->query('.//textarea', $form) as $textarea) {
                $name = $textarea->getAttribute('name');
                if ($name && array_key_exists($name, $formTest->testFields)) {
                    $postData[$name] = $formTest->testFields[$name];
                }
            }

            $formAction = $form->getAttribute('action') ?: $formTest->formUrl;
            if (!str_starts_with($formAction, 'http')) {
                $parsed = parse_url($formTest->formUrl);
                $formAction = $parsed['scheme'] . '://' . $parsed['host'] . '/' . ltrim($formAction, '/');
            }

            $formMethod = strtoupper($form->getAttribute('method') ?: 'POST');

            $submitResponse = $client->request($formMethod, $formAction, [
                'form_params' => $postData,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $submitBody = (string)$submitResponse->getBody();
            $statusCode = $submitResponse->getStatusCode();

            // --- CLEAN, GENERIC, CORRECT CHECKS ---

            // 1. Status code must be 2xx
            if ($statusCode < 200 || $statusCode >= 300) {
                return ['success' => false, 'message' => "Form submission failed with HTTP status $statusCode."];
            }

            // 2. If Expected Success Text is configured, it must be found
            if (!empty($formTest->expectedSuccessText)) {
                if (!str_contains($submitBody, $formTest->expectedSuccessText)) {
                    return ['success' => false, 'message' => "Expected success text not found in response."];
                }
            }

            // 3. If we have no Expected Success Text, just accept 2xx as success
            return ['success' => true];

        } catch (\Throwable $e) {
            Craft::error('Error running form test: ' . $e->getMessage(), __METHOD__);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

}
