<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTime as CoreDate;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as ResourceTag;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
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
     * @var CoreDate|MockObject
     */
    private $coreDate;

    /**
     * @var DateTime|MockObject
     */
    private $dateTime;

    /**
     * @var Tag
     */
    private $tag;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceTag::class);
        $this->relationFactory = $this->createMock(RelationFactory::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->coreDate = $this->createMock(CoreDate::class);
        $this->dateTime = $this->createMock(DateTime::class);

        $this->tag = $this->createTag();
    }

    /**
     * @param string[] $mockedMethods
     * @return Tag|MockObject
     */
    private function createTag(array $mockedMethods = [])
    {
        $args = [
            $this->createMock(Context::class),
            $this->createMock(Registry::class),
            $this->relationFactory,
            $this->urlBuilder,
            $this->createMock(StoreManagerInterface::class),
            $this->coreDate,
            $this->dateTime,
            $this->resource
        ];
        if (!$mockedMethods) {
            return new Tag(...$args);
        }
        return $this->getMockBuilder(Tag::class)
            ->setConstructorArgs($args)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    /**
     * Relation test double with in-memory data and a controllable product id list
     *
     * @param array $data
     * @param array $productIds
     * @return Relation
     */
    private function createRelation(array $data = [], array $productIds = []): Relation
    {
        $relation = new class extends Relation {
            /**
             * @var array
             */
            public $linkedProductIds = [];

            /**
             * @var int
             */
            public $saveCalls = 0;

            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            public function __construct()
            {
            }

            public function getProductIds()
            {
                return $this->linkedProductIds;
            }

            public function loadByTagCustomer($productId, $tagId, $customerId, $storeId = null)
            {
                return $this;
            }

            public function save()
            {
                $this->saveCalls++;
                return $this;
            }
        };
        $relation->setData($data);
        $relation->linkedProductIds = $productIds;
        return $relation;
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

    /**
     * @return array
     */
    public function saveRelationProvider(): array
    {
        return [
            'approved, not linked yet' => [
                Tag::STATUS_APPROVED, [], [], Tag::ADD_STATUS_SUCCESS, 1
            ],
            'approved, linked, no customer relation' => [
                Tag::STATUS_APPROVED, [10], [], Tag::ADD_STATUS_EXIST, 1
            ],
            'approved, linked, inactive customer relation' => [
                Tag::STATUS_APPROVED, [10], ['id' => 77, 'active' => 0], Tag::ADD_STATUS_EXIST, 1
            ],
            'approved, linked, active customer relation' => [
                Tag::STATUS_APPROVED, [10], ['id' => 77, 'active' => 1], Tag::ADD_STATUS_EXIST, 0
            ],
            'pending, no relation' => [
                Tag::STATUS_PENDING, [], [], Tag::ADD_STATUS_NEW, 1
            ],
            'pending, inactive relation' => [
                Tag::STATUS_PENDING, [], ['id' => 77, 'active' => 0], Tag::ADD_STATUS_NEW, 1
            ],
            'pending, active relation' => [
                Tag::STATUS_PENDING, [], ['id' => 77, 'active' => 1], Tag::ADD_STATUS_NEW, 0
            ],
            'disabled, customer already linked' => [
                Tag::STATUS_DISABLED, [10], ['id' => 77, 'active' => 1], Tag::ADD_STATUS_REJECTED, 0
            ],
            'disabled, no customer link' => [
                Tag::STATUS_DISABLED, [], [], Tag::ADD_STATUS_NEW, 1
            ],
        ];
    }

    /**
     * @param int $status
     * @param array $customerLinkedProductIds
     * @param array $existingData
     * @param string $expectedResult
     * @param int $expectedSaves
     * @dataProvider saveRelationProvider
     */
    public function testSaveRelation(
        int $status,
        array $customerLinkedProductIds,
        array $existingData,
        string $expectedResult,
        int $expectedSaves
    ): void {
        $this->coreDate->method('gmtTimestamp')->willReturn(1000);
        $this->dateTime->method('formatDate')->willReturn('2020-01-01 00:00:00');

        $newRelation = $this->createRelation([], $customerLinkedProductIds);
        $existing = $this->createRelation($existingData, $customerLinkedProductIds);
        $this->relationFactory->method('create')->willReturnOnConsecutiveCalls($newRelation, $existing, $existing);

        $tag = $this->createTag(['save']);
        $tag->setId(5);
        $tag->setStatus($status);
        if ($status === Tag::STATUS_DISABLED && !$customerLinkedProductIds) {
            $tag->expects($this->once())->method('save')->willReturnSelf();
        } else {
            $tag->expects($this->never())->method('save');
        }

        $this->assertSame($expectedResult, $tag->saveRelation(10, 3, 1));
        $this->assertSame($expectedSaves, $newRelation->saveCalls);
        $this->assertSame(5, $newRelation->getTagId());
        $this->assertSame(Relation::STATUS_ACTIVE, $newRelation->getActive());
        $this->assertSame('2020-01-01 00:00:00', $newRelation->getCreatedAt());
        if ($status === Tag::STATUS_DISABLED && !$customerLinkedProductIds) {
            $this->assertSame(Tag::STATUS_PENDING, $tag->getStatus());
        }
        if (($existingData['active'] ?? null) === 0) {
            $this->assertSame(77, $newRelation->getId());
        }
    }

    /**
     * @return Observer
     */
    private function createProductObserver(): Observer
    {
        $event = new Event(['product' => new DataObject(['id' => 42])]);
        return new Observer(['event' => $event]);
    }

    public function testProductEventAggregate(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('joinRel')->willReturnSelf();
        $collection->expects($this->once())->method('addProductFilter')->with(42)->willReturnSelf();
        $collection->expects($this->once())->method('addTagGroup')->willReturnSelf();
        $collection->expects($this->once())->method('load')->willReturnSelf();
        $collection->expects($this->once())->method('walk')->with('aggregate');

        $tag = $this->createTag(['getResourceCollection']);
        $tag->method('getResourceCollection')->willReturn($collection);

        $this->assertSame($tag, $tag->productEventAggregate($this->createProductObserver()));
    }

    public function testProductDeleteEventAction(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('joinRel')->willReturnSelf();
        $collection->method('addProductFilter')->with(42)->willReturnSelf();
        $collection->method('addTagGroup')->willReturnSelf();
        $collection->method('load')->willReturnSelf();
        $collection->method('getAllIds')->willReturn([1, 2]);
        $this->resource->expects($this->once())->method('decrementProducts')->with([1, 2]);

        $tag = $this->createTag(['getResourceCollection', '_getResource']);
        $tag->method('getResourceCollection')->willReturn($collection);
        $tag->method('_getResource')->willReturn($this->resource);

        $this->assertSame($tag, $tag->productDeleteEventAction($this->createProductObserver()));
    }

    public function testAfterSaveAddsAssignedProducts(): void
    {
        $relation = $this->createMock(Relation::class);
        $relation->expects($this->once())->method('addRelations')->with($this->isInstanceOf(Tag::class), [1, 2]);
        $this->relationFactory->expects($this->once())->method('create')->willReturn($relation);

        $this->tag->setData('tag_assigned_products', [1, 2]);
        $this->assertSame($this->tag, $this->tag->afterSave());
    }

    public function testAfterSaveWithoutAssignedProducts(): void
    {
        $this->relationFactory->expects($this->never())->method('create');
        $this->assertSame($this->tag, $this->tag->afterSave());
    }
}
