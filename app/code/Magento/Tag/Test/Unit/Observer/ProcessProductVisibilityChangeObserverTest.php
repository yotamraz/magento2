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
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Tag\Observer\ProcessProductVisibilityChangeObserver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProcessProductVisibilityChangeObserverTest extends TestCase
{
    private ProcessProductVisibilityChangeObserver $observer;
    private IndexerRegistry&MockObject $indexerRegistryMock;

    protected function setUp(): void
    {
        $this->indexerRegistryMock = $this->createMock(IndexerRegistry::class);
        $this->observer = new ProcessProductVisibilityChangeObserver($this->indexerRegistryMock);
    }

    private function makeObserver(?Product $product): Observer
    {
        $event = new Event(['product' => $product]);
        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($event);
        return $observerMock;
    }

    public function testExecuteInvalidatesIndexerWhenVisibilityChanges(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(1);
        $productMock->method('dataHasChangedFor')
            ->willReturnMap([['visibility', true], ['status', false]]);

        $indexerMock = $this->createMock(IndexerInterface::class);
        $indexerMock->expects($this->once())->method('isScheduled')->willReturn(false);
        $indexerMock->expects($this->once())->method('invalidate');

        $this->indexerRegistryMock->expects($this->once())->method('get')->with('tag_summary')
            ->willReturn($indexerMock);

        $this->observer->execute($this->makeObserver($productMock));
    }

    public function testExecuteInvalidatesIndexerWhenStatusChanges(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(1);
        $productMock->method('dataHasChangedFor')
            ->willReturnMap([['visibility', false], ['status', true]]);

        $indexerMock = $this->createMock(IndexerInterface::class);
        $indexerMock->expects($this->once())->method('isScheduled')->willReturn(false);
        $indexerMock->expects($this->once())->method('invalidate');

        $this->indexerRegistryMock->method('get')->willReturn($indexerMock);
        $this->observer->execute($this->makeObserver($productMock));
    }

    public function testExecuteSkipsInvalidateWhenNothingChanged(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(1);
        $productMock->method('dataHasChangedFor')->willReturn(false);

        $this->indexerRegistryMock->expects($this->never())->method('get');
        $this->observer->execute($this->makeObserver($productMock));
    }

    public function testExecuteSkipsInvalidateWhenScheduled(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(1);
        $productMock->method('dataHasChangedFor')
            ->willReturnMap([['visibility', true], ['status', false]]);

        $indexerMock = $this->createMock(IndexerInterface::class);
        $indexerMock->expects($this->once())->method('isScheduled')->willReturn(true);
        $indexerMock->expects($this->never())->method('invalidate');

        $this->indexerRegistryMock->method('get')->willReturn($indexerMock);
        $this->observer->execute($this->makeObserver($productMock));
    }

    public function testExecuteSkipsWhenNoProduct(): void
    {
        $this->indexerRegistryMock->expects($this->never())->method('get');
        $this->observer->execute($this->makeObserver(null));
    }
}
