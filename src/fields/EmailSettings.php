<?php

namespace webdna\craftemailcontenteditor\fields;

use webdna\craftemailcontenteditor\EmailContentEditor;
use webdna\craftemailcontenteditor\models\EmailSettings as ModelsEmailSettings;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use yii\db\Schema;

/**
 * Email Settings field type
 */
class EmailSettings extends Field
{

    public string $messageKey = '';
    public string $subject = '';
    public string $testVariables = '';
    public ?int $testUserId = null;
    public array $testOrderId = [];

    public static function displayName(): string
    {
        return Craft::t('email-content-editor', 'Email Settings');
    }

    public static function valueType(): string
    {
        return 'mixed';
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function getContentColumnType(): array|string
    {
        return Schema::TYPE_TEXT;
    }

    public function normalizeValue(mixed $value, ElementInterface $element = null): mixed
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        return $value;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $systemMessages = collect(Craft::$app->getSystemMessages()->getAllMessages())->map( function ($m) { $m['heading'] = str_replace(':','',$m['heading']); return $m;})->pluck('heading', 'key')->all();
        $commerceEmails = EmailContentEditor::getInstance()->emails->getAllCommerceEmails();
        $messageOptions = array_merge($systemMessages,$commerceEmails);
        $view = Craft::$app->getView();
        // Register our asset bundle
        // $view->registerAssetBundle(FieldAsset::class);

        // Get our id and namespace
        $id = $view->formatInputId($this->handle);
        $namespacedId = $view->namespaceInputId($id);

        // Variables to pass down to our field JavaScript to let it namespace properly
        // $jsonVars = [
        //     'id' => $id,
        //     'name' => $this->handle,
        //     'namespace' => $namespacedId,
        //     'prefix' => $view->namespaceInputId(''),
        //     ];
        // $jsonVars = Json::encode($jsonVars);
        // $view->registerJs("$('#{$namespacedId}-field').ListingSourceField(" . $jsonVars . ");");
        $values = new ModelsEmailSettings($value ?? []);

        // Render the input template
        return $view->renderTemplate(
            'email-content-editor/settings-field',
            [
                'name' => $this->handle,
                'model' => $value,
                'subject' => $values->subject,
                'messageKey' => $values->messageKey,
                'testVariables' => $values->testVariables,
                'testOrder' => $values->getTestOrder(),
                'options' => $messageOptions,
                'element' => $element,
                'field' => $this,
                'id' => $id,
                'namespacedId' => $namespacedId,
            ]
        );
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return StringHelper::toString($value, ' ');
    }

    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }

    public function modifyElementsQuery(ElementQueryInterface $query, mixed $value): void
    {
        parent::modifyElementsQuery($query, $value);
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return Craft::getAlias('@webdna/craftemailcontenteditor/icon-mask.svg');
    }
    
}
