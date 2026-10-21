<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Tag\Model\ResourceModel\Tag\Relation as ResourceRelation;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tag Relation model
 */
class RelationTest extends TestCase
{
    /**
     * @var ResourceRelation|MockObject
     */
    private $resource;

    /**
     * @var Relation
     */
    private $relation;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceRelation::class);
        $this->relation = new Relation(
            $this->createMock(Context::class),
            $this->createMock(Registry::class),
            $this->resource
        );
    }

    public function testConstants(): void
    {
        $this->assertSame(1, Relation::STATUS_ACTIVE);
        $this->assertSame(0, Relation::STATUS_NOT_ACTIVE);
        $this->assertSame('tag_relation', Relation::ENTITY);
    }

    public function testLoadByTagCustomerSetsKeysAndDelegates(): void
    {
        $this->resource->expects($this->once())->method('loadByTagCustomer')->with($this->relation);
        $this->assertSame($this->relation, $this->relation->loadByTagCustomer(10, 20, 30));
        $this->assertSame(10, $this->relation->getProductId());
        $this->assertSame(20, $this->relation->getTagId());
        $this->assertSame(30, $this->relation->getCustomerId());
        $this->assertFalse($this->relation->hasStoreId());
    }

    public function testLoadByTagCustomerWithStore(): void
    {
        $this->relation->loadByTagCustomer(null, 20, 30, 2);
        $this->assertSame(2, $this->relation->getStoreId());
    }

    public function testGetProductIdsIsCached(): void
    {
        $this->resource->expects($this->once())->method('getProductIds')->willReturn([1, 2]);
        $this->assertSame([1, 2], $this->relation->getProductIds());
        $this->assertSame([1, 2], $this->relation->getProductIds());
    }

    public function testGetRelatedTagIdsIsCached(): void
    {
        $this->resource->expects($this->once())->method('getRelatedTagIds')->willReturn([7]);
        $this->assertSame([7], $this->relation->getRelatedTagIds());
        $this->assertSame([7], $this->relation->getRelatedTagIds());
    }

    public function testDeactivate(): void
    {
        $this->relation->setTagId(3)->setCustomerId(4);
        $this->resource->expects($this->once())->method('deactivate')->with(3, 4);
        $this->assertSame($this->relation, $this->relation->deactivate());
    }

    public function testAddRelations(): void
    {
        $tag = $this->createMock(Tag::class);
        $tag->method('getTagId')->willReturn(11);
        $tag->method('__call')->willReturnCallback(static fn ($method) => $method === 'getStore' ? 2 : null);

        $this->resource->expects($this->once())->method('addRelations')->with($this->relation);
        $this->relation->addRelations($tag, [5, 6]);

        $this->assertSame([5, 6], $this->relation->getAddedProductIds());
        $this->assertSame(11, $this->relation->getTagId());
        $this->assertNull($this->relation->getCustomerId());
        $this->assertSame(2, $this->relation->getStoreId());
    }
}
