<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ActionValidator\RemoveAction;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TagTest extends TestCase
{
    /**
     * @var TagResource|MockObject
     */
    private $resource;

    /**
     * @var RelationResource|MockObject
     */
    private $relationResource;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var Registry|MockObject
     */
    private $registry;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilder;

    /**
     * @var DateTime|MockObject
     */
    private $dateTime;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Tag
     */
    private $model;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->resource = $this->createMock(TagResource::class);
        $this->resource->method('getIdFieldName')->willReturn('tag_id');

        $this->relationResource = $this->createMock(RelationResource::class);
        $this->relationResource->method('getIdFieldName')->willReturn('tag_relation_id');

        $actionValidator = $this->createMock(RemoveAction::class);
        $actionValidator->method('isAllowed')->willReturn(true);
        $this->context = new Context(
            $this->createMock(LoggerInterface::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(CacheInterface::class),
            $this->createMock(State::class),
            $actionValidator
        );

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->registry = $this->createMock(Registry::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('gmtDate')->willReturn('2024-01-02 03:04:05');

        $relationFactory = $this->createMock(RelationFactory::class);
        $relationFactory->method('create')->willReturnCallback(
            function () {
                return $this->objectManager->getObject(
                    Relation::class,
                    ['context' => $this->context, 'resource' => $this->relationResource]
                );
            }
        );

        $this->model = $this->objectManager->getObject(
            Tag::class,
            [
                'context' => $this->context,
                'registry' => $this->registry,
                'storeManager' => $this->storeManager,
                'urlBuilder' => $this->urlBuilder,
                'relationFactory' => $relationFactory,
                'dateTime' => $this->dateTime,
                'resource' => $this->resource,
            ]
        );
    }

    /**
     * Stub the relation resource: which product ids are linked and which relation row is found for a customer
     *
     * @param int[] $tagProductIds product ids linked to the tag by anybody
     * @param int[] $customerProductIds product ids linked to the tag by the customer
     * @param array $row relation row returned by loadByTagCustomer (empty: no relation)
     * @return void
     */
    private function stubRelationResource(array $tagProductIds, array $customerProductIds, array $row): void
    {
        $this->relationResource->method('getProductIds')->willReturnCallback(
            static function ($relation) use ($tagProductIds, $customerProductIds) {
                return $relation->getCustomerId() === null ? $tagProductIds : $customerProductIds;
            }
        );
        $this->relationResource->method('loadByTagCustomer')->willReturnCallback(
            function ($relation) use ($row) {
                $relation->setData($row);
                return $this->relationResource;
            }
        );
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(-1, Tag::STATUS_DISABLED);
        $this->assertSame(0, Tag::STATUS_PENDING);
        $this->assertSame(1, Tag::STATUS_APPROVED);
        $this->assertSame(-1, $this->model->getDisabledStatus());
        $this->assertSame(0, $this->model->getPendingStatus());
        $this->assertSame(1, $this->model->getApprovedStatus());
        $this->assertSame('success', Tag::ADD_STATUS_SUCCESS);
        $this->assertSame('new', Tag::ADD_STATUS_NEW);
        $this->assertSame('exist', Tag::ADD_STATUS_EXIST);
        $this->assertSame('rejected', Tag::ADD_STATUS_REJECTED);
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

    public function testAddSummarySetsStoreId(): void
    {
        $this->resource->expects($this->once())->method('addSummary')->with($this->model);
        $this->model->addSummary(3);
        $this->assertSame(3, $this->model->getStoreId());
    }

    public function testAddBasePopularityFlag(): void
    {
        $this->assertFalse($this->model->getAddBasePopularity());
        $this->model->setAddBasePopularity();
        $this->assertTrue($this->model->getAddBasePopularity());
    }

    public function testSimpleAccessors(): void
    {
        $this->model->setData(['tag_id' => 7, 'name' => 'x', 'popularity' => 2]);
        $this->model->setRatio(5);
        $this->assertSame(7, $this->model->getTagId());
        $this->assertSame('x', $this->model->getName());
        $this->assertSame(2, $this->model->getPopularity());
        $this->assertSame(5, $this->model->getRatio());
    }

    public function testIsAvailableInStore(): void
    {
        $this->model->setVisibleInStoreIds([1, 2]);
        $this->assertTrue($this->model->isAvailableInStore(2));
        $this->assertFalse($this->model->isAvailableInStore(3));
    }

    public function testIsAvailableInStoreFallsBackToCurrentStore(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->model->setVisibleInStoreIds([2, 3]);
        $this->assertTrue($this->model->isAvailableInStore());
    }

    public function testIsAvailableInStoreWithoutVisibleStoreIds(): void
    {
        $this->assertFalse($this->model->isAvailableInStore(1));
    }

    public function testPopularityAndRatioDefaultToNull(): void
    {
        $this->assertNull($this->model->getPopularity());
        $this->assertNull($this->model->getRatio());
    }

    public function testUrlGetters(): void
    {
        $this->model->setData('tag_id', 5);
        $expected = [
            'getTaggedProductsUrl' => 'tag/product/list',
            'getViewTagUrl' => 'tag/customer/view',
            'getEditTagUrl' => 'tag/customer/edit',
            'getRemoveTagUrl' => 'tag/customer/remove',
        ];
        $this->urlBuilder->method('getUrl')->willReturnCallback(
            static function ($route, $params) {
                return $route . '?' . json_encode($params);
            }
        );

        foreach ($expected as $method => $route) {
            $this->assertSame($route . '?{"tagId":5}', $this->model->$method());
        }
    }

    public function testBeforeDeleteFromNonAdminAreaIsRejected(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->registry->method('registry')->with('isSecureArea')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cannot complete this operation from non-admin area.');
        $this->model->beforeDelete();
    }

    public function testBeforeDeleteAllowedInSecureArea(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->registry->method('registry')->with('isSecureArea')->willReturn(true);

        $this->assertSame($this->model, $this->model->beforeDelete());
    }

    public function testBeforeDeleteAllowedInAdminStore(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(Store::DEFAULT_STORE_ID);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->registry->method('registry')->with('isSecureArea')->willReturn(null);

        $this->assertSame($this->model, $this->model->beforeDelete());
    }

    public function testGetRelatedProductIdsFiltersByTagStoreAndStatus(): void
    {
        $this->model->setData(['tag_id' => 5, 'store_id' => 2, 'status_filter' => Tag::STATUS_APPROVED]);
        $this->relationResource->expects($this->once())
            ->method('getProductIds')
            ->with(
                $this->callback(
                    static function (Relation $relation): bool {
                        return $relation->getTagId() === 5
                            && $relation->getStoreId() === 2
                            && $relation->getStatusFilter() === Tag::STATUS_APPROVED
                            && $relation->hasData('customer_id')
                            && $relation->getCustomerId() === null;
                    }
                )
            )
            ->willReturn([10, 11]);

        $this->assertSame([10, 11], $this->model->getRelatedProductIds());
    }

    public function testAfterSaveAddsRelationsWhenProductsAssigned(): void
    {
        $this->model->setData(['tag_id' => 5, 'store' => 2, 'tag_assigned_products' => [10, 11]]);
        $this->relationResource->expects($this->once())
            ->method('addRelations')
            ->with(
                $this->callback(
                    static function (Relation $relation): bool {
                        return $relation->getAddedProductIds() === [10, 11]
                            && $relation->getTagId() === 5
                            && $relation->getStoreId() === 2
                            && $relation->getCustomerId() === null;
                    }
                )
            );

        $this->assertSame($this->model, $this->model->afterSave());
    }

    public function testAfterSaveWithoutAssignedProductsSkipsRelations(): void
    {
        $this->relationResource->expects($this->never())->method('addRelations');
        $this->assertSame($this->model, $this->model->afterSave());
    }

    /**
     * @return array [status, tag product ids, customer product ids, relation row, result, save count, saved id]
     */
    public static function saveRelationProvider(): array
    {
        $inactive = ['tag_relation_id' => 77, 'active' => 0];
        $active = ['tag_relation_id' => 77, 'active' => 1];
        return [
            'approved, product not linked' => [Tag::STATUS_APPROVED, [], [], [], Tag::ADD_STATUS_SUCCESS, 1, null],
            'approved, linked, no customer relation' => [
                Tag::STATUS_APPROVED, [10], [], [], Tag::ADD_STATUS_EXIST, 1, null
            ],
            'approved, linked, active customer relation' => [
                Tag::STATUS_APPROVED, [10], [10], $active, Tag::ADD_STATUS_EXIST, 0, null
            ],
            'approved, linked, inactive customer relation' => [
                Tag::STATUS_APPROVED, [10], [10], $inactive, Tag::ADD_STATUS_EXIST, 1, 77
            ],
            'pending, no relation' => [Tag::STATUS_PENDING, [], [], [], Tag::ADD_STATUS_NEW, 1, null],
            'pending, active relation' => [Tag::STATUS_PENDING, [], [10], $active, Tag::ADD_STATUS_NEW, 0, null],
            'pending, inactive relation' => [Tag::STATUS_PENDING, [], [10], $inactive, Tag::ADD_STATUS_NEW, 1, 77],
        ];
    }

    #[DataProvider('saveRelationProvider')]
    public function testSaveRelation(
        int $status,
        array $tagProductIds,
        array $customerProductIds,
        array $row,
        string $expectedResult,
        int $expectedSaves,
        ?int $expectedSavedId
    ): void {
        $this->model->setData(['tag_id' => 5, 'status' => $status]);
        $this->stubRelationResource($tagProductIds, $customerProductIds, $row);

        $saved = [];
        $this->relationResource->expects($this->exactly($expectedSaves))
            ->method('save')
            ->willReturnCallback(
                function ($relation) use (&$saved) {
                    $saved[] = $relation;
                    return $this->relationResource;
                }
            );

        $this->resource->expects($this->never())->method('save');

        $this->assertSame($expectedResult, $this->model->saveRelation(10, 6, 1));

        if ($expectedSaves > 0) {
            $relation = $saved[0];
            $this->assertEquals($expectedSavedId, $relation->getId());
            $this->assertSame(5, $relation->getTagId());
            $this->assertSame(10, $relation->getProductId());
            $this->assertSame(6, $relation->getCustomerId());
            $this->assertSame(1, $relation->getStoreId());
            $this->assertSame(Relation::STATUS_ACTIVE, $relation->getActive());
            $this->assertSame('2024-01-02 03:04:05', $relation->getCreatedAt());
        }
    }

    public function testSaveRelationDisabledTagWithExistingCustomerRelationIsRejected(): void
    {
        $this->model->setData(['tag_id' => 5, 'status' => Tag::STATUS_DISABLED]);
        $this->stubRelationResource([], [10], ['tag_relation_id' => 77, 'active' => 1, 'customer_id' => 6]);
        $this->resource->expects($this->never())->method('save');
        $this->relationResource->expects($this->never())->method('save');

        $this->assertSame(Tag::ADD_STATUS_REJECTED, $this->model->saveRelation(10, 6, 1));
        $this->assertSame(Tag::STATUS_DISABLED, $this->model->getStatus());
    }

    public function testSaveRelationDisabledTagPromotesToPending(): void
    {
        $this->model->setData(['tag_id' => 5, 'status' => Tag::STATUS_DISABLED]);
        $this->stubRelationResource([], [], []);
        $this->resource->expects($this->once())->method('save')->with($this->model);
        $this->relationResource->expects($this->once())->method('save');

        $this->assertSame(Tag::ADD_STATUS_NEW, $this->model->saveRelation(10, 6, 1));
        $this->assertSame(Tag::STATUS_PENDING, $this->model->getStatus());
    }
}
