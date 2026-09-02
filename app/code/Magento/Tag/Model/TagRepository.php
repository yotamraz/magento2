<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterfaceFactory;
use Magento\Tag\Api\TagRepositoryInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;

class TagRepository implements TagRepositoryInterface
{
    public function __construct(
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly TagSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function get(int $tagId): TagInterface
    {
        $tag = $this->tagFactory->create();
        $this->tagResource->load($tag, $tagId);
        if (!$tag->getId()) {
            throw new NoSuchEntityException(__('Tag with id "%1" does not exist.', $tagId));
        }
        return $tag;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): TagSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function save(TagInterface $tag): TagInterface
    {
        try {
            $this->tagResource->save($tag);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save tag: %1', $e->getMessage()), $e);
        }
        return $tag;
    }

    public function delete(TagInterface $tag): bool
    {
        try {
            $this->tagResource->delete($tag);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete tag: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $tagId): bool
    {
        return $this->delete($this->get($tagId));
    }
}
