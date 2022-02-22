<?php
/**
 * Created by PhpStorm.
 * User: tobias
 * Date: 14.03.14
 * Time: 10:21.
 */

namespace schmunk42\giiant\generators\crud\providers\core;

use schmunk42\giiant\generators\model\Generator as ModelGenerator;
use yii\db\ActiveRecord;
use yii\db\ColumnSchema;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

class RelationProvider extends \schmunk42\giiant\base\Provider
{
    /**
     * @var null can be null (default) or `select2`
     */
    public $inputWidget = null;

    /**
     * @var bool wheter to skip non-existing columns in relation grid
     *
     * @since 0.6
     */
    public $skipVirtualAttributes = false;

    /**
     * @var bool generate dropdown filter in GridView on related fields values
     *
     * @since 0.11
     */
    public $gridFilterDropdownRelation = false;

    /**
     * Formatter for relation form inputs.
     *
     * Renders a drop-down list for a `hasOne`/`belongsTo` relation
     *
     * @param $column
     *
     * @return null|string
     */
    public function activeField($attribute)
    {
        $column = $this->generator->getColumnByAttribute($attribute);
        if (!$column) {
            return;
        }

        // TODO: NoSQL hotfix
        if (is_string($column)) {
            return null;
        }
        $relation = $this->generator->getRelationByColumn($this->generator->modelClass, $column,
            ['belongs_to', 'has_one']);
        if ($relation) {
            switch (true) {
                case !$relation->multiple:
                    $pk = key($relation->link);
                    $name = $this->generator->getModelNameAttribute($relation->modelClass);
                    $method = __METHOD__;
                    switch ($this->inputWidget) {
                        case 'select2':
                            $code = <<<EOS
// generated by {$method}
\$form->field(\$model, '{$column->name}')->widget(\kartik\select2\Select2::classname(), [
    'name' => 'class_name',
    'model' => \$model,
    'attribute' => '{$column->name}',
    'data' => \yii\helpers\ArrayHelper::map({$relation->modelClass}::find()->all(), '{$pk}', '{$name}'),
    'options' => [
        'placeholder' => {$this->generator->generateString('Type to autocomplete')},
        'multiple' => false,
        'disabled' => (isset(\$relAttributes) && isset(\$relAttributes['{$column->name}'])),
    ]
]);
EOS;
                            break;
                        default:
                            $code = <<<EOS
// generated by {$method}
\$form->field(\$model, '{$column->name}')->dropDownList(
    \yii\helpers\ArrayHelper::map({$relation->modelClass}::find()->all(), '{$pk}', '{$name}'),
    [
        'prompt' => {$this->generator->generateString('Select')},
        'disabled' => (isset(\$relAttributes) && isset(\$relAttributes['{$column->name}'])),
    ]
);
EOS;
                            break;
                    }

                    return $code;
                default:
                    return;

            }
        }
    }

    /**
     * Formatter for detail view relation attributes.
     *
     * Renders a link to the related detail view
     *
     * @param $column ColumnSchema
     *
     * @return null|string
     */
    public function attributeFormat($attribute)
    {
        $column = $this->generator->getColumnByAttribute($attribute);
        if (!$column) {
            return;
        }

        // TODO: NoSQL hotfix
        if (is_string($column)) {
            return "'$column'";
        }

        // handle columns with a primary key, to create links in pivot tables (changed at 0.3-dev; 03.02.2015)
        // TODO double check with primary keys not named `id` of non-pivot tables
        // TODO Note: condition does not apply in every case
        if ($column->isPrimaryKey) {
            //return null; #TODO: double check with primary keys not named `id` of non-pivot tables
        }

        $relation = $this->generator->getRelationByColumn($this->generator->modelClass, $column,
            ['belongs_to', 'has_one']);
        if ($relation) {
            if ($relation->multiple) {
                return;
            }
            $title = $this->generator->getModelNameAttribute($relation->modelClass);
            $route = $this->generator->createRelationRoute($relation, 'view');

            // prepare URLs
            $routeAttach = 'create';
            $routeIndex = $this->generator->createRelationRoute($relation, 'index');

            $modelClass = $this->generator->modelClass;
            $relationProperty = lcfirst((new ModelGenerator([
                'disablePluralization' => $this->generator->disablePluralization
            ]))->generateRelationName(
                [$relation],
                $modelClass::getTableSchema(),
                $column->name,
                $relation->multiple
            ));
            $relationModel = new $relation->modelClass();
            $relationModelName = StringHelper::basename($modelClass);
            $pks = $relationModel->primaryKey();
            $paramArrayItems = '';
            foreach ($pks as $attr) {
                $paramArrayItems .= "'{$attr}' => \$model->{$relationProperty}->{$attr},";
            }
            $attachArrayItems = "'{$relationModelName}'=>['{$column->name}' => \$model->{$column->name}]";

            $method = __METHOD__;
            $code = <<<EOS
// generated by {$method}
[
    'format' => 'html',
    'attribute' => '$column->name',
    'value' => (\$model->{$relationProperty} ? 
        Html::a('<i class="glyphicon glyphicon-list"></i>', ['{$routeIndex}']).' '.
        Html::a('<i class="glyphicon glyphicon-circle-arrow-right"></i> '.\$model->{$relationProperty}->{$title}, ['{$route}', {$paramArrayItems}]).' '.
        Html::a('<i class="glyphicon glyphicon-paperclip"></i>', ['{$routeAttach}', {$attachArrayItems}])
        : 
        '<span class="label label-warning">?</span>'),
]
EOS;

            return $code;
        }
    }

