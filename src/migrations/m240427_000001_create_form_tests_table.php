<?php

namespace boost\craftguardian\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240427_000001_create_form_tests_table migration.
 */
class m240427_000001_create_form_tests_table extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%form_tests}}', [
            'id' => $this->primaryKey(),
            'formName' => $this->string()->notNull(),
            'formUrl' => $this->string()->notNull(),
            'submitUrl' => $this->string()->null(),
            'method' => $this->string(10)->notNull()->defaultValue('POST'),
            'expectedSuccessText' => $this->string()->notNull(),
            'testFields' => $this->json()->notNull(),
            'sendEmailCheck' => $this->boolean()->defaultValue(false),
            'testInterval' => $this->integer()->defaultValue(30),
            'lastRunAt' => $this->dateTime()->null(),
            'nextRunAt' => $this->dateTime()->null(),
            'enabled' => $this->boolean()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%form_tests}}');

        return true;
    }
}
