<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Tag\Api\Data\TagInterface;

/**
 * Tag entity model.
 *
 * @api
 * @method int getTagId()
 * @method string getName()
 * @method int getStatus()
 * @method int|null getFirstCustomerId()
 * @method int|null getFirstStoreId()
 * @method int getPopularity()
 * @method int getRatio()
 * @method $this setName(string $value)
 * @method $this setStatus(int $value)
 * @method $this setFirstCustomerId(int $value)
 * @method $this setFirstStoreId(int $value)
 * @method $this setStoreId(int $value)
 * @method $this setPopularity(int $value)
 * @method $this setRatio(int $value)
 * @method array getVisibleInStoreIds()
 * @method $this setVisibleInStoreIds(array $value)
 */
class Tag extends AbstractModel implements TagInterface
{
    public const STATUS_DISABLED = -1;
    public const STATUS_PENDING  = 0;
    public const STATUS_APPROVED = 1;

    public const ADD_STATUS_SUCCESS  = 'success';
    public const ADD_STATUS_NEW      = 'new';
    public const ADD_STATUS_EXIST    = 'exist';
    public const ADD_STATUS_REJECTED = 'rejected';

    public const ENTITY    = 'tag';
    public const CACHE_TAG = 'tag';

    protected $_eventPrefix = 'tag';
    protected $_cacheTag    = self::CACHE_TAG;

    /**
     * @var \Magento\Tag\Model\Tag\RelationFactory
     */
    private $relationFactory;

    /**
     * @var \Magento\Framework\Indexer\IndexerRegistry
     */
    private $indexerRegistry;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Tag\Model\Tag\RelationFactory $relationFactory,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->relationFactory  = $relationFactory;
        $this->indexerRegistry  = $indexerRegistry;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct(): void
    {
        $this->_init(\Magento\Tag\Model\ResourceModel\Tag::class);
    }

    /**
     * Schedule tag_summary index invalidation after commit.
     */
    public function afterCommitCallback(): static
    {
        parent::afterCommitCallback();
        $indexer = $this->indexerRegistry->get('tag_summary');
        if (!$indexer->isScheduled()) {
            $indexer->invalidate();
        }
        return $this;
    }

    // --- TagInterface implementation ---

    public function getName(): ?string
    {
        return $this->_getData(self::NAME);
    }

    public function setName(string $name): TagInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getTagId(): ?int
    {
        $id = $this->_getData(self::TAG_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setTagId(int $tagId): TagInterface
    {
        return $this->setData(self::TAG_ID, $tagId);
    }

    public function getStatus(): int
    {
        return (int) $this->_getData(self::STATUS);
    }

    public function setStatus(int $status): TagInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getFirstCustomerId(): ?int
    {
        $id = $this->_getData(self::FIRST_CUSTOMER_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setFirstCustomerId(?int $customerId): TagInterface
    {
        return $this->setData(self::FIRST_CUSTOMER_ID, $customerId);
    }

    public function getFirstStoreId(): ?int
    {
        $id = $this->_getData(self::FIRST_STORE_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setFirstStoreId(?int $storeId): TagInterface
    {
        return $this->setData(self::FIRST_STORE_ID, $storeId);
    }

    // --- Named accessors ---

    public function getApprovedStatus(): int
    {
        return self::STATUS_APPROVED;
    }

    public function getPendingStatus(): int
    {
        return self::STATUS_PENDING;
    }

    public function getDisabledStatus(): int
    {
        return self::STATUS_DISABLED;
    }

    /**
     * Load tag by name.
     */
    public function loadByName(string $name): static
    {
        $this->_getResource()->loadByName($this, $name);
        return $this;
    }

    /**
     * Trigger aggregation through the resource model.
     */
    public function aggregate(): static
    {
        $this->_getResource()->aggregate($this);
        return $this;
    }

    /**
     * Save tag–product–customer–store relation and return an ADD_STATUS_* constant.
     */
    public function saveRelation(int $productId, ?int $customerId, int $storeId): string
    {
        /** @var \Magento\Tag\Model\Tag\Relation $relationModel */
        $relationModel = $this->relationFactory->create();
        $relationModel->setTagId((int) $this->getId())
            ->setStoreId($storeId)
            ->setProductId($productId)
            ->setCustomerId($customerId)
            ->setActive(Tag\Relation::STATUS_ACTIVE);

        $result = '';
        $needSave = false;

        switch ($this->getStatus()) {
            case self::STATUS_APPROVED:
                if ($this->_checkLinkBetweenTagProduct($relationModel)) {
                    $existing = $this->_getLinkBetweenTagCustomerProduct($relationModel);
                    if ($existing->getId()) {
                        if (!$existing->getActive()) {
                            $relationModel->setId($existing->getId());
                            $needSave = true;
                        }
                    } else {
                        $needSave = true;
                    }
                    $result = self::ADD_STATUS_EXIST;
                } else {
                    $needSave = true;
                    $result   = self::ADD_STATUS_SUCCESS;
                }
                break;

            case self::STATUS_PENDING:
                $existing = $this->_getLinkBetweenTagCustomerProduct($relationModel);
                if ($existing->getId()) {
                    if (!$existing->getActive()) {
                        $relationModel->setId($existing->getId());
                        $needSave = true;
                    }
                } else {
                    $needSave = true;
                }
                $result = self::ADD_STATUS_NEW;
                break;

            case self::STATUS_DISABLED:
                if ($this->_checkLinkBetweenTagCustomerProduct($relationModel)) {
                    $result = self::ADD_STATUS_REJECTED;
                } else {
                    $this->setStatus(self::STATUS_PENDING)->save();
                    $needSave = true;
                    $result   = self::ADD_STATUS_NEW;
                }
                break;
        }

        if ($needSave) {
            $relationModel->save();
        }

        return $result;
    }

    protected function _checkLinkBetweenTagProduct(Tag\Relation $relationModel): bool
    {
        $customerId = $relationModel->getCustomerId();
        $relationModel->setCustomerId(null);
        $result = in_array($relationModel->getProductId(), $relationModel->getProductIds());
        $relationModel->setCustomerId($customerId);
        return $result;
    }

    protected function _checkLinkBetweenTagCustomerProduct(Tag\Relation $relationModel): bool
    {
        return count($this->_getLinkBetweenTagCustomerProduct($relationModel)->getProductIds()) > 0;
    }

    protected function _getLinkBetweenTagCustomerProduct(Tag\Relation $relationModel): Tag\Relation
    {
        return $this->relationFactory->create()->loadByTagCustomer(
            $relationModel->getProductId(),
            (int) $this->getId(),
            $relationModel->getCustomerId(),
            $relationModel->getStoreId()
        );
    }

    protected function _afterSave(): static
    {
        if ($this->hasData('tag_assigned_products')) {
            $relation = $this->relationFactory->create();
            $relation->addRelations($this, $this->getDataByKey('tag_assigned_products'));
        }
        return parent::_afterSave();
    }

    protected function _beforeDelete(): static
    {
        return parent::_beforeDelete();
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . ($this->getTagId() ?? $this->getId())];
    }
}
