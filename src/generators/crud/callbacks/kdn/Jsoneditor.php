<?php
/**
 * Created by PhpStorm.
 * User: tobias
 * Date: 09.06.15
 * Time: 22:40.
 */
namespace schmunk42\giiant\generators\crud\callbacks\kdn;

use yii\helpers\Inflector;

class Jsoneditor
{
    public static function field()
    {
        return function ($attribute) {
            $editor = \kdn\yii2\JsonEditor::class;
            $label = Inflector::camelize($attribute);

            return <<<FORMAT
'<div class="form-group field-widget-{$attribute}"><label class="control-label col-sm-2">{$label}</label><div class="col-sm-8">'.
{$editor}::widget(
    [
        'clientOptions' => [
            'modes' => ['code', 'form', 'text', 'tree', 'view'],
            'mode'  => 'tree',
        ],
        'model' => \$model,
        'attribute' => '{$attribute}',
        'options' => [
            'id' => 'widget-{$attribute}',
            'class' => 'form-control',
        ]
    ]
).
'</div></div>'
FORMAT;
        };
    }

    public static function attribute()
    {
        return function ($attribute, $generator) {
            $method = __METHOD__;
            $editor = \kdn\yii2\JsonEditor::class;
            return <<<FORMAT
// generated by {$method}
[
    'format' => 'raw',
    'attribute' => '{$attribute}',
    'headerOptions' => ['style' => 'min-width: 600px'],
    'value'=> function (\$model) {
        return {$editor}::widget([
            'name' => '_display',
            'value' => \$model->{$attribute},
            'clientOptions' => [
                'mode' => 'view',
                'modes' => [
                    'view',
                    'code'
                ]
            ]
        ]);
    }
]
FORMAT;
        };
    }
}
