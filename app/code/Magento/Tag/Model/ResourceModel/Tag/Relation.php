<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Tag;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Tag\Model\Tag\Relation as RelationModel;

/**
 * Tag relation resource model.
 */
class Relation extends AbstractDb
{
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        private readonly DateTime $dateTime,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct(): void
    {
        $this->_init('tag_relation', 'tag_relation_id');
    }

    /**
     * Load relation by tag + customer (+ optional product + store).
     */
    public function loadByTagCustomer(AbstractModel $model): void
    {
        if (!$model->getData('tag_id') || !$model->getData('customer_id')) {
            return;
        }

        $conn = $this->getConnection();
        $bind = [
            'tag_id'      => (int) $model->getData('tag_id'),
            'customer_id' => (int) $model->getData('customer_id'),
        ];

        $select = $conn->select()
            ->from($this->getMainTable())
            ->join(
                ['tag' => $this->getTable('tag')],
                'tag.tag_id = ' . $this->getMainTable() . '.tag_id',
                []
            )
            ->where($this->getMainTable() . '.tag_id = :tag_id')
            ->where($this->getMainTable() . '.customer_id = :customer_id');

        if ($model->getData('product_id')) {
            $select->where($this->getMainTable() . '.product_id = :product_id');
            $bind['product_id'] = (int) $model->getData('product_id');
        }

        if ($model->hasData('store_id')) {
            $select->where($this->getMainTable() . '.store_id = :store_id');
            $bind['store_id'] = (int) $model->getData('store_id');
        }

        $data = $conn->fetchRow($select, $bind);
        $model->setData(is_array($data) ? $data : []);
    }

    /**
     * Return product IDs for the given tag/customer/store filter.
     */
    public function getProductIds(AbstractModel $model): array
    {
        $bind   = ['tag_id' => (int) $model->getData('tag_id')];
        $conn   = $this->getConnection();
        $select = $conn->select()
            ->from($this->getMainTable(), 'product_id')
            ->where($this->getMainTable() . '.tag_id = :tag_id');

        if ($model->getData('customer_id') !== null) {
            $select->where($this->getMainTable() . '.customer_id = :customer_id');
            $bind['customer_id'] = (int) $model->getData('customer_id');
        }

        if ($model->hasData('store_id')) {
            $select->where($this->getMainTable() . '.store_id = :store_id');
            $bind['store_id'] = (int) $model->getData('store_id');
        }

        if ($model->getData('status_filter') !== null) {
            $select->join(
                ['tag' => $this->getTable('tag')],
                'tag.tag_id = ' . $this->getMainTable() . '.tag_id',
                []
            )->where('tag.status = :t_status');
            $bind['t_status'] = $model->getData('status_filter');
        }

        return $conn->fetchCol($select, $bind);
    }

    /**
     * Return tag IDs related to the product(s) on the model.
     */
    public function getRelatedTagIds(AbstractModel $model): array
    {
        $productIds = is_array($model->getData('product_id'))
            ? $model->getData('product_id')
            : [(int) $model->getData('product_id')];

        $conn   = $this->getConnection();
        $select = $conn->select()
            ->from($this->getMainTable(), 'tag_id')
            ->where('product_id IN(?)', $productIds)
            ->order('tag_id');
        return $conn->fetchCol($select);
    }

    /**
     * Set active=0 for all relations of the given tag + customer.
     */
    public function deactivate(int $tagId, ?int $customerId): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['active' => RelationModel::STATUS_NOT_ACTIVE],
            ['tag_id = ?' => $tagId, 'customer_id = ?' => $customerId]
        );
    }

    /**
     * Sync tag–product relations for a batch of product IDs (insert new, delete removed).
     */
    public function addRelations(AbstractModel $model): void
    {
        $addedIds = (array) $model->getData('added_product_ids');
        $conn     = $this->getConnection();

        $bind = [
            'tag_id'   => (int) $model->getData('tag_id'),
            'store_id' => (int) $model->getData('store_id'),
        ];
        $select = $conn->select()
            ->from($this->getMainTable(), 'product_id')
            ->where('tag_id = :tag_id')
            ->where('store_id = :store_id');
        $existingIds = $conn->fetchCol($select, $bind);

        $toInsert = array_diff($addedIds, $existingIds);
        $toDelete = array_diff($existingIds, $addedIds);

        if ($toInsert) {
            $rows = [];
            $now  = $this->dateTime->gmtDate();
            foreach ($toInsert as $productId) {
                $rows[] = [
                    'tag_id'      => (int) $model->getData('tag_id'),
                    'store_id'    => (int) $model->getData('store_id'),
                    'product_id'  => (int) $productId,
                    'customer_id' => $model->getData('customer_id'),
                    'created_at'  => $now,
                ];
            }
            $conn->insertMultiple($this->getMainTable(), $rows);
        }

        if ($toDelete) {
            $conn->delete($this->getMainTable(), [
                'product_id IN (?)' => $toDelete,
                'store_id = ?'      => (int) $model->getData('store_id'),
            ]);
        }
    }
}
