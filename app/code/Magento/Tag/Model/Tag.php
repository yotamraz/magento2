<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Event\Observer;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;

/**
 * Tag model
 *
 * @method TagResource _getResource()
 * @method TagResource getResource()
 * @method Collection getCollection()
 * @method Collection getResourceCollection()
 * @method int getBasePopularity()
 * @method $this setBasePopularity(int $value)
 * @method int getFirstCustomerId()
 * @method $this setFirstCustomerId(int $value)
 * @method int getFirstStoreId()
 * @method $this setFirstStoreId(int $value)
 * @method int getStatus()
 * @method $this setStatus(int $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method array getVisibleInStoreIds()
 * @method $this setVisibleInStoreIds(array $value)
 */
class Tag extends AbstractModel
{
    public const STATUS_DISABLED = -1;

    public const STATUS_PENDING = 0;

    public const STATUS_APPROVED = 1;

    /**
     * Statuses for tag relation add
     */
    public const ADD_STATUS_SUCCESS = 'success';

    public const ADD_STATUS_NEW = 'new';

    public const ADD_STATUS_EXIST = 'exist';

    public const ADD_STATUS_REJECTED = 'rejected';

    public const ENTITY = 'tag';

    /**
     * @var string
     */
    protected $_eventPrefix = 'tag';

