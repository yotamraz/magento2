<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Api;

use Magento\Tag\Api\Data\TagRelationInterface;

/**
 * Tag relation repository service contract.
 *
 * @api
 */
interface TagRelationRepositoryInterface
{
    /**
     * @param \Magento\Tag\Api\Data\TagRelationInterface $relation
     * @return \Magento\Tag\Api\Data\TagRelationInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TagRelationInterface $relation): TagRelationInterface;

    /**
     * @param int $tagId
     * @param int $productId
     * @param int|null $customerId
     * @param int|null $storeId
     * @return \Magento\Tag\Api\Data\TagRelationInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $tagId, int $productId, ?int $customerId = null, ?int $storeId = null): TagRelationInterface;
}
