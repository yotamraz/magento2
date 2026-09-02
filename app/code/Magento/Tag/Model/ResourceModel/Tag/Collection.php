<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Tag;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;

/**
 * Tag collection.
 */
class Collection extends AbstractCollection
{
    protected $_map = ['fields' => ['tag_id' => 'main_table.tag_id']];

    protected function _construct(): void
    {
        $this->_init(Tag::class, TagResource::class);
    }

    /**
     * Add limit.
     */
    public function limit(int $limit): static
    {
        $this->getSelect()->limit($limit);
        return $this;
    }

    /**
     * Join tag_summary for popularity, customers, products columns.
     */
    public function addSummary(int $storeId): static
    {
        if (!$this->getFlag('summary')) {
            $cond = $this->getConnection()->quoteInto(' AND summary.store_id IN(?)', $storeId);
            $this->getSelect()->joinLeft(
                ['summary' => $this->getTable('tag_summary')],
                'main_table.tag_id = summary.tag_id' . $cond,
                ['store_id', 'popularity', 'customers', 'products']
            );
            $this->addFilterToMap('store_id', 'summary.store_id');
            $this->addFilterToMap('popularity', 'summary.popularity');
            $this->addFilterToMap('customers', 'summary.customers');
            $this->addFilterToMap('products', 'summary.products');
            $this->setFlag('summary', true);
        }
        return $this;
    }

    /**
     * Join tag_relation for joins that need the relation table.
     */
    public function joinRel(): static
    {
        $this->setFlag('relation', true);
        $this->getSelect()->joinLeft(
            ['relation' => $this->getTable('tag_relation')],
            'main_table.tag_id = relation.tag_id',
            []
        );
        return $this;
    }

    /**
     * Add popularity columns via join of relation + summary.
     */
    public function addPopularity(?int $limit = null): static
    {
        if (!$this->getFlag('popularity')) {
            $this->getSelect()
                ->joinLeft(
                    ['relation' => $this->getTable('tag_relation')],
                    'main_table.tag_id = relation.tag_id',
                    []
                )
                ->joinLeft(
                    ['summary' => $this->getTable('tag_summary')],
                    'relation.tag_id = summary.tag_id AND relation.store_id = summary.store_id',
                    ['popularity']
                )
                ->group('main_table.tag_id');
            if ($limit !== null) {
                $this->getSelect()->limit($limit);
            }
            $this->setFlag('popularity', true);
        }
        return $this;
    }

    /**
     * Mark to attach visible_in_store_ids after load.
     */
    public function addStoresVisibility(): static
    {
        $this->setFlag('add_stores_after', true);
        return $this;
    }

    /**
     * Filter to only tags visible in the specified store(s).
     */
    public function addStoreFilter(array|int $storeId, bool $allFilter = true): static
    {
        if (!$this->getFlag('store_filter')) {
            $this->getSelect()->joinLeft(
                ['summary_store' => $this->getTable('tag_summary')],
                'main_table.tag_id = summary_store.tag_id',
                []
            );
            $this->getSelect()->where('summary_store.store_id IN (?)', $storeId);
            $this->getSelect()->group('main_table.tag_id');

            if ($this->getFlag('relation') && $allFilter) {
                $this->getSelect()->where('relation.store_id IN (?)', $storeId);
            }

            $this->setFlag('store_filter', true);
        }
        return $this;
    }

    /**
     * Filter by tag status.
     */
    public function addStatusFilter(int $status): static
    {
        $this->getSelect()->where('main_table.status = ?', $status);
        return $this;
    }

    /**
     * Filter by product ID (requires joinRel() first).
     */
    public function addProductFilter(int $productId): static
    {
        $this->getSelect()->where('relation.product_id = ?', $productId);
        return $this;
    }

    /**
     * Filter by customer ID (requires joinRel() first).
     */
    public function addCustomerFilter(int $customerId): static
    {
        $this->getSelect()->where('relation.customer_id = ?', $customerId);
        return $this;
    }

    /**
     * Filter by active relation status (requires joinRel() first).
     */
    public function setActiveFilter(): static
    {
        $this->getSelect()->where('relation.active = ?', \Magento\Tag\Model\Tag\Relation::STATUS_ACTIVE);
        return $this;
    }

    /**
     * Group by tag_id (de-duplicate after join).
     */
    public function addTagGroup(): static
    {
        $this->getSelect()->group('main_table.tag_id');
        return $this;
    }

    public function load($printQuery = false, $logQuery = false): static
    {
        if ($this->isLoaded()) {
            return $this;
        }
        parent::load($printQuery, $logQuery);
        if ($this->getFlag('add_stores_after')) {
            $this->_addStoresVisibility();
        }
        return $this;
    }

    public function addFieldToFilter($field, $condition = null): static
    {
        if ($this->getFlag('summary')
            && in_array($field, ['customers', 'products', 'uses', 'historical_uses', 'popularity'])
        ) {
            $this->getSelect()->where($this->_getConditionSql('summary.' . $field, $condition));
            return $this;
        }
        return parent::addFieldToFilter($field, $condition);
    }

    public function getSelectCountSql(): \Magento\Framework\DB\Select
    {
        $select = parent::getSelectCountSql();
        $select->reset(\Zend_Db_Select::COLUMNS);
        $select->reset(\Zend_Db_Select::GROUP);
        $select->reset(\Zend_Db_Select::HAVING);
        $select->columns('COUNT(DISTINCT main_table.tag_id)');
        return $select;
    }

    private function _addStoresVisibility(): void
    {
        $tagIds = $this->getColumnValues('tag_id');
        $tagsStores = [];
        if ($tagIds) {
            $select = $this->getConnection()->select()
                ->from($this->getTable('tag_summary'), ['store_id', 'tag_id'])
                ->where('tag_id IN(?)', $tagIds);
            foreach ($this->getConnection()->fetchAll($select) as $row) {
                $tagsStores[$row['tag_id']][] = $row['store_id'];
            }
        }
        foreach ($this as $item) {
            $item->setData('stores', $tagsStores[$item->getId()] ?? []);
        }
    }
}
