<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $resource;

    /**
     * @var Tag
     */
    private $model;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(TagResource::class);
        $this->model = (new ObjectManager($this))->getObject(Tag::class, ['resource' => $this->resource]);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(-1, Tag::STATUS_DISABLED);
        $this->assertSame(0, Tag::STATUS_PENDING);
        $this->assertSame(1, Tag::STATUS_APPROVED);
        $this->assertSame('success', Tag::ADD_STATUS_SUCCESS);
        $this->assertSame('new', Tag::ADD_STATUS_NEW);
        $this->assertSame('exist', Tag::ADD_STATUS_EXIST);
        $this->assertSame('rejected', Tag::ADD_STATUS_REJECTED);
        $this->assertSame('tag', Tag::ENTITY);
    }

    public function testStatusGetters(): void
    {
        $this->assertSame(Tag::STATUS_APPROVED, $this->model->getApprovedStatus());
        $this->assertSame(Tag::STATUS_PENDING, $this->model->getPendingStatus());
        $this->assertSame(Tag::STATUS_DISABLED, $this->model->getDisabledStatus());
    }

    public function testSimpleGettersAndSetters(): void
    {
        $this->model->setData(['tag_id' => 5, 'name' => 'foo', 'popularity' => 7]);
        $this->assertSame(5, $this->model->getTagId());
        $this->assertSame('foo', $this->model->getName());
        $this->assertSame(7, $this->model->getPopularity());
        $this->assertSame($this->model, $this->model->setRatio(3));
        $this->assertSame(3, $this->model->getRatio());
    }

    public function testAddBasePopularityFlag(): void
    {
        $this->assertFalse($this->model->getAddBasePopularity());
        $this->assertSame($this->model, $this->model->setAddBasePopularity());
        $this->assertTrue($this->model->getAddBasePopularity());
    }

    public function testLoadByNameDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('loadByName')->with($this->model, 'foo');
        $this->assertSame($this->model, $this->model->loadByName('foo'));
    }

    public function testAggregateDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('aggregate')->with($this->model);
        $this->assertSame($this->model, $this->model->aggregate());
    }

    public function testIsAvailableInStore(): void
    {
        $this->model->setVisibleInStoreIds([1, 2]);
        $this->assertTrue($this->model->isAvailableInStore(2));
        $this->assertFalse($this->model->isAvailableInStore(3));
    }
}
