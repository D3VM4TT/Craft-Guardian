<?php

namespace boost\craftguardian\services;

use boost\craftguardian\records\FormTestRecord;
use Craft;
use craft\base\Component;

class FormTestService extends Component
{
    public function fetchFormFields(string $url): array
    {
        $client = Craft::createGuzzleClient();
        $response = $client->get($url);
        $html = (string)$response->getBody();

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);

        $inputs = [];
        foreach ($dom->getElementsByTagName('input') as $input) {
            $type = $input->getAttribute('type') ?: 'text';
            $name = $input->getAttribute('name');
            if ($name && $type !== 'hidden') {
                $inputs[] = [
                    'name' => $name,
                    'type' => $type,
                    'defaultValue' => $this->getDefaultValueForType($type),
                ];
            }
        }

        foreach ($dom->getElementsByTagName('textarea') as $textarea) {
            $name = $textarea->getAttribute('name');
            if ($name) {
                $inputs[] = [
                    'name' => $name,
                    'type' => 'textarea',
                    'defaultValue' => 'Test message',
                ];
            }
        }

        foreach ($dom->getElementsByTagName('select') as $select) {
            $name = $select->getAttribute('name');
            if ($name) {
                $inputs[] = [
                    'name' => $name,
                    'type' => 'select',
                    'defaultValue' => 'Option 1',
                ];
            }
        }

        return $inputs;
    }

    private function getDefaultValueForType(string $type): string
    {
        return match ($type) {
            'email' => 'test@example.com',
            'tel' => '0123456789',
            'url' => 'https://example.com',
            'number' => '123',
            default => 'Test Value',
        };
    }

    public function getAllTests()
    {
        // To implement: fetch all form tests from DB
        return [];
    }

    public function getTestById(int $id)
    {
        // To implement: fetch a single test from DB
    }

    public function saveFormTest(\boost\craftguardian\models\FormTest $formTest): bool
    {
        $record = $formTest->id ? FormTestRecord::findOne($formTest->id) : new FormTestRecord();

        $record->formName = $formTest->formName;
        $record->formUrl = $formTest->formUrl;
        $record->submitUrl = $formTest->submitUrl;
        $record->method = $formTest->method;
        $record->expectedSuccessText = $formTest->expectedSuccessText;
        $record->testFields = $formTest->testFields;
        $record->sendEmailCheck = $formTest->sendEmailCheck;
        $record->testInterval = $formTest->testInterval;
        $record->enabled = $formTest->enabled;

        return (bool)$record->save();
    }

}
