<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Api\Data;

/**
 * Tag entity service contract interface.
 *
 * @api
 */
interface TagInterface
{
    public const TAG_ID = 'tag_id';
    public const NAME = 'name';
    public const STATUS = 'status';
    public const FIRST_CUSTOMER_ID = 'first_customer_id';
    public const FIRST_STORE_ID = 'first_store_id';

    /**
     * @return int|null
     */
    public function getTagId(): ?int;

    /**
     * @param int $tagId
     * @return $this
     */
    public function setTagId(int $tagId): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return int
     */
    public function getStatus(): int;

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self;

    /**
     * @return int|null
     */
    public function getFirstCustomerId(): ?int;

    /**
     * @param int|null $customerId
     * @return $this
     */
    public function setFirstCustomerId(?int $customerId): self;

    /**
     * @return int|null
     */
    public function getFirstStoreId(): ?int;

    /**
     * @param int|null $storeId
     * @return $this
     */
    public function setFirstStoreId(?int $storeId): self;
}
