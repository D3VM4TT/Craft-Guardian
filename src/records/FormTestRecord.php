<?php

namespace boost\craftguardian\records;

use craft\db\ActiveRecord;

class FormTestRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%form_tests}}';
    }
}