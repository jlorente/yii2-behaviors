<?php

/**
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @license     The MIT License (MIT)
 * @copyright   José Lorente
 * @version     1.0
 */

namespace jlorente\behaviors;

use Closure;
use yii\base\Event;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecordInterface;

/**
 * EnhancedAttributeBehavior automatically assigns a specified value to one or multiple attributes 
 * of an ActiveRecord object when certain events happen. It can be used to update
 * attributes of another active records related the behavior owner.
 *
 * To use EnhancedAttributeBehavior, configure the [[attributes]] property which should specify the list of attributes
 * that need to be updated and the corresponding events that should trigger the update. For example,
 * Then configure the [[value]] property with a PHP callable whose return value will be used to assign to the current
 * attribute(s). For example,
 *
 * ~~~
 * use custom\behaviors\EnhancedAttributeBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => EnhancedAttributeBehavior::className(),
 *             'foreignClass' => [ForeignClass::className(), 'id' => 'question_id']
 *             'attributes' => [
 *                 ActiveRecord::EVENT_BEFORE_INSERT => 'attribute1',
 *                 ActiveRecord::EVENT_BEFORE_UPDATE => 'attribute2',
 *             ],
 *             'value' => function ($event) {
 *                 return 'some value';
 *             },
 *         ],
 *     ];
 * }
 * ~~~
 *
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @since 2.0
 */
class EnhancedAttributeBehavior extends AttributeBehavior {

    /**
     * @var array with the following structure
     *
     * ```php
     * [
     *     'ForeignObjectFullClassName',
     *     [
     *          'foreignObjectAttributeName' => 'ownerReferenceAttributeName'
     *     ]
     * ]
     * ```
     * 
     * Where 'ForeignObjectFullClassName' can be obtained using the Object::className() method
     * and the second parameter is the primary-foreign key constraint. The keys of the array refer to
     * the attributes of the record associated with the `$class` model, while the values of the
     * array refer to the corresponding attributes in **this** AR class.
     */
    public $foreignClass;

    /**
     * Evaluates the attribute value and assigns it to the current attributes. If no foreignClass 
     * is specified, it behaves like the normal AttributeBehavior.
     * @param Event $event
     */
    public function evaluateAttributes($event) {
        if ($this->foreignClass === null) {
            parent::evaluateAttributes($event);
        } elseif (!empty($this->attributes[$event->name])) {
            $fObjects = $this->getForeignObjects($this->foreignClass[0], $this->foreignClass[1]);
            $attributes = (array) $this->attributes[$event->name];
            $value = $this->getValue($event);
            $uAttributes = [];
            $n = count($fObjects);
            foreach ($attributes as $attribute) {
                for ($i = 0; $i < $n; ++$i) {
                    $fObjects[$i]->$attribute = $value;
                }
                $uAttributes[] = $attribute;
            }
            foreach ($fObjects as $fObject) {
                $fObject->save(false, $uAttributes);
            }
        }
    }

    /**
     * Returns the value of the current attributes.
     * This method is called by [[evaluateAttributes()]]. Its return value will be assigned
     * to the attributes corresponding to the triggering event.
     * @param Event $event the event that triggers the current attribute updating.
     * @return mixed the attribute value
     */
    protected function getValue($event) {
        return $this->value instanceof Closure ? call_user_func($this->value, $event) : $this->value;
    }

    /**
     * Return the foreign objects obtained by the given relation.
     * 
     * @param string $class
     * @param array $relation
     * @return ActiveRecordInterface
     */
    protected function getForeignObjects($class, $relation) {
        $k = key($relation);
        $s = [$k => $this->owner->{$relation[$k]}];
        return $class::findAll($s);
    }

}