    /**
     * Formatter for relation grid columns.
     *
     * Renders a link to the related detail view
     *
     * @param $column ColumnSchema
     * @param $model ActiveRecord
     *
     * @return null|string
     */
    public function columnFormat($attribute, $model)
    {
        $column = $this->generator->getColumnByAttribute($attribute, $model);
        if (!$column) {
            return;
        }

        // TODO: NoSQL hotfix
        if (is_string($column)) {
            return $column;
        }

        // handle columns with a primary key, to create links in pivot tables (changed at 0.3-dev; 03.02.2015)
        // TODO double check with primary keys not named `id` of non-pivot tables
        // TODO Note: condition does not apply in every case
        if ($column->isPrimaryKey) {
            //return null;
        }

        $relation = $this->generator->getRelationByColumn($model, $column, ['belongs_to', 'has_one']);
        if ($relation) {
            if ($relation->multiple) {
                return;
            }
            $title = $this->generator->getModelNameAttribute($relation->modelClass);
            $route = $this->generator->createRelationRoute($relation, 'view');
            $method = __METHOD__;
            $modelClass = $this->generator->modelClass;
            $relationProperty = lcfirst((new ModelGenerator())->generateRelationName(
                [$relation],
                $modelClass::getTableSchema(),
                $column->name,
                $relation->multiple
            ));
            $relationModel = new $relation->modelClass();
            $pks = $relationModel->primaryKey();
            $paramArrayItems = '';

            foreach ($pks as $attr) {
                $paramArrayItems .= "'{$attr}' => \$rel->{$attr},";
            }

            $filter = '';
            //params for filter
            if ($this->gridFilterDropdownRelation) {
                $name = $this->generator->getModelNameAttribute($relation->modelClass);
                $pk = key($relation->link);

                $filter = "\n'filter' => \yii\helpers\ArrayHelper::map({$relation->modelClass}::find()->all(), '{$pk}', '{$name}'),";
            }

            $code = <<<EOS
// generated by {$method}
[
    'class' => yii\\grid\\DataColumn::className(),
    'attribute' => '{$column->name}',
    'value' => function (\$model) {
        if (\$rel = \$model->{$relationProperty}) {
            return Html::a(\$rel->{$title}, ['{$route}', {$paramArrayItems}], ['data-pjax' => 0]);
        } else {
            return '';
        }
    },{$filter}
    'format' => 'raw',
]
EOS;

            return $code;
        }
    }

