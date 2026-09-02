<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Product;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;

/**
 * Products tagged with a specific tag — extends the catalog product EAV collection.
 *
 * Joins tag_relation (alias "relation") and tag (alias "t") to allow filtering
 * by tag, customer, store, and status.
 */
class Collection extends ProductCollection
{
    /** @var int|null */
    private ?int $tagIdFilter = null;

    protected function _initSelect(): static
    {
        parent::_initSelect();
        $this->_joinFields();
        $this->getSelect()->group('e.entity_id');
        return $this;
    }

    private function _joinFields(): void
    {
        $this->addAttributeToSelect('name')
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('small_image');

        $conn = $this->getConnection();
        $this->getSelect()
            ->join(
                ['relation' => $this->getTable('tag_relation')],
                'relation.product_id = e.entity_id',
                ['product_id', 'item_store_id' => 'store_id']
            )
            ->join(
                ['t' => $this->getTable('tag')],
                't.tag_id = relation.tag_id',
                [
                    'tag_id',
                    'tag_status' => 'status',
                    'tag_name'   => 'name',
                    'store_id'   => $conn->getCheckSql(
                        't.first_store_id = 0',
                        'relation.store_id',
                        't.first_store_id'
                    ),
                ]
            );
    }

    public function addTagFilter(int $tagId): static
    {
        $this->getSelect()->where('relation.tag_id = ?', $tagId);
        $this->setFlag('distinct', true);
        $this->tagIdFilter = $tagId;
        return $this;
    }

    public function addStoreFilter($store = null, $withAdmin = true): static
    {
        if ($store !== null) {
            $this->getSelect()->where('relation.store_id IN (?)', $store);
        }
        return $this;
    }

    public function addCustomerFilter(int|array $customerId): static
    {
        if (is_array($customerId) && isset($customerId['null'])) {
            $condition = $customerId['null'] ? 'IS NULL' : 'IS NOT NULL';
            $this->getSelect()->where('relation.customer_id ' . $condition);
            return $this;
        }
        $this->getSelect()->where('relation.customer_id IN(?)', $customerId);
        return $this;
    }

    public function addStatusFilter(int $status): static
    {
        $this->getSelect()->where('t.status = ?', $status);
        return $this;
    }

    public function setActiveFilter(): static
    {
        $this->getSelect()->where('relation.active = ?', \Magento\Tag\Model\Tag\Relation::STATUS_ACTIVE);
        return $this;
    }

    public function addGroupByTag(): static
    {
        $this->getSelect()->group('relation.tag_relation_id');
        return $this;
    }

    public function setDescOrder(string $dir = 'DESC'): static
    {
        $this->setOrder('relation.tag_relation_id', $dir);
        return $this;
    }

    public function setRelationId(): static
    {
        $this->_setIdFieldName('tag_relation_id');
        return $this;
    }

    public function addPopularity(int $tagId, ?int $storeId = null): static
    {
        $condition = ['prelation.product_id = e.entity_id'];
        if ($storeId !== null) {
            $condition[] = $this->getConnection()->quoteInto('prelation.store_id = ?', $storeId);
        }

        $innerSelect = $this->getConnection()->select()
            ->from(
                ['relation' => $this->getTable('tag_relation')],
                ['product_id', 'store_id', 'popularity' => 'COUNT(DISTINCT relation.tag_relation_id)']
            )
            ->where('relation.tag_id = ?', $tagId)
            ->group(['product_id', 'store_id']);

        $this->getSelect()->joinLeft(
            ['prelation' => $innerSelect],
            implode(' AND ', $condition),
            ['popularity' => 'prelation.popularity']
        );

        $this->tagIdFilter = $tagId;
        $this->setFlag('prelation', true);
        return $this;
    }

    public function getSelectCountSql(): \Magento\Framework\DB\Select
    {
        $countSelect = clone $this->getSelect();
        $countSelect->reset(\Zend_Db_Select::COLUMNS);
        $countSelect->reset(\Zend_Db_Select::ORDER);
        $countSelect->reset(\Zend_Db_Select::LIMIT_COUNT);
        $countSelect->reset(\Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->reset(\Zend_Db_Select::GROUP);

        $field = $this->getFlag('group_tag') ? 'relation.tag_id' : 'e.entity_id';
        $expr  = new \Zend_Db_Expr('COUNT(' . ($this->getFlag('distinct') ? 'DISTINCT ' : '') . $field . ')');
        $countSelect->columns($expr);
        return $countSelect;
    }
}
