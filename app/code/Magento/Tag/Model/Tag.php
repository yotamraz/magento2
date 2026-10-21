<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection as TagCollection;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;

/**
 * Tag model
 *
 * @method TagResource _getResource()
 * @method int getBasePopularity()
 * @method TagCollection getCollection()
 * @method int getFirstCustomerId()
 * @method int getFirstStoreId()
 * @method TagResource getResource()
 * @method TagCollection getResourceCollection()
 * @method int getStatus()
 * @method array getStatusFilter()
 * @method int getStore()
 * @method int getStoreId()
 * @method array getVisibleInStoreIds()
 * @method bool hasBasePopularity()
 * @method bool hasStoreId()
 * @method $this setBasePopularity(int $value)
 * @method $this setFirstCustomerId(int $value)
 * @method $this setFirstStoreId(int $value)
 * @method $this setName(string $value)
 * @method $this setStatus(int $value)
 * @method $this setStore(int $value)
 * @method $this setStoreId(int $value)
 * @method $this setVisibleInStoreIds(array $value)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Tag extends AbstractModel
{
    public const STATUS_DISABLED = -1;

    public const STATUS_PENDING = 0;

    public const STATUS_APPROVED = 1;

    // statuses for tag relation add
    public const ADD_STATUS_SUCCESS = 'success';

    public const ADD_STATUS_NEW = 'new';

    public const ADD_STATUS_EXIST = 'exist';

    public const ADD_STATUS_REJECTED = 'rejected';

    /**
     * Entity code.
     * Can be used as part of method name for entity processing
     */
    public const ENTITY = 'tag';

    /**
     * Event prefix for observer
     *
     * @var string
     */
    protected $_eventPrefix = 'tag';

    /**
     * This flag means should we or not add base popularity on tag load
     *
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
     * @var RelationFactory
     */
    private $relationFactory;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param RelationFactory $relationFactory
     * @param DateTime $dateTime
     * @param AbstractResource|null $resource
     * @param AbstractCollection|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        RelationFactory $relationFactory,
        DateTime $dateTime,
        ?AbstractResource $resource = null,
        ?AbstractCollection $resourceCollection = null,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->relationFactory = $relationFactory;
        $this->dateTime = $dateTime;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(TagResource::class);
    }

    /**
     * Setter for addBasePopularity flag
     *
     * @param  bool  $flag
     * @return $this
     */
    public function setAddBasePopularity($flag = true)
    {
        $this->_addBasePopularity = $flag;
        return $this;
    }

    /**
     * Getter for addBasePopularity flag
     *
     * @return bool
     */
    public function getAddBasePopularity()
    {
        return $this->_addBasePopularity;
    }

    /**
     * Product event tags collection getter
     *
     * @param Observer $observer
     * @return TagCollection
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
     * @return int
     */
    public function getPopularity()
    {
        return $this->_getData('popularity');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_getData('name');
    }

    /**
     * @return int
     */
    public function getTagId()
    {
        return $this->_getData('tag_id');
    }

    /**
     * @return int
     */
    public function getRatio()
    {
        return $this->_getData('ratio');
    }

    /**
     * @param  int   $ratio
     * @return $this
     */
    public function setRatio($ratio)
    {
        $this->setData('ratio', $ratio);
        return $this;
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function loadByName($name)
    {
        $this->_getResource()->loadByName($this, $name);
        return $this;
    }

    /**
     * @return $this
     */
    public function aggregate()
    {
        $this->_getResource()->aggregate($this);
        return $this;
    }

    /**
     * @param  Observer $observer
     * @return $this
     */
    public function productEventAggregate($observer)
    {
        $this->_getProductEventTagsCollection($observer)->walk('aggregate');
        return $this;
    }

    /**
     * Product delete event action
     *
     * @param  Observer $observer
     * @return $this
     */
    public function productDeleteEventAction($observer)
    {
        $this->_getResource()->decrementProducts($this->_getProductEventTagsCollection($observer)->getAllIds());
        return $this;
    }

    /**
     * Add summary data to current object
     *
     * @param  int   $storeId
     * @return $this
     */
    public function addSummary($storeId)
    {
        $this->setStoreId($storeId);
        $this->_getResource()->addSummary($this);
        return $this;
    }

    /**
     * Getter for self::STATUS_APPROVED
     *
     * @return int
     */
    public function getApprovedStatus()
    {
        return self::STATUS_APPROVED;
    }

    /**
     * Getter for self::STATUS_PENDING
     *
     * @return int
     */
    public function getPendingStatus()
    {
        return self::STATUS_PENDING;
    }

    /**
     * Getter for self::STATUS_DISABLED
     *
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
     * Retrieves array of related product IDs
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
     * Checks is available current tag in specified store
     *
     * @param  int  $storeId
     * @return bool
     */
    public function isAvailableInStore($storeId = null)
    {
        $storeId ??= $this->storeManager->getStore()->getId();
        return in_array($storeId, $this->getVisibleInStoreIds() ?? []);
    }

    /**
     * Protect tag deletion from non-admin scope (legacy Mage_Core_Model_Abstract::_protectFromNonAdmin)
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeDelete()
    {
        if (!$this->_registry->registry('isSecureArea')
            && (int)$this->storeManager->getStore()->getId() !== Store::DEFAULT_STORE_ID
        ) {
            throw new LocalizedException(__('Cannot complete this operation from non-admin area.'));
        }

        return parent::beforeDelete();
    }

    /**
     * Save tag relation with product, customer and store
     *
     * @param  int    $productId
     * @param  int    $customerId
     * @param  int    $storeId
     * @return string - relation add status
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
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
            ->setCreatedAt($this->dateTime->gmtDate());

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
     * Check whether product is already marked in store with tag
     *
     * @param  Relation $relationModel
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
     * Check whether product is already marked in store with tag by customer
     *
     * @param  Relation $relationModel
     * @return bool
     */
    protected function _checkLinkBetweenTagCustomerProduct($relationModel)
    {
        return (count($this->_getLinkBetweenTagCustomerProduct($relationModel)->getProductIds()) > 0);
    }

    /**
     * Get relation model for product marked in store with tag by customer
     *
     * @param  Relation $relationModel
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
     * Processing object after save data
     *
     * @return $this
     */
    public function afterSave()
    {
        if ($this->hasData('tag_assigned_products')) {
            $tagRelationModel = $this->relationFactory->create();
            $tagRelationModel->addRelations($this, $this->getDataByKey('tag_assigned_products'));
        }

        return parent::afterSave();
    }
}
