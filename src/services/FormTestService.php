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
            // Step 1: Fetch the form page
            $client = Craft::createGuzzleClient([
                'allow_redirects' => true,
                'cookies' => true,
            ]);

            $response = $client->request('GET', $formTest->formUrl);
            $html = (string)$response->getBody();

            // Step 2: Parse form
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);

            $xpath = new \DOMXPath($dom);
            $form = $xpath->query('//form')->item(0);

            if (!$form) {
                return ['success' => false, 'message' => 'No form found on page.'];
            }

            // Step 3: Collect fields
            $postData = [];

            // All hidden inputs
            foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if ($name) {
                    $postData[$name] = $value;
                }
            }

            // Normal inputs
            foreach ($xpath->query('.//input[not(@type="hidden")]', $form) as $input) {
                $name = $input->getAttribute('name');
                if ($name && array_key_exists($name, $formTest->testFields)) {
                    $postData[$name] = $formTest->testFields[$name];
                }
            }

            // Textareas
            foreach ($xpath->query('.//textarea', $form) as $textarea) {
                $name = $textarea->getAttribute('name');
                if ($name && array_key_exists($name, $formTest->testFields)) {
                    $postData[$name] = $formTest->testFields[$name];
                }
            }

            // Checkboxes
            foreach ($xpath->query('.//input[@type="checkbox"]', $form) as $checkbox) {
                $name = $checkbox->getAttribute('name');
                if ($name && array_key_exists($name, $formTest->testFields)) {
                    $postData[$name] = $formTest->testFields[$name];
                }
            }

            // Step 4: Figure out where to POST
            $formAction = $form->getAttribute('action') ?: $formTest->formUrl;
            if (!str_starts_with($formAction, 'http')) {
                $parsed = parse_url($formTest->formUrl);
                $formAction = $parsed['scheme'] . '://' . $parsed['host'] . '/' . ltrim($formAction, '/');
            }

            $formMethod = strtoupper($form->getAttribute('method') ?: 'POST');

            // Step 5: POST the form
            $submitResponse = $client->request($formMethod, $formAction, [
                'form_params' => $postData,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $submitBody = (string)$submitResponse->getBody();

            // Step 6: Check for success
            if (str_contains($submitBody, $formTest->expectedSuccessText)) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Success text not found after form submission.'];

        } catch (\Throwable $e) {
            Craft::error('Error running form test: ' . $e->getMessage(), __METHOD__);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }



}