    /**
     * @var bool
     */
    protected $_addBasePopularity = false;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var RelationFactory
     */
    private $relationFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param DateTime $dateTime
     * @param RelationFactory $relationFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        DateTime $dateTime,
        RelationFactory $relationFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->dateTime = $dateTime;
        $this->relationFactory = $relationFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Init resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(TagResource::class);
    }

    /**
     * Setter for base popularity flag
     *
     * @param bool $flag
     * @return $this
     */
    public function setAddBasePopularity($flag = true)
    {
        $this->_addBasePopularity = $flag;
        return $this;
    }

    /**
     * Getter for base popularity flag
     *
     * @return bool
     */
    public function getAddBasePopularity()
    {
        return $this->_addBasePopularity;
    }

    /**
     * Retrieve tags collection related to the product of the event
     *
     * @param Observer $observer
     * @return Collection
     */
    protected function _getProductEventTagsCollection(Observer $observer)
    {
        return $this->getResourceCollection()
            ->joinRel()
            ->addProductFilter($observer->getEvent()->getProduct()->getId())
            ->addTagGroup()
            ->load();
    }

    /**
     * Get tag popularity
     *
     * @return mixed
     */
    public function getPopularity()
    {
        return $this->getData('popularity');
    }

    /**
     * Get tag name
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * Get tag id
     *
     * @return int|null
     */
    public function getTagId()
    {
        return $this->getData('tag_id');
    }

    /**
     * Get tag ratio
     *
     * @return mixed
     */
    public function getRatio()
    {
        return $this->getData('ratio');
    }

    /**
     * Set tag ratio
     *
     * @param mixed $ratio
     * @return $this
     */
    public function setRatio($ratio)
    {
        $this->setData('ratio', $ratio);
        return $this;
    }

    /**
     * Load tag by name
     *
     * @param string $name
     * @return $this
     */
    public function loadByName($name)
    {
        $this->_getResource()->loadByName($this, $name);
        return $this;
    }

    /**
     * Aggregate tag summary
     *
     * @return $this
     */
    public function aggregate()
    {
        $this->_getResource()->aggregate($this);
        return $this;
    }

    /**
     * Aggregate tags related to the product of the event
     *
     * @param Observer $observer
     * @return $this
     */
    public function productEventAggregate($observer)
    {
        $this->_getProductEventTagsCollection($observer)->walk('aggregate');
        return $this;
    }

    /**
     * Decrement product counters for tags related to the deleted product
     *
     * @param Observer $observer
     * @return $this
     */
    public function productDeleteEventAction($observer)
    {
        $this->_getResource()->decrementProducts($this->_getProductEventTagsCollection($observer)->getAllIds());
        return $this;
    }

    /**
     * Add summary data
     *
     * @deprecated legacy method kept for 1:1 behaviour
     * @param int $storeId
     * @return $this
     */
    public function addSummary($storeId)
    {
        $this->setStoreId($storeId);
        $this->_getResource()->addSummary($this);
        return $this;
    }

    /**
     * @return int
     */
    public function getApprovedStatus()
    {
        return self::STATUS_APPROVED;
    }

    /**
     * @return int
     */
    public function getPendingStatus()
    {
        return self::STATUS_PENDING;
    }

    /**
     * @return int
     */
    public function getDisabledStatus()
    {
        return self::STATUS_DISABLED;
    }

    /**
     * @return string
     */
    public function getTaggedProductsUrl()
    {
        return $this->urlBuilder->getUrl('tag/product/list', ['tagId' => $this->getTagId()]);
    }

    /**
     * @return string
     */
    public function getViewTagUrl()
    {
        return $this->urlBuilder->getUrl('tag/customer/view', ['tagId' => $this->getTagId()]);
    }

    /**
     * @return string
     */
    public function getEditTagUrl()
    {
        return $this->urlBuilder->getUrl('tag/customer/edit', ['tagId' => $this->getTagId()]);
    }

    /**
     * @return string
     */
    public function getRemoveTagUrl()
    {
        return $this->urlBuilder->getUrl('tag/customer/remove', ['tagId' => $this->getTagId()]);
    }

    /**
     * Retrieve ids of products related to the tag
     *
     * @return array
     */
    public function getRelatedProductIds()
    {
        return $this->relationFactory->create()
            ->setTagId($this->getTagId())
            ->setStoreId($this->getStoreId())
            ->setStatusFilter($this->getStatusFilter())
            ->setCustomerId(null)
            ->getProductIds();
    }

    /**
     * Check whether tag is available in the store
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAvailableInStore($storeId = null)
    {
        $storeId ??= $this->storeManager->getStore()->getId();
        return in_array($storeId, (array)$this->getVisibleInStoreIds());
    }

    /**
     * Save tag relation for product and customer
     *
     * @param int $productId
     * @param int $customerId
     * @param int $storeId
     * @return string
     */
    public function saveRelation($productId, $customerId, $storeId)
    {
        /** @var Relation $relationModel */
        $relationModel = $this->relationFactory->create();
        $relationModel->setTagId($this->getId())
            ->setStoreId($storeId)
            ->setProductId($productId)
            ->setCustomerId($customerId)
            ->setActive(Relation::STATUS_ACTIVE)
            ->setCreatedAt($relationModel->getResource()->formatDate($this->dateTime->gmtTimestamp()));

        $result = '';
        $relationModelSaveNeed = false;
        switch ($this->getStatus()) {
            case $this->getApprovedStatus():
                if ($this->_checkLinkBetweenTagProduct($relationModel)) {
                    $relation = $this->_getLinkBetweenTagCustomerProduct($relationModel);
                    if ($relation->getId()) {
                        if (!$relation->getActive()) {
                            // activate relation if it was inactive
                            $relationModel->setId($relation->getId());
                            $relationModelSaveNeed = true;
                        }
                    } else {
                        $relationModelSaveNeed = true;
                    }

                    $result = self::ADD_STATUS_EXIST;
                } else {
                    $relationModelSaveNeed = true;
                    $result = self::ADD_STATUS_SUCCESS;
                }

                break;
            case $this->getPendingStatus():
                $relation = $this->_getLinkBetweenTagCustomerProduct($relationModel);
                if ($relation->getId()) {
                    if (!$relation->getActive()) {
                        $relationModel->setId($relation->getId());
                        $relationModelSaveNeed = true;
                    }
                } else {
                    $relationModelSaveNeed = true;
                }

                $result = self::ADD_STATUS_NEW;
                break;
            case $this->getDisabledStatus():
                if ($this->_checkLinkBetweenTagCustomerProduct($relationModel)) {
                    $result = self::ADD_STATUS_REJECTED;
                } else {
                    $this->setStatus($this->getPendingStatus())->save();
                    $relationModelSaveNeed = true;
                    $result = self::ADD_STATUS_NEW;
                }

                break;
        }

        if ($relationModelSaveNeed) {
            $relationModel->save();
        }

        return $result;
    }

    /**
     * Check whether tag is already linked to the product (any customer)
     *
     * @param Relation $relationModel
     * @return bool
     */
    protected function _checkLinkBetweenTagProduct($relationModel)
    {
        $customerId = $relationModel->getCustomerId();
        $relationModel->setCustomerId(null);
        $result = in_array($relationModel->getProductId(), $relationModel->getProductIds());
        $relationModel->setCustomerId($customerId);
        return $result;
    }

    /**
     * Check whether tag is already linked to the product by the customer
     *
     * @param Relation $relationModel
     * @return bool
     */
    protected function _checkLinkBetweenTagCustomerProduct($relationModel)
    {
        return (count($this->_getLinkBetweenTagCustomerProduct($relationModel)->getProductIds()) > 0);
    }

    /**
     * Load relation between tag, customer and product
     *
     * @param Relation $relationModel
     * @return Relation
     */
    protected function _getLinkBetweenTagCustomerProduct($relationModel)
    {
        return $this->relationFactory->create()->loadByTagCustomer(
            $relationModel->getProductId(),
            $this->getId(),
            $relationModel->getCustomerId(),
            $relationModel->getStoreId()
        );
    }

    /**
     * Save assigned products after the tag is saved
     *
     * @return $this
     */
    public function afterSave()
    {
        if ($this->hasData('tag_assigned_products')) {
            $this->relationFactory->create()->addRelations($this, $this->getData('tag_assigned_products'));
        }

        return parent::afterSave();
    }
}
