<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\Tag;

use Magento\Framework\Model\AbstractModel;
use Magento\Tag\Api\Data\TagRelationInterface;

/**
 * Tag–product–customer–store relation model.
 *
 * @api
 * @method int getTagRelationId()
 * @method int getTagId()
 * @method int|null getCustomerId()
 * @method int getProductId()
 * @method int getStoreId()
 * @method int getActive()
 * @method string|null getCreatedAt()
 * @method $this setTagId(int $value)
 * @method $this setCustomerId(int|null $value)
 * @method $this setProductId(int $value)
 * @method $this setStoreId(int $value)
 * @method $this setActive(int $value)
 * @method $this setCreatedAt(string $value)
 * @method $this setStatusFilter(string $value)
 * @method string|null getStatusFilter()
 */
class Relation extends AbstractModel implements TagRelationInterface
{
    public const STATUS_ACTIVE     = 1;
    public const STATUS_NOT_ACTIVE = 0;
    public const ENTITY            = 'tag_relation';

    protected $_eventPrefix = 'tag_relation';

    protected function _construct(): void
    {
        $this->_init(\Magento\Tag\Model\ResourceModel\Tag\Relation::class);
    }

    // --- TagRelationInterface ---

    public function getTagRelationId(): ?int
    {
        $id = $this->_getData(TagRelationInterface::TAG_RELATION_ID);
        return $id !== null ? (int) $id : null;
    }

    public function getTagId(): int
    {
        return (int) $this->_getData(TagRelationInterface::TAG_ID);
    }

    public function setTagId(int $tagId): TagRelationInterface
    {
        return $this->setData(TagRelationInterface::TAG_ID, $tagId);
    }

    public function getCustomerId(): ?int
    {
        $id = $this->_getData(TagRelationInterface::CUSTOMER_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setCustomerId(?int $customerId): TagRelationInterface
    {
        return $this->setData(TagRelationInterface::CUSTOMER_ID, $customerId);
    }

    public function getProductId(): int
    {
        return (int) $this->_getData(TagRelationInterface::PRODUCT_ID);
    }

    public function setProductId(int $productId): TagRelationInterface
    {
        return $this->setData(TagRelationInterface::PRODUCT_ID, $productId);
    }

    public function getStoreId(): int
    {
        return (int) $this->_getData(TagRelationInterface::STORE_ID);
    }

    public function setStoreId(int $storeId): TagRelationInterface
    {
        return $this->setData(TagRelationInterface::STORE_ID, $storeId);
    }

    public function getActive(): int
    {
        return (int) $this->_getData(TagRelationInterface::ACTIVE);
    }

    public function setActive(int $active): TagRelationInterface
    {
        return $this->setData(TagRelationInterface::ACTIVE, $active);
    }

    // --- Domain methods ---

    /**
     * Load relation by product (optional), tag, customer and store.
     */
    public function loadByTagCustomer(?int $productId, int $tagId, ?int $customerId, ?int $storeId = null): static
    {
        $this->setProductId((int) $productId);
        $this->setTagId($tagId);
        $this->setCustomerId($customerId);
        if ($storeId !== null) {
            $this->setStoreId($storeId);
        }
        $this->_getResource()->loadByTagCustomer($this);
        return $this;
    }

    /**
     * Return product IDs for the current tag/customer/store filter.
     */
    public function getProductIds(): array
    {
        $ids = $this->getDataByKey('product_ids');
        if ($ids === null) {
            $ids = $this->_getResource()->getProductIds($this);
            $this->setData('product_ids', $ids);
        }
        return $ids;
    }

    /**
     * Return tag IDs related to the products set on this object.
     */
    public function getRelatedTagIds(): array
    {
        if ($this->getDataByKey('related_tag_ids') === null) {
            $this->setData('related_tag_ids', $this->_getResource()->getRelatedTagIds($this));
        }
        return $this->getDataByKey('related_tag_ids');
    }

    /**
     * Deactivate tag relations for the current tag/customer.
     */
    public function deactivate(): static
    {
        $this->_getResource()->deactivate($this->getTagId(), $this->getCustomerId());
        return $this;
    }

    /**
     * Add tag–product relations for a batch of product IDs.
     */
    public function addRelations(\Magento\Tag\Model\Tag $tagModel, array $productIds = []): static
    {
        $this->setData('added_product_ids', $productIds);
        $this->setTagId((int) $tagModel->getId());
        $this->setCustomerId(null);
        $this->setStoreId((int) $tagModel->getData('store'));
        $this->_getResource()->addRelations($this);
        return $this;
    }
}
