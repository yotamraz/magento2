<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterface;

/**
 * Tag repository service contract.
 *
 * @api
 */
interface TagRepositoryInterface
{
    /**
     * @param int $tagId
     * @return \Magento\Tag\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $tagId): TagInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Tag\Api\Data\TagSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TagSearchResultsInterface;

    /**
     * @param \Magento\Tag\Api\Data\TagInterface $tag
     * @return \Magento\Tag\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TagInterface $tag): TagInterface;

    /**
     * @param \Magento\Tag\Api\Data\TagInterface $tag
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(TagInterface $tag): bool;

    /**
     * @param int $tagId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $tagId): bool;
}
