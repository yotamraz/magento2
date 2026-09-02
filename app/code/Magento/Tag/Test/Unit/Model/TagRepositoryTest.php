<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tag\Api\Data\TagSearchResultsInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterfaceFactory;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\Tag\Model\TagRepository;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagRepositoryTest extends TestCase
{
    private TagRepository $repository;
    private TagFactory&MockObject $tagFactoryMock;
    private TagResource&MockObject $tagResourceMock;
    private CollectionFactory&MockObject $collectionFactoryMock;
    private TagSearchResultsInterfaceFactory&MockObject $searchResultsFactoryMock;
    private CollectionProcessorInterface&MockObject $collectionProcessorMock;

    protected function setUp(): void
    {
        $this->tagFactoryMock           = $this->createMock(TagFactory::class);
        $this->tagResourceMock          = $this->createMock(TagResource::class);
        $this->collectionFactoryMock    = $this->createMock(CollectionFactory::class);
        $this->searchResultsFactoryMock = $this->createMock(TagSearchResultsInterfaceFactory::class);
        $this->collectionProcessorMock  = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new TagRepository(
            $this->tagFactoryMock,
            $this->tagResourceMock,
            $this->collectionFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionProcessorMock
        );
    }

    public function testGetReturnsLoadedTag(): void
    {
        $tagMock = $this->createMock(Tag::class);
        $tagMock->expects($this->once())->method('getId')->willReturn(5);
        $this->tagFactoryMock->expects($this->once())->method('create')->willReturn($tagMock);
        $this->tagResourceMock->expects($this->once())->method('load')->with($tagMock, 5);

        $result = $this->repository->get(5);
        $this->assertSame($tagMock, $result);
    }

    public function testGetThrowsNoSuchEntityWhenNotFound(): void
    {
        $tagMock = $this->createMock(Tag::class);
        $tagMock->expects($this->once())->method('getId')->willReturn(null);
        $this->tagFactoryMock->expects($this->once())->method('create')->willReturn($tagMock);
        $this->tagResourceMock->expects($this->once())->method('load');

        $this->expectException(NoSuchEntityException::class);
        $this->repository->get(999);
    }

    public function testSavePersistsTag(): void
    {
        $tagMock = $this->createMock(Tag::class);
        $this->tagResourceMock->expects($this->once())->method('save')->with($tagMock);

        $result = $this->repository->save($tagMock);
        $this->assertSame($tagMock, $result);
    }

    public function testSaveThrowsCouldNotSaveOnException(): void
    {
        $tagMock = $this->createMock(Tag::class);
        $this->tagResourceMock->method('save')->willThrowException(new \Exception('DB error'));

        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($tagMock);
    }

    public function testDeleteByIdCallsDeleteOnLoadedTag(): void
    {
        $tagMock = $this->createMock(Tag::class);
        $tagMock->method('getId')->willReturn(7);
        $this->tagFactoryMock->method('create')->willReturn($tagMock);
        $this->tagResourceMock->method('load');
        $this->tagResourceMock->expects($this->once())->method('delete')->with($tagMock);

        $this->assertTrue($this->repository->deleteById(7));
    }

    public function testDeleteThrowsCouldNotDeleteOnException(): void
    {
        $tagMock = $this->createMock(Tag::class);
        $this->tagResourceMock->method('delete')->willThrowException(new \Exception('DB error'));

        $this->expectException(CouldNotDeleteException::class);
        $this->repository->delete($tagMock);
    }

    public function testGetListWithEmptyCollectionReturnsZeroCount(): void
    {
        $criterionMock     = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock    = $this->createMock(Collection::class);
        $searchResultsMock = $this->createMock(TagSearchResultsInterface::class);

        $this->collectionFactoryMock->expects($this->once())->method('create')->willReturn($collectionMock);
        $this->collectionProcessorMock->expects($this->once())->method('process')->with($criterionMock, $collectionMock);
        $collectionMock->method('getItems')->willReturn([]);
        $collectionMock->method('getSize')->willReturn(0);

        $this->searchResultsFactoryMock->expects($this->once())->method('create')->willReturn($searchResultsMock);
        $searchResultsMock->expects($this->once())->method('setSearchCriteria')->with($criterionMock);
        $searchResultsMock->expects($this->once())->method('setItems')->with([]);
        $searchResultsMock->expects($this->once())->method('setTotalCount')->with(0);

        $result = $this->repository->getList($criterionMock);
        $this->assertSame($searchResultsMock, $result);
    }

    public function testGetListWithItemsPropagatesThemToSearchResults(): void
    {
        $criterionMock     = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock    = $this->createMock(Collection::class);
        $searchResultsMock = $this->createMock(TagSearchResultsInterface::class);
        $tag1              = $this->createMock(Tag::class);
        $tag2              = $this->createMock(Tag::class);
        $items             = [$tag1, $tag2];

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);
        $this->collectionProcessorMock->method('process');
        $collectionMock->method('getItems')->willReturn($items);
        $collectionMock->method('getSize')->willReturn(2);

        $this->searchResultsFactoryMock->method('create')->willReturn($searchResultsMock);
        $searchResultsMock->expects($this->once())->method('setSearchCriteria')->with($criterionMock);
        $searchResultsMock->expects($this->once())->method('setItems')->with($items);
        $searchResultsMock->expects($this->once())->method('setTotalCount')->with(2);

        $result = $this->repository->getList($criterionMock);
        $this->assertSame($searchResultsMock, $result);
    }
}
