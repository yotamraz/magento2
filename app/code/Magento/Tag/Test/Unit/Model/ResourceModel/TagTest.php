<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag as ResourceTag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tag resource model
 */
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
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var ResourceTag
     */
    private $resource;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        foreach (['from', 'join', 'joinLeft', 'where', 'limit', 'group', 'columns'] as $method) {
            $this->select->method($method)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($this->select);

        $resources = $this->createMock(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createMock(Context::class);
        $context->method('getResources')->willReturn($resources);

        $string = $this->createMock(StringUtils::class);
        $string->method('strlen')->willReturnCallback('strlen');
        $string->method('substr')->willReturnCallback('substr');
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->resource = new ResourceTag($context, $string, $this->storeManager);
    }

    public function testLoadByNameWithEmptyNameReturnsFalse(): void
    {
        $this->connection->expects($this->never())->method('fetchRow');
        $this->assertFalse($this->resource->loadByName(new DataObject(), ''));
    }

    public function testLoadByNameFound(): void
    {
        $model = new DataObject();
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['name' => 'summer'])
            ->willReturn(['tag_id' => 3, 'name' => 'summer']);

        $this->assertNull($this->resource->loadByName($model, 'summer'));
        $this->assertSame(['tag_id' => 3, 'name' => 'summer'], $model->getData());
    }

    public function testLoadByNameNotFoundClearsData(): void
    {
        $model = new DataObject(['stale' => 1]);
        $this->connection->method('fetchRow')->willReturn(false);

        $this->resource->loadByName($model, 'missing');
        $this->assertSame([], $model->getData());
    }

    public function testLoadByNameTruncatesLongNames(): void
    {
        $longName = str_repeat('a', 300);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['name' => str_repeat('a', 255)])
            ->willReturn([]);

        $this->resource->loadByName(new DataObject(), $longName);
    }

    public function testDecrementProductsWithEmptyListIsNoop(): void
    {
        $this->connection->expects($this->never())->method('update');
        $this->assertSame(0, $this->resource->decrementProducts([]));
    }

    public function testDecrementProductsUpdatesSummary(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with('tag_summary', $this->anything(), ['tag_id IN (?)' => [1, 2]])
            ->willReturn(2);

        $this->assertSame(2, $this->resource->decrementProducts([1, 2]));
    }

    public function testAddSummaryAddsRowData(): void
    {
        $model = new DataObject(['id' => 4, 'store_id' => 2]);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['tag_id' => 4, 'store_id' => 2])
            ->willReturn(['customers' => 1, 'products' => 2, 'popularity' => 3]);

        $this->assertSame($model, $this->resource->addSummary($model));
        $this->assertSame(3, $model->getData('popularity'));
        $this->assertSame(2, $model->getData('products'));
    }

    public function testAddSummaryWithoutRowLeavesModelUntouched(): void
    {
        $model = new DataObject(['id' => 4, 'store_id' => 2]);
        $this->connection->method('fetchRow')->willReturn(false);

        $this->resource->addSummary($model);
        $this->assertNull($model->getData('popularity'));
    }

    public function testAggregateReplacesSummaryRows(): void
    {
        $model = new DataObject(['id' => 5, 'store' => 0]);
        // existing base popularity, per-store aggregation, global aggregation
        $this->connection->method('fetchAssoc')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([
            ['store_id' => 1, 'historical_uses' => 4, 'uses' => 2, 'products' => 1, 'customers' => 1]
        ]);
        $this->connection->method('fetchRow')->willReturn([
            'store_id' => 0, 'historical_uses' => 9, 'uses' => 5, 'products' => 3, 'customers' => 2
        ]);

        $this->connection->expects($this->once())
            ->method('delete')
            ->with('tag_summary', ['tag_id = ?' => 5]);
        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'tag_summary',
                $this->callback(function (array $rows): bool {
                    foreach ($rows as $row) {
                        if ($row['tag_id'] !== 5 || $row['popularity'] !== $row['historical_uses']
                            || !array_key_exists('base_popularity', $row)
                        ) {
                            return false;
                        }
                    }
                    return count($rows) >= 1;
                })
            );

        $this->assertSame($model, $this->resource->aggregate($model));
    }

    public function testAfterSaveSkipsBasePopularityWithoutStore(): void
    {
        $this->connection->expects($this->never())->method('insertOnDuplicate');
        $this->invokeProtected('_afterSave', [$this->createSavedTag(['id' => 1])]);
    }

    public function testAfterSaveSkipsBasePopularityOutsideDefaultStore(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->connection->expects($this->never())->method('insertOnDuplicate');
        $this->invokeProtected('_afterSave', [$this->createSavedTag(['id' => 1, 'store' => 2])]);
    }

    public function testAfterSaveStoresBasePopularityInDefaultStore(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(0);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->connection->expects($this->once())
            ->method('insertOnDuplicate')
            ->with('tag_properties', ['tag_id' => 1, 'store_id' => 3, 'base_popularity' => 7]);

        $this->invokeProtected(
            '_afterSave',
            [$this->createSavedTag(['id' => 1, 'store' => 3, 'base_popularity' => 7])]
        );
    }

    public function testAfterLoadSetsVisibleStoreIds(): void
    {
        $tag = $this->createSavedTag(['id' => 8]);
        $this->connection->expects($this->once())
            ->method('fetchCol')
            ->with($this->select, ['tag_id' => 8])
            ->willReturn(['1', '2']);

        $this->assertSame($this->resource, $this->invokeProtected('_afterLoad', [$tag]));
        $this->assertSame(['1', '2'], $tag->getVisibleInStoreIds());
    }

    /**
     * @param array $data
     * @return \Magento\Tag\Model\Tag|MockObject
     */
    private function createSavedTag(array $data)
    {
        $tag = $this->getMockBuilder(\Magento\Tag\Model\Tag::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isObjectNew', 'getId', 'getTagId'])
            ->addMethods(['getStore', 'getBasePopularity', 'setVisibleInStoreIds', 'getVisibleInStoreIds'])
            ->getMock();
        $tag->method('isObjectNew')->willReturn(false);
        $tag->method('getId')->willReturn($data['id']);
        $tag->method('getTagId')->willReturn($data['id']);
        $tag->method('getStore')->willReturn($data['store'] ?? null);
        $tag->method('getBasePopularity')->willReturn($data['base_popularity'] ?? null);
        $visible = [];
        $tag->method('setVisibleInStoreIds')->willReturnCallback(
            function ($ids) use (&$visible, $tag) {
                $visible = $ids;
                return $tag;
            }
        );
        $tag->method('getVisibleInStoreIds')->willReturnCallback(function () use (&$visible) {
            return $visible;
        });
        return $tag;
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function invokeProtected(string $method, array $args)
    {
        $reflection = new \ReflectionMethod($this->resource, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->resource, $args);
    }
}
