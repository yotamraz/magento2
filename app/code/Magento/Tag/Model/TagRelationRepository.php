<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tag\Api\Data\TagRelationInterface;
use Magento\Tag\Api\TagRelationRepositoryInterface;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;

class TagRelationRepository implements TagRelationRepositoryInterface
{
    public function __construct(
        private readonly Tag\RelationFactory $relationFactory,
        private readonly RelationResource $relationResource
    ) {
    }

    public function save(TagRelationInterface $relation): TagRelationInterface
    {
        try {
            $this->relationResource->save($relation);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save tag relation: %1', $e->getMessage()), $e);
        }
        return $relation;
    }

    public function get(int $tagId, int $productId, ?int $customerId = null, ?int $storeId = null): TagRelationInterface
    {
        $relation = $this->relationFactory->create();
        $relation->loadByTagCustomer($productId, $tagId, $customerId, $storeId);
        if (!$relation->getId()) {
            throw new NoSuchEntityException(
                __('Tag relation for tag %1, product %2 does not exist.', $tagId, $productId)
            );
        }
        return $relation;
    }
}
