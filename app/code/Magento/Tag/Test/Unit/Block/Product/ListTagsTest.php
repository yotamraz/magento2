<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Tag\Block\Product\ListTags;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListTagsTest extends TestCase
{
    private ListTags $block;
    private Registry&MockObject $registryMock;
    private CollectionFactory&MockObject $collectionFactoryMock;

    protected function setUp(): void
    {
        $this->registryMock          = $this->createMock(Registry::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);

        $objectManager = new ObjectManager($this);
        $this->block   = $objectManager->getObject(ListTags::class, [
            'registry'             => $this->registryMock,
            'tagCollectionFactory' => $this->collectionFactoryMock,
        ]);
    }

    public function testGetProductIdReturnsIdWhenProductRegistered(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(42);

        $this->registryMock->expects($this->once())
            ->method('registry')
            ->with('current_product')
            ->willReturn($productMock);

        $this->assertSame(42, $this->block->getProductId());
    }

    public function testGetProductIdReturnsNullWithoutProduct(): void
    {
        $this->registryMock->method('registry')->with('current_product')->willReturn(null);
        $this->assertNull($this->block->getProductId());
    }

    public function testGetCountReturnsZeroWhenNoProduct(): void
    {
        $this->registryMock->method('registry')->willReturn(null);
        $this->assertSame(0, $this->block->getCount());
    }

    public function testGetTagsReturnsEmptyArrayWhenNoProduct(): void
    {
        $this->registryMock->method('registry')->willReturn(null);
        $this->assertSame([], $this->block->getTags());
    }
}
