<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Tag search results interface.
 *
 * @api
 */
interface TagSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Magento\Tag\Api\Data\TagInterface[]
     */
    public function getItems(): array;

    /**
     * @param \Magento\Tag\Api\Data\TagInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
