<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel\Tag;

use Magento\Framework\DB\Select;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tag collection select-building methods
 */
class CollectionTest extends TestCase
{
    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var Collection|MockObject
     */
    private $collection;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSelect', 'getTable', '_getConditionSql'])
            ->getMock();
        $this->collection->method('getSelect')->willReturn($this->select);
        $this->collection->method('getTable')->willReturnArgument(0);
        $this->collection->method('_getConditionSql')->willReturn('condition_sql');
    }

    public function testJoinFlags(): void
    {
        $this->assertSame($this->collection, $this->collection->setJoinFlag('relation'));
        $this->assertTrue($this->collection->getFlag('relation'));
        $this->assertSame($this->collection, $this->collection->unsetJoinFlag('relation'));
        $this->assertFalse($this->collection->getFlag('relation'));
    }

    public function testLimit(): void
    {
        $this->select->expects($this->once())->method('limit')->with(5);
        $this->assertSame($this->collection, $this->collection->limit(5));
    }

    public function testJoinRel(): void
    {
        $this->select->expects($this->once())
            ->method('joinLeft')
            ->with(['relation' => 'tag_relation'], 'main_table.tag_id=relation.tag_id');
        $this->assertSame($this->collection, $this->collection->joinRel());
        $this->assertTrue($this->collection->getFlag('relation'));
    }

    public function testAddTagGroup(): void
    {
        $this->select->expects($this->once())->method('group')->with('main_table.tag_id');
        $this->assertSame($this->collection, $this->collection->addTagGroup());
    }

    public function testAddPopularityWithLimitRunsOnce(): void
    {
        $this->select->expects($this->exactly(2))->method('joinLeft')->willReturnSelf();
        $this->select->expects($this->once())->method('group')->with('main_table.tag_id')->willReturnSelf();
        $this->select->expects($this->once())->method('limit')->with(10);

        $this->assertSame($this->collection, $this->collection->addPopularity(10));
        // second call is a no-op because the "popularity" flag is set
        $this->collection->addPopularity(10);
        $this->assertTrue($this->collection->getFlag('popularity'));
    }

    public function testAddPopularityWithoutLimit(): void
    {
        $this->select->method('joinLeft')->willReturnSelf();
        $this->select->method('group')->willReturnSelf();
        $this->select->expects($this->never())->method('limit');

        $this->collection->addPopularity();
    }

    public function testAddStatusFilter(): void
    {
        $this->select->expects($this->once())->method('where')->with('main_table.status = ?', 1);
        $this->assertSame($this->collection, $this->collection->addStatusFilter(1));
    }

    public function testAddCustomerFilterWithoutPrelation(): void
    {
        $this->select->expects($this->once())->method('where')->with('relation.customer_id = ?', 7)->willReturnSelf();
        $this->assertSame($this->collection, $this->collection->addCustomerFilter(7));
    }

    public function testAddCustomerFilterWithPrelation(): void
    {
        $this->collection->setFlag('prelation', true);
        $calls = [];
        $this->select->expects($this->exactly(2))
            ->method('where')
            ->willReturnCallback(function (string $cond, $value) use (&$calls) {
                $calls[] = [$cond, $value];
                return $this->select;
            });

        $this->collection->addCustomerFilter(7);
        $this->assertSame(
            [['relation.customer_id = ?', 7], ['prelation.customer_id = ?', 7]],
            $calls
        );
    }

    public function testSetActiveFilter(): void
    {
        $calls = [];
        $this->collection->setFlag('prelation', true);
        $this->select->expects($this->exactly(2))
            ->method('where')
            ->willReturnCallback(function (string $cond, $value) use (&$calls) {
                $calls[] = [$cond, $value];
                return $this->select;
            });

        $this->assertSame($this->collection, $this->collection->setActiveFilter());
        $this->assertSame([['relation.active = ?', 1], ['prelation.active = ?', 1]], $calls);
    }

    public function testAddStoreFilterAppliesRelationFiltersOnce(): void
    {
        $this->collection->setFlag('relation', true);
        $this->select->expects($this->once())->method('joinLeft');
        $this->select->expects($this->once())->method('group')->with('main_table.tag_id');
        $calls = [];
        $this->select->expects($this->exactly(2))
            ->method('where')
            ->willReturnCallback(function (string $cond, $value) use (&$calls) {
                $calls[] = [$cond, $value];
                return $this->select;
            });

        $this->assertSame($this->collection, $this->collection->addStoreFilter([1, 2]));
        // repeated call is ignored
        $this->collection->addStoreFilter([1, 2]);
        $this->assertSame(
            [['summary_store.store_id IN (?)', [1, 2]], ['relation.store_id IN (?)', [1, 2]]],
            $calls
        );
    }

    public function testAddStoreFilterWithoutAllFilterSkipsRelationWhere(): void
    {
        $this->collection->setFlag('relation', true);
        $this->select->expects($this->once())->method('joinLeft');
        $this->select->expects($this->once())->method('group');
        $this->select->expects($this->once())->method('where')->with('summary_store.store_id IN (?)', 3);

        $this->collection->addStoreFilter(3, false);
    }

    public function testAddFieldToFilterPopularityUsesHavingWhenRelationJoined(): void
    {
        $this->collection->setFlag('relation', true);
        $this->select->expects($this->once())->method('having');
        $this->select->expects($this->never())->method('where');

        $this->assertSame($this->collection, $this->collection->addFieldToFilter('popularity', ['gt' => 1]));
    }

    public function testAddFieldToFilterSummaryFieldUsesWhere(): void
    {
        $this->collection->setFlag('summary', true);
        $this->select->expects($this->once())->method('where');
        $this->select->expects($this->never())->method('having');

        $this->assertSame($this->collection, $this->collection->addFieldToFilter('customers', ['gt' => 1]));
    }

    public function testAddStoresVisibilityOnlySetsFlag(): void
    {
        $this->assertSame($this->collection, $this->collection->addStoresVisibility());
        $this->assertTrue($this->collection->getFlag('add_stores_after'));
    }
}