    /**
     * Renders a grid view for a given relation.
     *
     * @param $name
     * @param $relation
     * @param bool $showAllRecords
     *
     * @return mixed|string
     */
    public function relationGrid($name, $relation, $showAllRecords = false)
    {
        $model = new $relation->modelClass();

        // column counter
        $counter = 0;
        $columns = '';

        if (!$this->generator->isPivotRelation($relation)) {
            // hasMany relations
            $template = '{view} {update}';
            $deleteButtonPivot = '';
        } else {
            // manyMany relations
            $template = '{view} {delete}';
            $deleteButtonPivot = <<<EOS
'delete' => function (\$url, \$model) {
                return Html::a('<span class="glyphicon glyphicon-remove"></span>', \$url, [
                    'class' => 'text-danger',
                    'title'         => {$this->generator->generateString('Remove')},
                    'data-confirm'  => {$this->generator->generateString(
                'Are you sure you want to delete the related item?'
            )},
                    'data-method' => 'post',
                    'data-pjax' => '0',
                ]);
            },
'view' => function (\$url, \$model) {
                return Html::a(
                    '<span class="glyphicon glyphicon-cog"></span>',
                    \$url,
                    [
                        'data-title'  => {$this->generator->generateString('View Pivot Record')},
                        'data-toggle' => 'tooltip',
                        'data-pjax'   => '0',
                        'class'       => 'text-muted',
                    ]
                );
            },
EOS;
        }

        $reflection = new \ReflectionClass($relation->modelClass);
        $controller = $this->generator->pathPrefix . Inflector::camel2id($reflection->getShortName(), '-', true);
        $relKey = key($relation->link);
        $actionColumn = <<<EOS
[
    'class'      => '{$this->generator->actionButtonClass}',
    'template'   => '$template',
    'contentOptions' => ['nowrap'=>'nowrap'],
    'urlCreator' => function (\$action, \$model, \$key) {
        // using the column name as key, not mapping to 'id' like the standard generator
        \$params = is_array(\$key) ? \$key : [\$model->primaryKey()[0] => (string) \$key];
        \$params[0] = '$controller' . '/' . \$action;
        \$params['{$model->formName()}'] = ['$relKey' => \$model->primaryKey()[0]];
        return \$params;
    },
    'buttons'    => [
        $deleteButtonPivot
    ],
    'controller' => '$controller'
]
EOS;

        // add action column
        if ($this->generator->actionButtonColumnPosition !== 'right') {
            $columns .= $actionColumn . ",\n";
        }

        // prepare grid column formatters
        if (array_key_exists('crud-relation-list', $model->scenarios())) {
            $model->setScenario('crud-relation-list');
        } else if (array_key_exists('crud-list', $model->scenarios())) {
            $model->setScenario('crud-list');
        } else {
            $model->setScenario('crud');
        }
        $safeAttributes = $model->safeAttributes();
        if (empty($safeAttributes)) {
            $safeAttributes = $model->getTableSchema()->columnNames;
        }
        foreach ($safeAttributes as $attr) {

            // max defined amount of columns
            if ($counter > $this->generator->gridRelationMaxColumns) {
                continue;
            }
            // skip virtual attributes
            if ($this->skipVirtualAttributes && !isset($model->tableSchema->columns[$attr])) {
                continue;
            }
            // don't show current model
            if (key($relation->link) === $attr) {
                continue;
            }

            $code = $this->generator->columnFormat($attr, $model);
            if ($code === false) {
                continue;
            }
            $columns .= $code . ",\n";
            ++$counter;
        }

        if ($this->generator->actionButtonColumnPosition === 'right') {
            $columns .= $actionColumn . ",\n";
        }

        $query = $showAllRecords ?
            "'query' => \\{$relation->modelClass}::find()" :
            "'query' => \$model->get{$name}()";
        $pageParam = Inflector::slug("page-{$name}");
        $firstPageLabel = $this->generator->generateString('First');
        $lastPageLabel = $this->generator->generateString('Last');
        $code = "'<div class=\"table-responsive\">'\n . ";
        $code .= <<<EOS
\\yii\\grid\\GridView::widget([
    'layout' => '{summary}<div class="text-center">{pager}</div>{items}<div class="text-center">{pager}</div>',
    'dataProvider' => new \\yii\\data\\ActiveDataProvider([
        {$query},
        'pagination' => [
            'pageSize' => 20,
            'pageParam'=>'{$pageParam}',
        ]
    ]),
    'pager'        => [
        'class'          => yii\widgets\LinkPager::className(),
        'firstPageLabel' => {$firstPageLabel},
        'lastPageLabel'  => {$lastPageLabel}
    ],
    'columns' => [\n $columns]
])
EOS;
        $code .= "\n . '</div>' ";

        return $code;
    }
}
