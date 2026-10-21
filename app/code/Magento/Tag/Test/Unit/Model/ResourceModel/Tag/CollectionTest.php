<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel\Tag;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\TestCase;

/**
 * Select-shape tests for the base tag collection: every call made on the select is recorded and asserted.
 */
class CollectionTest extends TestCase
{
    /**
     * @var Select
     */
    private $select;

    /**
     * @var array
     */
    private $calls = [];

    /**
     * @var Collection
     */
    private $collection;

    protected function setUp(): void
    {
        $this->select = $this->createStub(Select::class);
        foreach (['from', 'joinLeft', 'where', 'group', 'limit', 'having'] as $method) {
            $this->select->method($method)->willReturnCallback(
                function (...$args) use ($method) {
                    $this->calls[] = [$method, $args];
                    return $this->select;
                }
            );
        }

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('quoteInto')->willReturnCallback(
            function ($text, $value) {
                return str_replace('?', implode(',', (array)$value), $text);
            }
        );
        $connection->method('prepareSqlCondition')->willReturnCallback(
            function ($field, $condition) {
                return $field . ' => ' . json_encode($condition);
            }
        );

        $resource = $this->createStub(TagResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('tag');
        $resource->method('getTable')->willReturnArgument(0);
        $resource->method('getIdFieldName')->willReturn('tag_id');

        $this->collection = (new ObjectManager($this))->getObject(
            Collection::class,
            ['connection' => $connection, 'resource' => $resource]
        );
        $this->calls = [];
    }

    /**
     * Arguments of every recorded call of the given select method
     *
     * @param string $method
     * @return array
     */
    private function callsTo(string $method): array
    {
        $result = [];
        foreach ($this->calls as [$name, $args]) {
            if ($name === $method) {
                $result[] = $args;
            }
        }

        return $result;
    }

    public function testAddStatusFilter(): void
    {
        $this->assertSame($this->collection, $this->collection->addStatusFilter(Tag::STATUS_APPROVED));
        $this->assertSame([['main_table.status = ?', Tag::STATUS_APPROVED]], $this->callsTo('where'));
    }

    public function testSetActiveFilterWithoutParentRelation(): void
    {
        $this->collection->setActiveFilter();
        $this->assertSame([['relation.active = ?', Relation::STATUS_ACTIVE]], $this->callsTo('where'));
    }

    public function testSetActiveFilterWithParentRelation(): void
    {
        $this->collection->setFlag('prelation', true);
        $this->collection->setActiveFilter();
        $this->assertSame(
            [
                ['relation.active = ?', Relation::STATUS_ACTIVE],
                ['prelation.active = ?', Relation::STATUS_ACTIVE],
            ],
            $this->callsTo('where')
        );
    }

    public function testAddCustomerFilter(): void
    {
        $this->collection->addCustomerFilter(6);
        $this->assertSame([['relation.customer_id = ?', 6]], $this->callsTo('where'));
    }

    public function testAddCustomerFilterWithParentRelation(): void
    {
        $this->collection->setFlag('prelation', true);
        $this->collection->addCustomerFilter(6);
        $this->assertSame(
            [['relation.customer_id = ?', 6], ['prelation.customer_id = ?', 6]],
            $this->callsTo('where')
        );
    }

    public function testAddProductFilterFiltersRelationProductId(): void
    {
        $this->collection->addProductFilter(10);
        $this->assertSame(
            [['relation.product_id => 10', null, Select::TYPE_CONDITION]],
            $this->callsTo('where')
        );
    }

    public function testAddProductFilterWithParentRelation(): void
    {
        $this->collection->setFlag('prelation', true);
        $this->collection->addProductFilter(10);
        $this->assertSame(
            [
                ['relation.product_id => 10', null, Select::TYPE_CONDITION],
                ['prelation.product_id => 10', null, Select::TYPE_CONDITION],
            ],
            $this->callsTo('where')
        );
    }

    public function testAddTagGroup(): void
    {
        $this->collection->addTagGroup();
        $this->assertSame([['main_table.tag_id']], $this->callsTo('group'));
    }

    public function testJoinRelJoinsTagRelationAndSetsFlag(): void
    {
        $this->assertSame($this->collection, $this->collection->joinRel());
        $this->assertTrue($this->collection->getFlag('relation'));
        $this->assertSame(
            [[['relation' => 'tag_relation'], 'main_table.tag_id=relation.tag_id']],
            $this->callsTo('joinLeft')
        );
    }

    public function testAddPopularityJoinsSummaryGroupsAndLimits(): void
    {
        $this->collection->addPopularity(5);
        $joins = $this->callsTo('joinLeft');
        $this->assertCount(2, $joins);
        $this->assertSame(
            [['relation' => 'tag_relation'], 'main_table.tag_id = relation.tag_id', []],
            $joins[0]
        );
        $this->assertSame(
            [
                ['summary' => 'tag_summary'],
                'relation.tag_id = summary.tag_id AND relation.store_id = summary.store_id',
                ['popularity'],
            ],
            $joins[1]
        );
        $this->assertSame([['main_table.tag_id']], $this->callsTo('group'));
        $this->assertSame([[5]], $this->callsTo('limit'));
        $this->assertTrue($this->collection->getFlag('popularity'));
    }

    public function testAddPopularityWithoutLimitAndIsOnlyAppliedOnce(): void
    {
        $this->collection->addPopularity();
        $this->assertSame([], $this->callsTo('limit'));

        $callCount = count($this->calls);
        $this->collection->addPopularity(3);
        $this->assertCount($callCount, $this->calls);
    }

    public function testAddSummaryJoinsSummaryAndMapsFields(): void
    {
        $this->collection->addSummary(2);
        $this->assertSame(
            [
                [
                    ['summary' => 'tag_summary'],
                    'main_table.tag_id = summary.tag_id AND summary.store_id IN(2)',
                    ['store_id', 'popularity', 'customers', 'products'],
                ],
            ],
            $this->callsTo('joinLeft')
        );
        $this->assertTrue($this->collection->getFlag('summary'));

        $callCount = count($this->calls);
        $this->collection->addSummary(3);
        $this->assertCount($callCount, $this->calls);
    }

    public function testAddFieldToFilterOnSummaryFieldUsesSummaryAlias(): void
    {
        $this->collection->addSummary(1);
        $this->calls = [];
        $this->collection->addFieldToFilter('customers', ['gt' => 1]);
        $this->assertSame([['summary.customers => {"gt":1}']], $this->callsTo('where'));
    }

    public function testAddFieldToFilterPopularityWithRelationUsesHaving(): void
    {
        $this->collection->setFlag('relation', true);
        $this->collection->addFieldToFilter('popularity', ['gt' => 2]);
        $this->assertSame([['COUNT(relation.tag_relation_id) => {"gt":2}']], $this->callsTo('having'));
        $this->assertSame([], $this->callsTo('where'));
    }

    public function testAddFieldToFilterMapsTagId(): void
    {
        $this->collection->addFieldToFilter('tag_id', 4);
        $this->assertSame([['main_table.tag_id => 4', null, Select::TYPE_CONDITION]], $this->callsTo('where'));
    }

    public function testAddStoreFilterWithAllFilter(): void
    {
        $this->collection->setFlag('relation', true);
        $this->collection->setFlag('prelation', true);
        $this->collection->addStoreFilter(2);

        $this->assertSame(
            [[['summary_store' => 'tag_summary'], 'main_table.tag_id = summary_store.tag_id']],
            $this->callsTo('joinLeft')
        );
        $this->assertSame(
            [
                ['summary_store.store_id IN (?)', 2],
                ['relation.store_id IN (?)', 2],
                ['prelation.store_id IN (?)', 2],
            ],
            $this->callsTo('where')
        );
        $this->assertSame([['main_table.tag_id']], $this->callsTo('group'));
        $this->assertTrue($this->collection->getFlag('store_filter'));
    }

    public function testAddStoreFilterWithoutAllFilterSkipsRelationStoreConditions(): void
    {
        $this->collection->setFlag('relation', true);
        $this->collection->setFlag('prelation', true);
        $this->collection->addStoreFilter(2, false);

        $this->assertSame([['summary_store.store_id IN (?)', 2]], $this->callsTo('where'));
    }

    public function testAddStoreFilterIsOnlyAppliedOnce(): void
    {
        $this->collection->addStoreFilter(2);
        $callCount = count($this->calls);
        $this->collection->addStoreFilter(3);
        $this->assertCount($callCount, $this->calls);
    }

    public function testLimitDelegatesToSelect(): void
    {
        $this->assertSame($this->collection, $this->collection->limit(7));
        $this->assertSame([[7]], $this->callsTo('limit'));
    }

    public function testAddStoresVisibilityOnlySetsFlag(): void
    {
        $this->assertSame($this->collection, $this->collection->addStoresVisibility());
        $this->assertTrue($this->collection->getFlag('add_stores_after'));
        $this->assertSame([], $this->calls);
    }
}
