<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * Ported from legacy Mage_Tag_Model_Tag_Relation (original source licensed under AFL 3.0 / OSL 3.0).
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Tag;

use Magento\Framework\Model\AbstractModel;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag;

/**
 * Tag relation model
 *
 * @method int getActive()
 * @method array getAddedProductIds()
 * @method int getCustomerId()
 * @method int getProductId()
 * @method string getStatusFilter()
 * @method int getStoreId()
 * @method int getTagId()
 * @method bool hasStoreId()
 * @method $this setActive(int $value)
 * @method $this setAddedProductIds(array $value)
 * @method $this setCustomerId(int $value)
 * @method $this setProductId(int $value)
 * @method $this setProductIds(array $value)
 * @method $this setRelatedTagIds(array $value)
 * @method $this setStatusFilter(string $value)
 * @method $this setStoreId(int $value)
 * @method $this setTagId(int $value)
 */
class Relation extends AbstractModel
{
    /**
     * Relation statuses
     */
    public const STATUS_ACTIVE     = 1;

    public const STATUS_NOT_ACTIVE = 0;

    /**
     * Entity code.
     * Can be used as part of method name for entity processing
     */
    public const ENTITY = 'tag_relation';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(RelationResource::class);
    }

    /**
     * Load relation by Product (optional), tag, customer and store
     *
     * @param  null|int $productId
     * @param  int      $tagId
     * @param  int      $customerId
     * @param  null|int $storeId
     * @return $this
     */
    public function loadByTagCustomer($productId, $tagId, $customerId, $storeId = null)
    {
        $this->setProductId($productId);
        $this->setTagId($tagId);
        $this->setCustomerId($customerId);
        if ($storeId !== null) {
            $this->setStoreId($storeId);
        }

        $this->_getResource()->loadByTagCustomer($this);
        return $this;
    }

    /**
     * Retrieve Relation Product Ids
     *
     * @return array
     */
    public function getProductIds()
    {
        $ids = $this->getDataByKey('product_ids');
        if ($ids === null) {
            $ids = $this->_getResource()->getProductIds($this);
            $this->setProductIds($ids);
        }

        return $ids;
    }

    /**
     * Retrieve list of related tag ids for products specified in current object
     *
     * @return array
     */
    public function getRelatedTagIds()
    {
        if ($this->getDataByKey('related_tag_ids') === null) {
            $this->setRelatedTagIds($this->_getResource()->getRelatedTagIds($this));
        }

        return $this->getDataByKey('related_tag_ids');
    }

    /**
     * Deactivate tag relations (using current settings)
     *
     * @return $this
     */
    public function deactivate()
    {
        $this->_getResource()->deactivate($this->getTagId(), $this->getCustomerId());
        return $this;
    }

    /**
     * Add TAG to PRODUCT relations
     *
     * @param  Tag   $model
     * @param  array $productIds
     * @return $this
     */
    public function addRelations(Tag $model, $productIds = [])
    {
        $this->setAddedProductIds($productIds);
        $this->setTagId($model->getTagId());
        $this->setCustomerId(null);
        $this->setStoreId($model->getStore());
        $this->_getResource()->addRelations($this);
        return $this;
    }
}
