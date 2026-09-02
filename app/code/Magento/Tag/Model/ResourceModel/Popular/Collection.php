<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Popular;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;

/**
 * Popular tags collection — ordered by popularity descending, joined with tag_summary.
 */
class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Tag::class, TagResource::class);
    }

    /**
     * Reset select and build a popularity-ordered join of tag_summary + tag.
     */
    public function joinFields(int $storeId = 0): static
    {
        $this->getSelect()
            ->reset()
            ->from(
                ['tag_summary' => $this->getTable('tag_summary')],
                ['popularity' => 'tag_summary.popularity']
            )
            ->joinInner(
                ['tag' => $this->getTable('tag')],
                'tag.tag_id = tag_summary.tag_id AND tag.status = ' . Tag::STATUS_APPROVED,
                ['tag_id', 'name', 'status', 'first_customer_id', 'first_store_id']
            )
            ->where('tag_summary.store_id = ?', $storeId)
            ->where('tag_summary.products > ?', 0)
            ->order('popularity ' . \Zend_Db_Select::SQL_DESC);

        return $this;
    }

    /**
     * Apply a limit to the result.
     */
    public function limit(int $limit): static
    {
        $this->getSelect()->limit($limit);
        return $this;
    }

    /**
     * Filter by tag status.
     */
    public function addStatusFilter(int $statusCode): static
    {
        $this->getSelect()->where('tag.status = ?', $statusCode);
        return $this;
    }

    public function getSelectCountSql(): \Magento\Framework\DB\Select
    {
        $this->_renderFilters();
        $select = clone $this->getSelect();
        $select->reset(\Zend_Db_Select::ORDER);
        $select->reset(\Zend_Db_Select::LIMIT_COUNT);
        $select->reset(\Zend_Db_Select::LIMIT_OFFSET);

        $countSelect = $this->getConnection()->select();
        $countSelect->from(['a' => $select], 'COUNT(popularity)');
        return $countSelect;
    }
}
