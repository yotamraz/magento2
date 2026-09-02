<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Tag\Relation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;

/**
 * Tag relation collection.
 */
class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Relation::class, RelationResource::class);
    }

    /**
     * Filter by tag ID.
     */
    public function addTagFilter(int $tagId): static
    {
        $this->addFieldToFilter('tag_id', $tagId);
        return $this;
    }

    /**
     * Filter by product ID.
     */
    public function addProductFilter(int $productId): static
    {
        $this->addFieldToFilter('product_id', $productId);
        return $this;
    }

    /**
     * Filter by customer ID.
     */
    public function addCustomerFilter(int $customerId): static
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter by store ID.
     */
    public function addStoreFilter(int $storeId): static
    {
        $this->addFieldToFilter('store_id', $storeId);
        return $this;
    }

    /**
     * Filter by active status.
     */
    public function addActiveFilter(): static
    {
        $this->addFieldToFilter('active', Relation::STATUS_ACTIVE);
        return $this;
    }
}
