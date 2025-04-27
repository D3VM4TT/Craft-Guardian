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
}
