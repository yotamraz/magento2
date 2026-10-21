<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
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
     * @var ResourceConnection|MockObject
     */
    private $resources;

    /**
     * @var TagResource
     */
    private $resource;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        foreach (['from', 'join', 'joinLeft', 'where', 'group', 'limit'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);

        $this->resources = $this->createMock(ResourceConnection::class);
        $this->resources->method('getConnection')->willReturn($this->connection);
        $this->resources->method('getTableName')->willReturnArgument(0);

        $context = $this->createMock(Context::class);
        $context->method('getResources')->willReturn($this->resources);

        $this->resource = new TagResource(
            $context,
            new StringUtils(),
            $this->createMock(StoreManagerInterface::class)
        );
    }

    public function testLoadByNameWithEmptyNameReturnsFalseWithoutQuery(): void
    {
        $this->connection->expects($this->never())->method('fetchRow');
        $model = new DataObject(['untouched' => 1]);

        $this->assertFalse($this->resource->loadByName($model, ''));
        $this->assertSame(['untouched' => 1], $model->getData());
    }

    public function testLoadByNameBindsNameAndSetsData(): void
    {
        $row = ['tag_id' => 4, 'name' => 'foo', 'status' => 1];
        $this->select->expects($this->once())->method('from')->with('tag');
        $this->select->expects($this->once())->method('where')->with('name = :name');
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['name' => 'foo'])
            ->willReturn($row);
        $model = new DataObject();

        $this->assertNull($this->resource->loadByName($model, 'foo'));
        $this->assertSame($row, $model->getData());
    }

    public function testLoadByNameTruncatesLongNameTo255Characters(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['name' => str_repeat('a', 255)])
            ->willReturn(false);

        $this->resource->loadByName(new DataObject(), str_repeat('a', 300));
    }

    public function testLoadByNameWithNoRowResetsModelData(): void
    {
        $this->connection->method('fetchRow')->willReturn(false);
        $model = new DataObject(['stale' => 1]);

        $this->resource->loadByName($model, 'missing');
        $this->assertSame([], $model->getData());
    }

    public function testAddSummaryAddsRowToObject(): void
    {
        $this->select->expects($this->once())
            ->method('from')
            ->with(['relation' => 'tag_relation'], []);
        $this->select->expects($this->once())
            ->method('joinLeft')
            ->with(
                ['summary' => 'tag_summary'],
                'relation.tag_id = summary.tag_id AND relation.store_id = summary.store_id',
                ['customers', 'products', 'popularity']
            );
        $this->select->expects($this->once())->method('limit')->with(1);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['tag_id' => 5, 'store_id' => 2])
            ->willReturn(['customers' => 3, 'products' => 4, 'popularity' => 6]);
        $object = new DataObject(['id' => 5, 'store_id' => 2]);

        $this->assertSame($object, $this->resource->addSummary($object));
        $this->assertSame(3, $object->getCustomers());
        $this->assertSame(4, $object->getProducts());
        $this->assertSame(6, $object->getPopularity());
    }

    public function testAddSummaryWithoutRowLeavesObjectUntouched(): void
    {
        $this->connection->method('fetchRow')->willReturn(false);
        $object = new DataObject(['id' => 5, 'store_id' => 2]);

        $this->resource->addSummary($object);
        $this->assertSame(['id' => 5, 'store_id' => 2], $object->getData());
    }

    public function testAggregateBuildsSummaryRowsPerStoreAndGlobal(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAssoc')
            ->with($this->select, ['tag_id' => 3])
            ->willReturn([1 => ['store_id' => 1, 'base_popularity' => 7]]);
        $this->connection->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['customers' => 1, 'products' => 1, 'store_id' => 1, 'uses' => 2]],
                [['historical_uses' => 4, 'store_id' => 1]]
            );
        $this->connection->method('fetchRow')
            ->willReturn(['customers' => 1, 'products' => 1, 'store_id' => 0, 'uses' => 2]);
        $this->connection->method('fetchOne')->willReturn('4');

        $this->connection->expects($this->once())
            ->method('delete')
            ->with('tag_summary', ['tag_id = ?' => 3]);
        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'tag_summary',
                $this->equalTo([
                    1 => [
                        'store_id' => 1,
                        'base_popularity' => 7,
                        'customers' => 1,
                        'products' => 1,
                        'uses' => 2,
                        'historical_uses' => 4,
                        'popularity' => 4,
                        'tag_id' => 3,
                    ],
                    0 => [
                        'store_id' => 0,
                        'customers' => 1,
                        'products' => 1,
                        'uses' => 2,
                        'historical_uses' => 4,
                        'popularity' => 4,
                        'base_popularity' => 0,
                        'tag_id' => 3,
                    ],
                ])
            );

        $object = new DataObject(['id' => 3, 'store' => 0]);
        $this->assertSame($object, $this->resource->aggregate($object));
    }

    public function testAggregateAppliesSpecifiedBasePopularityForStore(): void
    {
        $this->connection->method('fetchAssoc')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([]);
        $this->connection->method('fetchRow')->willReturn(false);

        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'tag_summary',
                $this->equalTo([
                    2 => [
                        'store_id' => 2,
                        'base_popularity' => 9,
                        'tag_id' => 3,
                        'popularity' => 0,
                        'historical_uses' => 0,
                        'uses' => 0,
                        'products' => 0,
                        'customers' => 0,
                    ],
                ])
            );

        $this->resource->aggregate(new DataObject(['id' => 3, 'store' => 2, 'base_popularity' => 9]));
    }

    public function testAggregateWithNothingToStoreOnlyDeletesOldRows(): void
    {
        $this->connection->method('fetchAssoc')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([]);
        $this->connection->method('fetchRow')->willReturn(false);

        $this->connection->expects($this->once())->method('delete')->with('tag_summary', ['tag_id = ?' => 3]);
        $this->connection->expects($this->never())->method('insertMultiple');

        $this->resource->aggregate(new DataObject(['id' => 3, 'store' => 0]));
    }

    public function testDecrementProductsWithNoTagsDoesNothing(): void
    {
        $this->resources->expects($this->never())->method('getConnection');
        $this->assertSame(0, $this->resource->decrementProducts([]));
    }

    public function testDecrementProductsUpdatesSummary(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'tag_summary',
                $this->callback(
                    static function (array $bind): bool {
                        return isset($bind['products'])
                            && $bind['products'] instanceof Expression
                            && (string)$bind['products'] === 'products - 1';
                    }
                ),
                ['tag_id IN (?)' => [1, 2]]
            )
            ->willReturn(2);

        $this->assertSame(2, $this->resource->decrementProducts([1, 2]));
    }
}
