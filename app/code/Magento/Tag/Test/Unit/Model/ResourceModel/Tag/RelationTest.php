<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel\Tag;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTime as CoreDate;
use Magento\Tag\Model\ResourceModel\Tag\Relation as ResourceRelation;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tag relation resource model
 */
class RelationTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var CoreDate|MockObject
     */
    private $coreDate;

    /**
     * @var DateTime|MockObject
     */
    private $dateTime;

    /**
     * @var ResourceRelation
     */
    private $resource;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('join')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->connection->method('select')->willReturn($this->select);

        $resources = $this->createMock(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createMock(Context::class);
        $context->method('getResources')->willReturn($resources);

        $this->coreDate = $this->createMock(CoreDate::class);
        $this->dateTime = $this->createMock(DateTime::class);

        $this->resource = new ResourceRelation($context, $this->coreDate, $this->dateTime);
    }

    private function createModel(array $data): Relation
    {
        $model = new class extends Relation {
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            public function __construct()
            {
            }
        };
        $model->setData($data);
        return $model;
    }

    public function testLoadByTagCustomerSkipsQueryWithoutTagOrCustomer(): void
    {
        $model = $this->createModel(['tag_id' => 1]);
        $this->connection->expects($this->never())->method('fetchRow');

        $this->assertSame($this->resource, $this->resource->loadByTagCustomer($model));
        $this->assertSame(['tag_id' => 1], $model->getData());
    }

    public function testLoadByTagCustomerBindsOnlyProvidedParts(): void
    {
        $model = $this->createModel(['tag_id' => 1, 'customer_id' => 2]);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['tag_id' => 1, 'customer_id' => 2])
            ->willReturn(['tag_relation_id' => 9, 'tag_id' => 1]);

        $this->resource->loadByTagCustomer($model);
        $this->assertSame(['tag_relation_id' => 9, 'tag_id' => 1], $model->getData());
    }

    public function testLoadByTagCustomerBindsProductAndStore(): void
    {
        $model = $this->createModel(['tag_id' => 1, 'customer_id' => 2, 'product_id' => 3, 'store_id' => 4]);
        // The store bind key spelling ("sore_id") is kept from the legacy implementation.
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with(
                $this->select,
                ['tag_id' => 1, 'customer_id' => 2, 'product_id' => 3, 'sore_id' => 4]
            )
            ->willReturn(false);

        $this->resource->loadByTagCustomer($model);
        $this->assertSame([], $model->getData());
    }

    public function testGetProductIdsMinimalBind(): void
    {
        $model = $this->createModel(['tag_id' => 1]);
        $this->select->expects($this->never())->method('join');
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with($this->select, ['tag_id' => 1])
            ->willReturn(['5', '6']);

        $this->assertSame(['5', '6'], $this->resource->getProductIds($model));
    }

    public function testGetProductIdsWithAllFilters(): void
    {
        $model = $this->createModel(['tag_id' => 1, 'customer_id' => 2, 'store_id' => 3, 'status_filter' => 1]);
        $this->select->expects($this->once())->method('join');
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with(
                $this->select,
                ['tag_id' => 1, 'customer_id' => 2, 'store_id' => 3, 't_status' => 1]
            )
            ->willReturn([]);

        $this->assertSame([], $this->resource->getProductIds($model));
    }

    /**
     * @return array
     */
    public function relatedTagIdsProvider(): array
    {
        return [
            'scalar product id' => [7, [7]],
            'array of product ids' => [[7, 8], [7, 8]],
        ];
    }

    /**
     * @param int|int[] $productId
     * @param int[] $expectedIds
     * @dataProvider relatedTagIdsProvider
     */
    public function testGetRelatedTagIds($productId, array $expectedIds): void
    {
        $model = $this->createModel(['product_id' => $productId]);
        $this->select->expects($this->once())
            ->method('where')
            ->with('product_id IN(?)', $expectedIds)
            ->willReturnSelf();
        $this->connection->expects($this->once())->method('fetchCol')->with($this->select)->willReturn(['1', '2']);

        $this->assertSame(['1', '2'], $this->resource->getRelatedTagIds($model));
    }

    public function testDeactivate(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'tag_relation',
                ['active' => Relation::STATUS_NOT_ACTIVE],
                ['tag_id = ?' => 1, 'customer_id = ?' => 2]
            );

        $this->assertSame($this->resource, $this->resource->deactivate(1, 2));
    }

    public function testAddRelationsInsertsNewAndDeletesRemoved(): void
    {
        $model = $this->createModel([
            'tag_id' => 1,
            'store_id' => 2,
            'customer_id' => 3,
            'added_product_ids' => [20, 30]
        ]);
        $this->coreDate->method('gmtTimestamp')->willReturn(1000);
        $this->dateTime->method('formatDate')->with(1000)->willReturn('2020-01-01 00:00:00');
        $this->connection->method('fetchCol')->willReturn([10, 20]);

        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'tag_relation',
                [[
                    'tag_id' => 1,
                    'store_id' => 2,
                    'product_id' => 30,
                    'customer_id' => 3,
                    'created_at' => '2020-01-01 00:00:00'
                ]]
            );
        $this->connection->expects($this->once())
            ->method('delete')
            ->with('tag_relation', ['product_id IN (?)' => [10], 'store_id = ?' => 2]);

        $this->assertSame($this->resource, $this->resource->addRelations($model));
    }

    public function testAddRelationsWithNoChangesDoesNothing(): void
    {
        $model = $this->createModel(['tag_id' => 1, 'store_id' => 2, 'added_product_ids' => [10]]);
        $this->connection->method('fetchCol')->willReturn([10]);
        $this->connection->expects($this->never())->method('insertMultiple');
        $this->connection->expects($this->never())->method('delete');

        $this->resource->addRelations($model);
    }
}
