<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Tag;

use Magento\Framework\Model\AbstractModel;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag;

/**
 * Tag relation model
 *
 * @method RelationResource _getResource()
 * @method RelationResource getResource()
 * @method int getTagId()
 * @method $this setTagId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method int getProductId()
 * @method $this setProductId(int $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method int getActive()
 * @method $this setActive(int $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 */
class Relation extends AbstractModel
{
    public const STATUS_ACTIVE     = 1;

    public const STATUS_NOT_ACTIVE = 0;

    public const ENTITY = 'tag_relation';

    /**
     * Init resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(RelationResource::class);
    }

    /**
     * Load relation by product, tag, customer and (optionally) store
     *
     * @param int $productId
     * @param int $tagId
     * @param int $customerId
     * @param int|null $storeId
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
     * Retrieve related product ids
     *
     * @return array
     */
    public function getProductIds()
    {
        $ids = $this->getData('product_ids');
        if ($ids === null) {
            $ids = $this->_getResource()->getProductIds($this);
            $this->setProductIds($ids);
        }

        return $ids;
    }

    /**
     * Retrieve related tag ids
     *
     * @return array
     */
    public function getRelatedTagIds()
    {
        if ($this->getData('related_tag_ids') === null) {
            $this->setRelatedTagIds($this->_getResource()->getRelatedTagIds($this));
        }

        return $this->getData('related_tag_ids');
    }

    /**
     * Deactivate tag relations of the customer
     *
     * @return $this
     */
    public function deactivate()
    {
        $this->_getResource()->deactivate($this->getTagId(), $this->getCustomerId());
        return $this;
    }

    /**
     * Replace tag relations with the given product ids
     *
     * @param Tag $model
     * @param array $productIds
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
