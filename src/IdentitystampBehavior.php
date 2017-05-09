<?php

/**
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @license     The MIT License (MIT)
 * @copyright   José Lorente
 * @version     1.0
 */

namespace jlorente\behaviors;

use yii\db\BaseActiveRecord;
use yii\base\InvalidCallException;
use yii\behaviors\AttributeBehavior;

/**
 * IdentitystampBehavior automatically fills the specified attributes with the current identitystamp.
 *
 * To use IdentitystampBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use jlorente\behaviors\IdentitystampBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         IdentitystampBehavior::className(),
 *     ];
 * }
 * ```
 *
 * By default, IdentitystampBehavior will fill the `created_by` and `updated_by` 
 * attributes with the current identitystamp when the associated AR object is 
 * being inserted; it will fill the `updated_by` attribute with the 
 * identitystamp when the AR object is being updated. The identitystamp value is 
 * obtained by `\yii\web\IdentityInterface::getId()`.
 *
 * Because attribute values will be set automatically by this behavior, they are 
 * usually not user input and should therefore not be validated, i.e. 
 * `created_by` and `updated_by` should not appear in the 
 * [[\yii\base\Model::rules()|rules()]] method of the model.
 *
 * For the above implementation to work with MySQL database, please declare the 
 * columns(`created_by`, `updated_by`) as the same column type of the returned 
 * getValue() method. Remember that if property [[value]] is left to null, the 
 * identitystamp value will be obtained by `\yii\web\IdentityInterface::getId()`
 * , so you will have to declare the types of columns as this value.
 *
 * If your attribute names are different or you want to use a different way of 
 * calculating the identitystamp, you may configure the [[createdAtAttribute]], 
 * [[updatedAtAttribute]] and [[value]] properties like the following:
 *
 * ```php
 * use yii\db\Expression;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => IdentitystampBehavior::className(),
 *             'createdByAttribute' => 'creator_id',
 *             'updatedByAttribute' => 'updator_id',
 *             'value' => function() { return User::findOne(['myIdentity' => 'myIdentity'])->id },
 *         ],
 *     ];
 * }
 * ```
 *
 * In case you use an [[\yii\db\Expression]], the attribute will not hold the 
 * identitystamp value, but the Expression object itself after the record has 
 * been saved. If you need the value from DB afterwards you should call the 
 * [[\yii\db\ActiveRecord::refresh()|refresh()]] method of the record.
 *
 * IdentitystampBehavior also provides a method named [[touch()]] that allows 
 * you to assign the current identitystamp to the specified attribute(s) and 
 * save them to the database. For example,
 *
 * ```php
 * $model->touch('creator_id');
 * ```
 *
 * @author José Lorente <jose.lorente.martin@gmail.com>
 * @since 2.0
 */
class IdentitystampBehavior extends AttributeBehavior {

    /**
     * @var string the attribute that will receive identitystamp value
     * Set this property to false if you do not want to record the creator id.
     */
    public $createdByAttribute = 'created_by';

    /**
     * @var string the attribute that will receive identitystamp value.
     * Set this property to false if you do not want to record the updator.
     */
    public $updatedByAttribute = 'updated_by';

    /**
     * @inheritdoc
     *
     * In case, when the value is `null`, the result of the 
     * \yii\web\IdentityInterface::getId() method will be used as value. If the 
     * behavior is been used in a non web application or the user isn't logged 
     * in, null will be returned.
     * @see \yii\web\IdentityInterface
     */
    public $value;

    /**
     * @inheritdoc
     */
    public function init() {
        parent::init();

        if (empty($this->attributes)) {
            $this->attributes = [
                BaseActiveRecord::EVENT_BEFORE_INSERT => [$this->createdByAttribute, $this->updatedByAttribute],
                BaseActiveRecord::EVENT_BEFORE_UPDATE => $this->updatedByAttribute,
            ];
        }
    }

    /**
     * @inheritdoc
     *
     * In case, when the value is `null`, the result of the 
     * \yii\web\IdentityInterface::getId() method will be used as value. If the 
     * behavior is been used in a non web application or the user isn't logged 
     * in, null will be returned.
     * @see \yii\web\IdentityInterface
     */
    protected function getValue($event) {
        if ($this->value === null) {
            return isset(Yii::$app->user) ? Yii::$app->user->getId() : null;
        }
        return parent::getValue($event);
    }

    /**
     * Updates a identitystamp attribute to the current identitystamp.
     *
     * ```php
     * $model->touch('lastVisit');
     * ```
     * @param string $attribute the name of the attribute to update.
     * @throws InvalidCallException if owner is a new record (since version 2.0.6).
     */
    public function touch($attribute) {
        /* @var $owner BaseActiveRecord */
        $owner = $this->owner;
        if ($owner->getIsNewRecord()) {
            throw new InvalidCallException('Updating the identitystamp is not possible on a new record.');
        }
        $owner->updateAttributes(array_fill_keys((array) $attribute, $this->getValue(null)));
    }

}
