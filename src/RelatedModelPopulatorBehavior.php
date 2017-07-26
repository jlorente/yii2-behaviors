<?php

/**
 * @author	José Lorente <jose.lorente.martin@gmail.com>
 * @copyright	José Lorente <jose.lorente.martin@gmail.com>
 * @version	1.0
 */

namespace jlorente\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * Behavior to help in the task of populate and save data of models together 
 * with related models. You can use it for submitting forms with tabular data or 
 * that include related models data.
 * 
 * The relations that the behavior must consider must be defined on behavior 
 * declaration.
 * 
 * 
 * ```php
 * use jlorente\behaviors\RelatedModelPopulatorBehavior;
 * 
 * class Post {
 *     public function behaviors() {
 *         return [
 *             // ... other behaviors ...
 *             'relatedModelPopulator' => [
 *                 'class' => RelatedModelPopulatorBehavior::className(),
 *                 'relations' => ['owner', 'blog']
 *             ]
 *         ];
 *     }
 * 
 *     public function getOwner() {
 *         return $this->hasOne(User::className(), ['id' => 'owner_id']);
 *     }
 * 
 *     public function getBlog() {
 *         return $this->hasOne(Blog::className(), ['id' => 'blog_id']);
 *     }
 * }
 * ```
 * 
 * Remember that the relations MUST exist in order to work. 
 * 
 * @see http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#declaring-relations
 * 
 * Actually the behavior only works with hasOne relations.
 * 
 * @author José Lorente <jose.lorente.martin@gmail.com>
 */
class RelatedModelPopulatorBehavior extends Behavior {

    /**
     * The relations to be considered by the behavior.
     * 
     * If you populate this variable after having attached the behavior remember 
     * to call ensureRelatedMap(true) to fix the related map.
     * 
     * @var string|string[] 
     */
    public $relations = [];

    /**
     * Stores the relations metadata. Is populated on init based on the 
     * relations property.
     * 
     * @var array
     */
    private $_relatedMap = [];

    /**
     * Variable to control the recursivity of the saving process.
     * 
     * @var boolean 
     */
    protected $_called = false;

    /**
     * @inheritdoc
     */
    public function events() {
        return [
            ActiveRecord::EVENT_AFTER_VALIDATE => 'validateRelatedModels'
            , ActiveRecord::EVENT_AFTER_INSERT => 'saveRelatedModels'
            , ActiveRecord::EVENT_AFTER_UPDATE => 'saveRelatedModels'
        ];
    }

    /**
     * Validates the related models.
     * 
     * @return boolean
     */
    public function validateRelatedModels() {
        $valid = true;
        $this->ensureRelatedMap();
        foreach ($this->_relatedMap as $relation => $relatedData) {
            $related = $this->owner->$relation;
            if ($related->validate() === false) {
                $valid = false;
                foreach ($related->getErrors() as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->owner->addError($relation . "[$attribute]", $error);
                    }
                }
            }
        }
        return $valid;
    }

    /**
     * Internal trait method that performs the save operation on the related 
     * models.
     * 
     * @see \yii\db\ActiveRecord::save()
     */
    public function saveRelatedModels($runValidation = true, $attributeNames = null) {
        if ($this->_called === true) {
            return true;
        }
        if ($runValidation === true && $this->validateRelatedModels() === false) {
            return false;
        }
        $this->_called = true;
        $trans = Yii::$app->db->beginTransaction();
        try {
            $attributes = [];
            $this->ensureRelatedMap();
            foreach ($this->_relatedMap as $relation => $relatedData) {
                $related = $this->owner->$relation;
                if ($related) {
                    if ($related->save($runValidation, $attributeNames) === false) {
                        throw new Exception('Unable to save ' . get_class($related) . '. Errors: [' . Json::encode($related->getErrors()) . ']');
                    }
                    $this->owner->{$relatedData['fk']} = $related->getPrimaryKey();
                    $attributes[] = $relatedData['fk'];
                }
            }
            if ($this->owner->updateAttributes($attributeNames) === false) {
                throw new Exception('Unable to update attributes from ' . get_class($this->owner));
            }
            $trans->commit();
            return true;
        } catch (\Exception $e) {
            $trans->rollback();
            throw $e;
        } finally {
            $this->_called = false;
        }
    }

    /**
     * Internal trait method that performs the load operation of the related 
     * models.
     * 
     * @see \yii\db\ActiveRecord::load()
     */
    public function relatedModelLoad($data, $formName = null) {
        if (parent::load($data, $formName) === true) {
            $this->ensureRelatedMap();
            $scope = $formName === null ? $this->formName() : $formName;
            foreach ($this->_relatedMap as $relation => $relationData) {
                $this->loadRelatedModel($data, $scope, $relation, $relationData['fk'], $relationData['class']);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Loads the related model data.
     * 
     * @param array $data
     * @param string $scope
     * @param string $modelClass
     * @return boolean
     */
    public function loadRelatedModel($data, $scope, $relation, $modelClass) {
        $model = $this->owner->$relation;
        if (!$model) {
            $model = new $modelClass();
            $model->loadDefaultValues();
            $this->owner->populateRelation($relation, $model);
        }
        if ($scope === '' && !empty($data) && isset($data[$model->formName()])) {
            /* @var $model \yii\db\ActiveRecord */
            $model->setAttributes($data[$model->formName()]);
            return true;
        } elseif (isset($data[$scope][$model->formName()])) {
            $model->setAttributes($data[$scope][$model->formName()]);
            return true;
        }
        return false;
    }

    /**
     * Ensures the related map with the relations property data.
     * 
     * The behavior must be already attached in order to use this method.
     * 
     * @param boolean $refresh
     */
    public function ensureRelatedMap($refresh = false) {
        if ($this->_relatedMap === null || $refresh = true) {
            
        }
    }

    /**
     * Sets the related map data.
     * 
     * The behavior must be already attached in order to use this method.
     * 
     * @param array $data
     */
    public function setRelations($data) {
        if (is_scalar($data)) {
            $this->addRelation($data);
        } else {
            foreach ($data as $name) {
                $this->addRelation($name);
            }
        }
    }

    /**
     * Adds a relation to the related map.
     * 
     * The behavior must be already attached in order to use this method.
     * 
     * @param string $name
     */
    public function addRelation($name) {
        $method = 'get' . $name;
        if (method_exists($this->owner, $method)) {
            $relation = call_user_func([$this->owner, $method]);
            $this->_relatedMap[$name] = [
                'class' => $relation->modelClass
                , 'fk' => array_pop($relation->link)
            ];
        }
    }

    /**
     * Removes a relation from the related map.
     * 
     * @param string $name
     */
    public function removeRelation($name) {
        unset($this->_relatedMap[$name]);
    }

}
