<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel\Tag;

use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Tag\Model\ResourceModel\Tag\Relation;
use Magento\Tag\Model\Tag\Relation as RelationModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
     * @var DateTime|MockObject
     */
    private $dateTime;

    /**
     * @var Relation
     */
    private $resource;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        foreach (['from', 'join', 'where', 'order'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);

        $resources = $this->createMock(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createMock(Context::class);
        $context->method('getResources')->willReturn($resources);

        $this->dateTime = $this->createMock(DateTime::class);
        $this->resource = new Relation($context, $this->dateTime);
    }

    public function testGetProductIdsBindsTagId(): void
    {
        $model = new DataObject(['tag_id' => 5]);
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with($this->select, ['tag_id' => 5])
            ->willReturn(['1', '2']);

        $this->assertSame(['1', '2'], $this->resource->getProductIds($model));
    }

    public function testGetProductIdsFiltersByCustomerAndStore(): void
    {
        $model = new DataObject(['tag_id' => 5, 'customer_id' => 7, 'store_id' => 2]);
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with($this->select, ['tag_id' => 5, 'customer_id' => 7, 'store_id' => 2])
            ->willReturn([]);

        $this->assertSame([], $this->resource->getProductIds($model));
    }

    public function testGetRelatedTagIdsWrapsScalarProductId(): void
    {
        $model = new DataObject(['product_id' => 9]);
        $this->select->expects($this->once())
            ->method('where')
            ->with('product_id IN(?)', [9]);
        $this->connection->method('fetchCol')->willReturn(['3']);

        $this->assertSame(['3'], $this->resource->getRelatedTagIds($model));
    }

    public function testAddRelationsInsertsAndDeletesDifference(): void
    {
        $model = new DataObject(
            ['tag_id' => 1, 'store_id' => 2, 'customer_id' => 3, 'added_product_ids' => [10, 11]]
        );
        $this->connection->method('fetchCol')->willReturn([11, 12]);
        $this->connection->method('formatDate')->willReturn('2026-01-01 00:00:00');
        $this->dateTime->method('gmtTimestamp')->willReturn(1);
        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'tag_relation',
                [[
                    'tag_id' => 1,
                    'store_id' => 2,
                    'product_id' => 10,
                    'customer_id' => 3,
                    'created_at' => '2026-01-01 00:00:00',
                ]]
            );
        $this->connection->expects($this->once())
            ->method('delete')
            ->with('tag_relation', ['product_id IN (?)' => [1 => 12], 'store_id = ?' => 2]);

        $this->assertSame($this->resource, $this->resource->addRelations($model));
    }

    public function testDeactivateUpdatesRelations(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'tag_relation',
                ['active' => RelationModel::STATUS_NOT_ACTIVE],
                ['tag_id = ?' => 3, 'customer_id = ?' => 4]
            );

        $this->assertSame($this->resource, $this->resource->deactivate(3, 4));
    }
}
