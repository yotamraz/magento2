<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel\Tag;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Tag\Model\ResourceModel\Tag\Relation;
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

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2024-01-02 03:04:05');

        $this->resource = new Relation($context, $dateTime);
    }

    /**
     * Record every where() condition of the select
     *
     * @return \ArrayObject
     */
    private function recordWhere(): \ArrayObject
    {
        $conditions = new \ArrayObject();
        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $select->method('where')->willReturnCallback(
            function (...$args) use ($conditions, $select) {
                $conditions->append($args);
                return $select;
            }
        );
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->select = $select;

        return $conditions;
    }

    /**
     * Rebuild the resource model around the current connection mock
     *
     * @return void
     */
    private function rebuildResource(): void
    {
        $resources = $this->createMock(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createMock(Context::class);
        $context->method('getResources')->willReturn($resources);
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2024-01-02 03:04:05');
        $this->resource = new Relation($context, $dateTime);
    }

    public function testLoadByTagCustomerWithoutTagOrCustomerDoesNotQuery(): void
    {
        $this->connection->expects($this->never())->method('fetchRow');
        $model = new DataObject(['tag_id' => 6]);

        $this->assertSame($this->resource, $this->resource->loadByTagCustomer($model));
        $this->assertSame(['tag_id' => 6], $model->getData());
    }

    public function testLoadByTagCustomerBindsAllFilters(): void
    {
        $conditions = $this->recordWhere();
        $this->rebuildResource();
        $row = ['tag_relation_id' => 9, 'active' => 1];
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with(
                $this->select,
                ['tag_id' => 6, 'customer_id' => 7, 'product_id' => 5, 'sore_id' => 2]
            )
            ->willReturn($row);
        $model = new DataObject(['tag_id' => 6, 'customer_id' => 7, 'product_id' => 5, 'store_id' => 2]);

        $this->resource->loadByTagCustomer($model);

        $this->assertSame(
            [
                ['tag_relation.tag_id = :tag_id'],
                ['customer_id = :customer_id'],
                ['tag_relation.product_id = :product_id'],
                ['tag_relation.store_id = :sore_id'],
            ],
            $conditions->getArrayCopy()
        );
        $this->assertSame($row, $model->getData());
    }

    public function testLoadByTagCustomerWithoutProductAndStoreOnlyBindsTagAndCustomer(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['tag_id' => 6, 'customer_id' => 7])
            ->willReturn(false);
        $model = new DataObject(['tag_id' => 6, 'customer_id' => 7]);

        $this->resource->loadByTagCustomer($model);
        $this->assertSame([], $model->getData());
    }

    public function testGetProductIdsFiltersByTagOnly(): void
    {
        $conditions = $this->recordWhere();
        $this->rebuildResource();
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with($this->select, ['tag_id' => 6])
            ->willReturn(['1', '2']);

        $this->assertSame(['1', '2'], $this->resource->getProductIds(new DataObject(['tag_id' => 6])));
        $this->assertSame([['tag_relation.tag_id=:tag_id']], $conditions->getArrayCopy());
    }

    public function testGetProductIdsWithCustomerStoreAndStatusFilter(): void
    {
        $conditions = $this->recordWhere();
        $this->rebuildResource();
        $this->select->expects($this->once())
            ->method('join')
            ->with('tag', 'tag.tag_id = tag_relation.tag_id');
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with(
                $this->select,
                ['tag_id' => 6, 'customer_id' => 7, 'store_id' => 2, 't_status' => 1]
            )
            ->willReturn([5]);
        $model = new DataObject(['tag_id' => 6, 'customer_id' => 7, 'store_id' => 2, 'status_filter' => 1]);

        $this->assertSame([5], $this->resource->getProductIds($model));
        $this->assertSame(
            [
                ['tag_relation.tag_id=:tag_id'],
                ['tag_relation.customer_id= :customer_id'],
                ['tag_relation.store_id = :store_id'],
                ['tag.status = :t_status'],
            ],
            $conditions->getArrayCopy()
        );
    }

    public function testGetRelatedTagIdsAcceptsSingleAndMultipleProducts(): void
    {
        $conditions = $this->recordWhere();
        $this->rebuildResource();
        $this->connection->expects($this->exactly(2))->method('fetchCol')->willReturn([3, 4]);

        $this->assertSame([3, 4], $this->resource->getRelatedTagIds(new DataObject(['product_id' => 5])));
        $this->assertSame([3, 4], $this->resource->getRelatedTagIds(new DataObject(['product_id' => [5, 6]])));
        $this->assertSame(
            [['product_id IN(?)', [5]], ['product_id IN(?)', [5, 6]]],
            $conditions->getArrayCopy()
        );
    }

    public function testDeactivateUpdatesRelationsByTagAndCustomer(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with('tag_relation', ['active' => 0], ['tag_id = ?' => 4, 'customer_id = ?' => 9]);

        $this->assertSame($this->resource, $this->resource->deactivate(4, 9));
    }

    public function testAddRelationsInsertsNewAndDeletesRemovedProducts(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with($this->select, ['tag_id' => 4, 'store_id' => 2])
            ->willReturn([2, 3]);
        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'tag_relation',
                $this->equalTo([
                    [
                        'tag_id' => 4,
                        'store_id' => 2,
                        'product_id' => 1,
                        'customer_id' => null,
                        'created_at' => '2024-01-02 03:04:05',
                    ],
                ])
            );
        $this->connection->expects($this->once())
            ->method('delete')
            ->with('tag_relation', ['product_id IN (?)' => [1 => 3], 'store_id = ?' => 2]);
        $model = new DataObject(['tag_id' => 4, 'store_id' => 2, 'added_product_ids' => [1, 2]]);

        $this->assertSame($this->resource, $this->resource->addRelations($model));
    }

    public function testAddRelationsWithUnchangedProductsDoesNotWrite(): void
    {
        $this->connection->method('fetchCol')->willReturn([1, 2]);
        $this->connection->expects($this->never())->method('insertMultiple');
        $this->connection->expects($this->never())->method('delete');
        $model = new DataObject(['tag_id' => 4, 'store_id' => 2, 'added_product_ids' => [1, 2]]);

        $this->resource->addRelations($model);
    }
}
