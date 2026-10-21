<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TagTest extends TestCase
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilder;

    /**
     * @var RelationFactory|MockObject
     */
    private $relationFactory;

    /**
     * @var DateTime|MockObject
     */
    private $dateTime;

    /**
     * @var TagResource|MockObject
     */
    private $resource;

    /**
     * @var RelationResource|MockObject
     */
    private $relationResource;

    /**
     * @var Tag
     */
    private $tag;

    protected function setUp(): void
    {
        $this->registry = new Registry();
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->relationFactory = $this->createMock(RelationFactory::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->resource = $this->createMock(TagResource::class);
        $this->resource->method('getIdFieldName')->willReturn('tag_id');
        $this->relationResource = $this->createMock(RelationResource::class);
        $this->relationResource->method('getIdFieldName')->willReturn('tag_relation_id');
        $this->relationResource->method('getConnection')->willReturn($this->createMock(AdapterInterface::class));
        $this->relationFactory->method('create')->willReturnCallback(fn () => $this->createRelation());

        $this->tag = new Tag(
            $this->createMock(Context::class),
            $this->registry,
            $this->storeManager,
            $this->urlBuilder,
            $this->relationFactory,
            $this->dateTime,
            $this->resource
        );
    }

    private function createRelation(): Relation
    {
        return new Relation($this->createMock(Context::class), $this->registry, $this->relationResource);
    }

    private function mockCurrentStore(int $storeId): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    public function testConstants(): void
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

    public function testEventPrefix(): void
    {
        $property = new \ReflectionProperty(Tag::class, '_eventPrefix');
        $this->assertSame('tag', $property->getValue($this->tag));
    }

    public function testRatio(): void
    {
        $this->assertNull($this->tag->getRatio());
        $this->assertSame($this->tag, $this->tag->setRatio(75));
        $this->assertSame(75, $this->tag->getRatio());
        $this->assertSame(75, $this->tag->getData('ratio'));
    }

    public function testSimpleGetters(): void
    {
        $this->tag->setData(['tag_id' => 7, 'name' => 'summer', 'popularity' => 3]);
        $this->assertSame(7, $this->tag->getTagId());
        $this->assertSame('summer', $this->tag->getName());
        $this->assertSame(3, $this->tag->getPopularity());
    }

    public function testAddBasePopularityFlag(): void
    {
        $this->assertFalse($this->tag->getAddBasePopularity());
        $this->assertSame($this->tag, $this->tag->setAddBasePopularity());
        $this->assertTrue($this->tag->getAddBasePopularity());
        $this->tag->setAddBasePopularity(false);
        $this->assertFalse($this->tag->getAddBasePopularity());
    }

    public function testLoadByNameDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('loadByName')->with($this->tag, 'foo');
        $this->assertSame($this->tag, $this->tag->loadByName('foo'));
    }

    public function testAggregateDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('aggregate')->with($this->tag);
        $this->assertSame($this->tag, $this->tag->aggregate());
    }

    public function testUrlGetters(): void
    {
        $this->tag->setData('tag_id', 5);
        $this->urlBuilder->expects($this->exactly(4))
            ->method('getUrl')
            ->willReturnCallback(static fn ($route, $params) => $route . '?' . $params['tagId']);

        $this->assertSame('tag/product/list?5', $this->tag->getTaggedProductsUrl());
        $this->assertSame('tag/customer/view?5', $this->tag->getViewTagUrl());
        $this->assertSame('tag/customer/edit?5', $this->tag->getEditTagUrl());
        $this->assertSame('tag/customer/remove?5', $this->tag->getRemoveTagUrl());
    }

    public function testIsAvailableInStore(): void
    {
        $this->tag->setVisibleInStoreIds([1, 3]);
        $this->assertTrue($this->tag->isAvailableInStore(1));
        $this->assertFalse($this->tag->isAvailableInStore(2));
    }

    public function testIsAvailableInStoreUsesCurrentStore(): void
    {
        $this->mockCurrentStore(3);
        $this->tag->setVisibleInStoreIds([1, 3]);
        $this->assertTrue($this->tag->isAvailableInStore());
    }

    public function testBeforeDeleteRejectedOutsideAdmin(): void
    {
        $this->mockCurrentStore(1);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cannot complete this operation from non-admin area.');
        $this->tag->beforeDelete();
    }

    public function testProtectFromNonAdminPassesInAdminStore(): void
    {
        $this->mockCurrentStore(0);
        $method = new \ReflectionMethod(Tag::class, '_protectFromNonAdmin');
        $method->invoke($this->tag);
        $this->addToAssertionCount(1);
    }

    public function testProtectFromNonAdminPassesInSecureArea(): void
    {
        $this->mockCurrentStore(1);
        $this->registry->register('isSecureArea', true);
        $method = new \ReflectionMethod(Tag::class, '_protectFromNonAdmin');
        $method->invoke($this->tag);
        $this->addToAssertionCount(1);
    }

    public function testSaveRelationPendingTagCreatesNewRelation(): void
    {
        $this->tag->setData(['tag_id' => 5, 'status' => Tag::STATUS_PENDING]);
        $this->relationResource->expects($this->once())->method('save');

        $this->assertSame(Tag::ADD_STATUS_NEW, $this->tag->saveRelation(10, 20, 1));
    }

    public function testSaveRelationApprovedTagWithoutLinkIsSuccess(): void
    {
        $this->tag->setData(['tag_id' => 5, 'status' => Tag::STATUS_APPROVED]);
        $this->relationResource->method('getProductIds')->willReturn([]);
        $this->relationResource->expects($this->once())->method('save');

        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $this->tag->saveRelation(10, 20, 1));
    }

    public function testSaveRelationApprovedTagWithExistingLinkIsExist(): void
    {
        $this->tag->setData(['tag_id' => 5, 'status' => Tag::STATUS_APPROVED]);
        $this->relationResource->method('getProductIds')->willReturn([10]);
        $this->relationResource->expects($this->once())->method('save');

        $this->assertSame(Tag::ADD_STATUS_EXIST, $this->tag->saveRelation(10, 20, 1));
    }

    public function testSaveRelationDisabledTagWithExistingLinkIsRejected(): void
    {
        $this->tag->setData(['tag_id' => 5, 'status' => Tag::STATUS_DISABLED]);
        $this->relationResource->method('getProductIds')->willReturn([10]);
        $this->relationResource->expects($this->never())->method('save');

        $this->assertSame(Tag::ADD_STATUS_REJECTED, $this->tag->saveRelation(10, 20, 1));
    }

    public function testSaveRelationDisabledTagWithoutLinkBecomesPending(): void
    {
        $this->tag->setData(['tag_id' => 5, 'status' => Tag::STATUS_DISABLED]);
        $this->relationResource->method('getProductIds')->willReturn([]);
        $this->relationResource->expects($this->once())->method('save');
        $this->resource->expects($this->once())->method('save')->with($this->tag);

        $this->assertSame(Tag::ADD_STATUS_NEW, $this->tag->saveRelation(10, 20, 1));
        $this->assertSame(Tag::STATUS_PENDING, $this->tag->getStatus());
    }

    public function testGetRelatedProductIds(): void
    {
        $this->tag->setData(['tag_id' => 5, 'store_id' => 2]);
        $this->relationResource->expects($this->once())
            ->method('getProductIds')
            ->with($this->callback(
                static fn (Relation $relation) => $relation->getTagId() === 5
                    && $relation->getStoreId() === 2
                    && $relation->getCustomerId() === null
            ))
            ->willReturn([11, 12]);

        $this->assertSame([11, 12], $this->tag->getRelatedProductIds());
    }
}
