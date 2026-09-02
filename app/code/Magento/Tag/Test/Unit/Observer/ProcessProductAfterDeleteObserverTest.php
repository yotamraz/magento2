<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use Magento\Tag\Observer\ProcessProductAfterDeleteObserver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProcessProductAfterDeleteObserverTest extends TestCase
{
    private ProcessProductAfterDeleteObserver $observer;
    private CollectionFactory&MockObject $collectionFactoryMock;
    private TagResource&MockObject $tagResourceMock;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->tagResourceMock       = $this->createMock(TagResource::class);

        $this->observer = new ProcessProductAfterDeleteObserver(
            $this->collectionFactoryMock,
            $this->tagResourceMock
        );
    }

    private function makeObserver(?Product $product): Observer
    {
        $event = new Event(['product' => $product]);
        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($event);
        return $observerMock;
    }

    public function testExecuteDecrementsProductsForAffectedTags(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(10);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->method('joinRel')->willReturnSelf();
        $collectionMock->method('addProductFilter')->willReturnSelf();
        $collectionMock->method('addTagGroup')->willReturnSelf();
        $collectionMock->method('load')->willReturnSelf();
        $collectionMock->method('getAllIds')->willReturn([1, 2, 3]);

        $this->collectionFactoryMock->expects($this->once())->method('create')->willReturn($collectionMock);
        $this->tagResourceMock->expects($this->once())->method('decrementProducts')->with([1, 2, 3]);

        $this->observer->execute($this->makeObserver($productMock));
    }

    public function testExecuteSkipsWhenNoProduct(): void
    {
        $this->collectionFactoryMock->expects($this->never())->method('create');
        $this->tagResourceMock->expects($this->never())->method('decrementProducts');

        $this->observer->execute($this->makeObserver(null));
    }

    public function testExecuteSkipsDecrementWhenNoTagIds(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(99);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->method('joinRel')->willReturnSelf();
        $collectionMock->method('addProductFilter')->willReturnSelf();
        $collectionMock->method('addTagGroup')->willReturnSelf();
        $collectionMock->method('load')->willReturnSelf();
        $collectionMock->method('getAllIds')->willReturn([]);

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);
        $this->tagResourceMock->expects($this->never())->method('decrementProducts');

        $this->observer->execute($this->makeObserver($productMock));
    }
}
