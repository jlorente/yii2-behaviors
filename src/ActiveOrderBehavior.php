<?php

/**
 * @author      José Lorente <jose.lorente.martin@gmail.com>
 * @license     The MIT License (MIT)
 * @copyright   José Lorente
 * @version     1.0
 */

namespace jlorente\behaviors;

use Yii;
use yii\db\QueryInterface;
use yii\base\Behavior;
use yii\db\BaseActiveRecord;
use yii\db\Exception;

/**
 * ActiveOrderBehavior will automatically manage an order column in a table.
 *
 * ActiveOrderBehavior will automatically move elements of the same group based 
 * on the reference columns. If no reference columns are provided, all the rows 
 * in the table are considered from the same group.
 * You may specify an active record model to use this behavior like so:
 * 
 * ```php
 * use jlorente\behaviors\ActiveOrderBehavior;
 * 
 * class Post {
 *     public function behaviors() {
 *         return [
 *             // ... other behaviors ...
 *             [
 *                 'class' => ActiveOrderBehavior::className(),
 *                 'orderAttribute' => 'order',
 *                 'referenceColumns' => ['blog_id'],
 *                 'preventInitialization' => false
 *             ]
 *         ];
 *     }
 * }
 * ```
 * 
 * The orderAttribute and referenceColumns options actually default to 'order' 
 * and [] respectively, so it is not required that you configure them.
 * 
 * You can specify more than one reference column by defining the referenceColumns
 * as an array of columns or a string for one column.
 *
 * By default, the order column if not defined, is set to the integer that follows 
 * the group's max order. If preventInitialization option is set to true and 
 * the order property is not defined, an exception in the event will be raised.
 *
 * @author José Lorente <jose.lorente.martin@gmail.com>
 */
class ActiveOrderBehavior extends Behavior {

    /**
     *
     * @var string 
     */
    public $orderAttribute = 'order';

    /**
     *
     * @var mixed
     */
    public $referenceColumns = [];

    /**
     *
     * @var boolean
     */
    public $preventInitialization = false;

    /**
     *
     * @var int
     */
    protected $oldValue;

    /**
     * @inheritdoc
     */
    public function events() {
        return [
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete'
        ];
    }

    /**
     * {@inheritDoc}
     * 
     * Asigns a default value for order column if not set and displaces elements 
     * of the group in order to make place for the current element.
     */
    public function beforeSave($event) {
        if (is_numeric($this->getColValue()) === false || $this->getColValue() <= 0) {
            if ($this->preventInitialization === true) {
                $event->isValid = false;
                return;
            }
            $this->setColValue($this->getMaxOrder() + 1);
        }

        $trans = Yii::$app->db->beginTransaction();
        try {
            if ($this->getOldOrder() !== $this->getColValue()) {
                $this->reasignOldPosition();
                $this->moveUp();
            }
            $trans->commit();
            $event->isValid = true;
        } catch (Exception $e) {
            $trans->rollback();
            $this->getOwner()->addError(__CLASS__, $e->getMessage());
            $event->isValid = false;
        }
    }

    /**
     * @see static::extract()
     * @see static::moveDown()
     */
    protected function reasignOldPosition() {
        if ($this->getOwner()->isNewRecord === false) {
            $this->extract();
            $this->moveDown();
        }
    }

    /**
     * Extracts the element from its current position and puts it in the end.
     */
    protected function extract() {
        $nMax = $this->getMaxOrder() + 1;
        $query = $this->getQuery();
        $this->addPrimaryKeyCriteria($query);
        $class = get_class($this->getOwner());
        $class::updateAll([$this->orderAttribute => $nMax], $query->where);
    }

    /**
     * Decrements the order column of all the elements of the group with an 
     * order greater than the old value in one.
     */
    protected function moveDown() {
        $query = $this->getQuery();
        $this->addReferenceCriteria($query);
        $query->andWhere(['>', $this->orderAttribute, $this->getOldOrder()]);
        $class = get_class($this->getOwner());
        $class::updateAllCounters([$this->orderAttribute => -1], $query->where);
    }

    /**
     * Increments the order column of all the elements of the group with an 
     * order greater or equal than the new value in one.
     */
    protected function moveUp() {
        $query = $this->getQuery();
        $this->addReferenceCriteria($query);
        $query->andWhere(['>=', $this->orderAttribute, $this->getColValue()]);
        $class = get_class($this->getOwner());
        $class::updateAllCounters([$this->orderAttribute => 1], $query->where);
    }

    /**
     * {@inheritDoc}
     * 
     * Asigns a default value for order column if not set and displaces elements 
     * of the group in order to make place for the current element.
     */
    public function afterDelete($event) {
        $this->oldValue = $this->getColValue();
        $this->moveDown();
    }

    /**
     * Returns the greatest value for the order column of a group.
     * 
     * @return int
     */
    public function getMaxOrder() {
        $query = $this->getQuery();
        $this->addReferenceCriteria($query);
        return $query->count();
    }

    /**
     * Adds reference columns filters to the Query object
     * 
     * @param QueryInterface $query
     * @return QueryInterface
     */
    protected function addReferenceCriteria(QueryInterface $query) {
        if (empty($this->referenceColumns) === false) {
            $referenceColumns = (array) $this->referenceColumns;
            foreach ($referenceColumns as $column) {
                $query->andWhere([$column => $this->getOwner()->$column]);
            }
        }
        return $query;
    }

    /**
     * Adds primary key filter/s to the Query object.
     * 
     * @param QueryInterface $query
     * @return QueryInterface
     */
    protected function addPrimaryKeyCriteria(QueryInterface $query) {
        $class = get_class($this->getOwner());
        $pks = (array) $class::primaryKey();
        foreach ($pks as $pk) {
            $query->andWhere([$pk => $this->getOwner()->$pk]);
        }
        return $query;
    }

    /**
     * Gets the current value of the object's old order.
     * 
     * @return int
     */
    protected function getOldOrder() {
        return $this->getOwner()->getOldAttribute($this->orderAttribute);
    }

    /**
     * Sets the value of the object's order column.
     * 
     * @param int $value
     */
    protected function setColValue($value) {
        $this->getOwner()->{$this->orderAttribute} = $value;
    }

    /**
     * Return the current value of the object's order column.
     * 
     * @return int
     */
    protected function getColValue() {
        return $this->getOwner()->{$this->orderAttribute};
    }

    /**
     * Gets the query object created from the model.
     * 
     * @return QueryInterface
     */
    protected function getQuery() {
        $class = get_class($this->getOwner());
        return $class::find();
    }

    /**
     * 
     * @return yii\db\ActiveRecordInterface
     */
    protected function getOwner() {
        return $this->owner;
    }

}
