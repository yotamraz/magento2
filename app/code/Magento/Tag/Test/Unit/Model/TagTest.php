<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTime as CoreDate;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as ResourceTag;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tag model
 */
class TagTest extends TestCase
{
    /**
     * @var ResourceTag|MockObject
     */
    private $resource;

    /**
     * @var RelationFactory|MockObject
     */
    private $relationFactory;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilder;

    /**
     * @var Tag
     */
    private $tag;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceTag::class);
        $this->relationFactory = $this->createMock(RelationFactory::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->tag = new Tag(
            $this->createMock(Context::class),
            $this->createMock(Registry::class),
            $this->relationFactory,
            $this->urlBuilder,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(CoreDate::class),
            $this->createMock(DateTime::class),
            $this->resource
        );
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
        $this->assertSame(Tag::STATUS_APPROVED, $this->tag->getApprovedStatus());
        $this->assertSame(Tag::STATUS_PENDING, $this->tag->getPendingStatus());
        $this->assertSame(Tag::STATUS_DISABLED, $this->tag->getDisabledStatus());
    }

    public function testDataAccessors(): void
    {
        $this->tag->setData(['tag_id' => 5, 'name' => 'summer', 'popularity' => 3]);
        $this->assertSame(5, $this->tag->getTagId());
        $this->assertSame('summer', $this->tag->getName());
        $this->assertSame(3, $this->tag->getPopularity());
        $this->assertNull($this->tag->getRatio());
        $this->assertSame($this->tag, $this->tag->setRatio(7));
        $this->assertSame(7, $this->tag->getRatio());
    }

    public function testAddBasePopularityFlag(): void
    {
        $this->assertFalse($this->tag->getAddBasePopularity());
        $this->assertSame($this->tag, $this->tag->setAddBasePopularity());
        $this->assertTrue($this->tag->getAddBasePopularity());
    }

    public function testLoadByNameDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('loadByName')->with($this->tag, 'summer');
        $this->assertSame($this->tag, $this->tag->loadByName('summer'));
    }

    public function testAggregateDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('aggregate')->with($this->tag);
        $this->assertSame($this->tag, $this->tag->aggregate());
    }

    public function testAddSummarySetsStoreAndDelegates(): void
    {
        $this->resource->expects($this->once())->method('addSummary')->with($this->tag);
        $this->assertSame($this->tag, $this->tag->addSummary(2));
        $this->assertSame(2, $this->tag->getStoreId());
    }

    public function testUrls(): void
    {
        $this->tag->setData('tag_id', 9);
        $this->urlBuilder->expects($this->exactly(4))
            ->method('getUrl')
            ->willReturnCallback(static function (string $route, array $params): string {
                return $route . '?tagId=' . $params['tagId'];
            });
        $this->assertSame('tag/product/list?tagId=9', $this->tag->getTaggedProductsUrl());
        $this->assertSame('tag/customer/view?tagId=9', $this->tag->getViewTagUrl());
        $this->assertSame('tag/customer/edit?tagId=9', $this->tag->getEditTagUrl());
        $this->assertSame('tag/customer/remove?tagId=9', $this->tag->getRemoveTagUrl());
    }

    public function testIsAvailableInStore(): void
    {
        $this->tag->setVisibleInStoreIds([1, 3]);
        $this->assertTrue($this->tag->isAvailableInStore(3));
        $this->assertFalse($this->tag->isAvailableInStore(2));
    }

    public function testRelatedProductIds(): void
    {
        $relation = new class extends Relation {
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            public function __construct()
            {
            }

            public function getProductIds()
            {
                return [$this->getTagId(), $this->getStoreId()];
            }
        };
        $this->relationFactory->expects($this->once())->method('create')->willReturn($relation);

        $this->tag->setData(['tag_id' => 4, 'store_id' => 1]);
        $this->assertSame([4, 1], $this->tag->getRelatedProductIds());
        $this->assertNull($relation->getCustomerId());
        $this->assertTrue($relation->hasData('customer_id'));
    }
}
