<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Api\Data;

/**
 * Tag relation service contract interface.
 *
 * @api
 */
interface TagRelationInterface
{
    public const TAG_RELATION_ID = 'tag_relation_id';
    public const TAG_ID = 'tag_id';
    public const CUSTOMER_ID = 'customer_id';
    public const PRODUCT_ID = 'product_id';
    public const STORE_ID = 'store_id';
    public const ACTIVE = 'active';
    public const CREATED_AT = 'created_at';

    /** @return int|null */
    public function getTagRelationId(): ?int;

    /** @return int */
    public function getTagId(): int;

    /** @param int $tagId */
    public function setTagId(int $tagId): self;

    /** @return int|null */
    public function getCustomerId(): ?int;

    /** @param int|null $customerId */
    public function setCustomerId(?int $customerId): self;

    /** @return int */
    public function getProductId(): int;

    /** @param int $productId */
    public function setProductId(int $productId): self;

    /** @return int */
    public function getStoreId(): int;

    /** @param int $storeId */
    public function setStoreId(int $storeId): self;

    /** @return int */
    public function getActive(): int;

    /** @param int $active */
    public function setActive(int $active): self;
}
