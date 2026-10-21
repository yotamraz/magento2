<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RelationTest extends TestCase
{
    /**
     * @var RelationResource|MockObject
     */
    private $resource;

    /**
     * @var Relation
     */
    private $relation;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(RelationResource::class);
        $this->resource->method('getIdFieldName')->willReturn('tag_relation_id');
        $this->relation = new Relation(
            $this->createMock(Context::class),
            new Registry(),
            $this->resource
        );
    }

    public function testConstants(): void
    {
        $this->assertSame(1, Relation::STATUS_ACTIVE);
        $this->assertSame(0, Relation::STATUS_NOT_ACTIVE);
        $this->assertSame('tag_relation', Relation::ENTITY);
    }

    public function testLoadByTagCustomerSetsDataAndDelegates(): void
    {
        $this->resource->expects($this->once())->method('loadByTagCustomer')->with($this->relation);

        $this->assertSame($this->relation, $this->relation->loadByTagCustomer(10, 5, 20, 2));
        $this->assertSame(10, $this->relation->getProductId());
        $this->assertSame(5, $this->relation->getTagId());
        $this->assertSame(20, $this->relation->getCustomerId());
        $this->assertSame(2, $this->relation->getStoreId());
    }

    public function testLoadByTagCustomerWithoutStoreLeavesStoreUnset(): void
    {
        $this->relation->loadByTagCustomer(null, 5, 20);
        $this->assertFalse($this->relation->hasData('store_id'));
    }

    public function testGetProductIdsIsCached(): void
    {
        $this->resource->expects($this->once())
            ->method('getProductIds')
            ->with($this->relation)
            ->willReturn([1, 2]);

        $this->assertSame([1, 2], $this->relation->getProductIds());
        $this->assertSame([1, 2], $this->relation->getProductIds());
    }

    public function testGetRelatedTagIdsIsCached(): void
    {
        $this->resource->expects($this->once())
            ->method('getRelatedTagIds')
            ->with($this->relation)
            ->willReturn([3, 4]);

        $this->assertSame([3, 4], $this->relation->getRelatedTagIds());
        $this->assertSame([3, 4], $this->relation->getRelatedTagIds());
    }

    public function testDeactivate(): void
    {
        $this->relation->setTagId(5)->setCustomerId(20);
        $this->resource->expects($this->once())->method('deactivate')->with(5, 20);

        $this->assertSame($this->relation, $this->relation->deactivate());
    }

    public function testAddRelations(): void
    {
        $tagResource = $this->createMock(TagResource::class);
        $tag = new Tag(
            $this->createMock(Context::class),
            new Registry(),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(RelationFactory::class),
            $this->createMock(DateTime::class),
            $tagResource
        );
        $tag->setData(['tag_id' => 5, 'store' => 3]);
        $this->resource->expects($this->once())->method('addRelations')->with($this->relation);

        $this->assertSame($this->relation, $this->relation->addRelations($tag, [7, 8]));
        $this->assertSame([7, 8], $this->relation->getAddedProductIds());
        $this->assertSame(5, $this->relation->getTagId());
        $this->assertNull($this->relation->getCustomerId());
        $this->assertSame(3, $this->relation->getStoreId());
    }
}
