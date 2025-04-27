<?php

namespace boost\craftguardian;

use boost\craftguardian\services\FormTestService;
use Craft;
use boost\craftguardian\models\Settings;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;


/**
 * Craft Guardian plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Matthew De Jager <matthewdejager5@gmail.com>
 * @copyright Matthew De Jager
 * @license https://craftcms.github.io/license/ Craft License
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;
    const string HANDLE = 'craft-guardian';


    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // REGISTER SERVICES
            $this->setComponents([
                'formTests' => FormTestService::class
            ]);
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(self::HANDLE . '/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(

                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $event->rules[self::HANDLE . '/form-tests/save'] = self::HANDLE . '/form-tests/save';
                    $event->rules[self::HANDLE . '/form-tests/run-test'] = self::HANDLE . '/form-tests/run-test';
                    $event->rules[self::HANDLE . '/form-tests'] = self::HANDLE . '/form-tests/index';
                    $event->rules[self::HANDLE . '/form-tests/new'] = self::HANDLE . '/form-tests/edit';
                    $event->rules[self::HANDLE . '/form-tests/<formTestId:\d+>'] = self::HANDLE . '/form-tests/edit';
                }
            );
        }
    }

    public function getCpNavItem(): array
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = 'Craft Guardian';
        $navItem['subnav'] = [
            'form-tests' => ['label' => 'Form Tests', 'url' => self::HANDLE . '/form-tests'],
        ];
        return $navItem;
    }
}
