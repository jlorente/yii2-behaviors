<?php

/**
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @license     The MIT License (MIT)
 * @copyright   José Lorente
 * @version     1.0
 */

namespace jlorente\behaviors;

use Ramsey\Uuid\Uuid;
use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\BaseActiveRecord;
use yii\validators\UniqueValidator;

/**
 * UuidBehavior automatically fills the specified attribute with a unique uuid.
 *
 * To use UuidBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use yii\behaviors\UuidBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UuidBehavior::className(),
 *             // 'uuidAttribute' => 'uuid',
 *         ],
 *     ];
 * }
 * ```
 *
 * By default, UuidBehavior will fill the `uuid` attribute with a unique uuid 
 * when the associated AR object is being validated.
 *
 * Because attribute values will be set automatically by this behavior, they are usually not user input and should therefore
 * not be validated, i.e. the `uuid` attribute should not appear in the [[\yii\base\Model::rules()|rules()]] method of the model.
 *
 * If your attribute name is different, you may configure the [[uuidAttribute]] property like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UuidBehavior::className(),
 *             'uuidAttribute' => 'alias',
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Jose Lorente <jose.lorente.martin@gmail.com>
 * @since 2.0
 */
class UuidBehavior extends AttributeBehavior
{

    /**
     * @var string the attribute that will receive the uuid value
     */
    public $uuidAttribute = 'uuid';

    /**
     * @var array configuration for uuid uniqueness validator. Parameter 'class' may be omitted - by default
     * [[UniqueValidator]] will be used.
     * @see UniqueValidator
     */
    public $uniqueValidator = [];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if (empty($this->attributes)) {
            $this->attributes = [BaseActiveRecord::EVENT_BEFORE_VALIDATE => $this->uuidAttribute];
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getValue($event)
    {
        if (!$this->isNewUuidNeeded()) {
            return $this->owner->{$this->uuidAttribute};
        }

        return $this->generateUniqueUuid();
    }

    /**
     * Checks whether the new uuid generation is needed
     * This method is called by [[getValue]] to check whether the new uuid generation is needed.
     * You may override it to customize checking.
     * @return bool
     * @since 2.0.7
     */
    protected function isNewUuidNeeded()
    {
        if (empty($this->owner->{$this->uuidAttribute})) {
            return true;
        }

        return false;
    }

    /**
     * This method is called to generate the unique uuid.
     * Calls [[generateUuid]] until generated uuid is unique and returns it.
     * @return string unique uuid
     * @see getValue
     * @see generateUniqueUuid
     */
    protected function generateUniqueUuid()
    {
        do {
            $uniqueUuid = $this->generateUuid();
        } while (!$this->validateUuid($uniqueUuid));

        return $uniqueUuid;
    }

    /**
     * This method is called by [[generateUniqueUuid]] to generate the uuid.
     * You may override it to customize uuid generation.
     * The default implementation uses Uuid version 5 composed by a uuid version 4.
     * @return string the uuid result.
     */
    protected function generateUuid()
    {
        return Uuid::uuid5(
                        $this->getUuidNamespace(),
                        $this->getUuidGeneratorUniqueIdentifier()
                )->toString();
    }

    /**
     * Checks if given uuid value is unique.
     * @param string $uuid uuid value
     * @return bool whether uuid is unique.
     */
    protected function validateUuid($uuid)
    {
        /* @var $validator UniqueValidator */
        /* @var $model BaseActiveRecord */
        $validator = Yii::createObject(array_merge(
                                [
                                    'class' => UniqueValidator::className(),
                                ],
                                $this->uniqueValidator
        ));

        $model = clone $this->owner;
        $model->clearErrors();
        $model->{$this->uuidAttribute} = $uuid;

        $validator->validateAttribute($model, $this->uuidAttribute);
        return !$model->hasErrors();
    }

    /**
     * Gets the uuid model namespace.
     * 
     * @return string
     */
    public function getUuidNamespace(): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, Yii::$app->urlManager->createAbsoluteUrl(['/']));
    }

    /**
     * Gets the uuid model namespace.
     * 
     * @return string
     */
    public function getUuidGeneratorUniqueIdentifier(): string
    {
        return get_class($this->owner) . ':  ' . Uuid::uuid4() . ':' . microtime();
    }

}
