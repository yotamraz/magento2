<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RelationTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $resource;

    /**
     * @var Relation
     */
    private $model;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(RelationResource::class);
        $this->model = (new ObjectManager($this))->getObject(Relation::class, ['resource' => $this->resource]);
    }

    public function testConstants(): void
    {
        $this->assertSame(1, Relation::STATUS_ACTIVE);
        $this->assertSame(0, Relation::STATUS_NOT_ACTIVE);
        $this->assertSame('tag_relation', Relation::ENTITY);
    }

    public function testGetProductIdsIsCached(): void
    {
        $this->resource->expects($this->once())->method('getProductIds')->with($this->model)->willReturn([4, 5]);
        $this->assertSame([4, 5], $this->model->getProductIds());
        $this->assertSame([4, 5], $this->model->getProductIds());
    }

    public function testLoadByTagCustomerSetsKeys(): void
    {
        $this->resource->expects($this->once())->method('loadByTagCustomer')->with($this->model);
        $this->model->loadByTagCustomer(3, 2, 1, 4);
        $this->assertSame(3, $this->model->getProductId());
        $this->assertSame(2, $this->model->getTagId());
        $this->assertSame(1, $this->model->getCustomerId());
        $this->assertSame(4, $this->model->getStoreId());
    }

    public function testDeactivate(): void
    {
        $this->model->setTagId(2)->setCustomerId(1);
        $this->resource->expects($this->once())->method('deactivate')->with(2, 1);
        $this->assertSame($this->model, $this->model->deactivate());
    }
}
