<?php

namespace boost\craftguardian\models;

use craft\base\Model;

class FormTest extends Model
{
    public ?int $id = null;
    public string $formName = '';
    public string $formUrl = '';
    public ?string $submitUrl = null;
    public string $method = 'POST';
    public string $expectedSuccessText = '';
    public array $testFields = [];
    public bool $sendEmailCheck = false;
    public int $testInterval = 30;
    public ?\DateTime $lastRunAt = null;
    public ?\DateTime $nextRunAt = null;
    public bool $enabled = true;
}
