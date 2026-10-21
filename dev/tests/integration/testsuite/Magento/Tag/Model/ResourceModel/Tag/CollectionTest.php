<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt of Magento_Tag.
 */

declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Tag;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Real-SQL checks of the base tag collection.
 *
 * @magentoDbIsolation enabled
 */
class CollectionTest extends TestCase
{
    /**
     * @return Collection
     */
    private function createCollection(): Collection
    {
        return Bootstrap::getObjectManager()->create(Collection::class);
    }

    /**
     * Create an approved tag, relate it to the fixture product and aggregate it
     *
     * @return array [tagId, productId, customerId]
     */
    private function createTaggedProduct(): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com')->getId();

        $tag = $objectManager->get(TagFactory::class)->create();
        $tag->setName('collection-tag')
            ->setStatus(Tag::STATUS_APPROVED)
            ->setFirstCustomerId($customerId)
            ->setFirstStoreId(1)
            ->save();
        $tag->saveRelation($productId, $customerId, 1);
        $tag->aggregate();

        return [(int)$tag->getId(), $productId, $customerId];
    }

    public function testStatusAndActiveFilterSql(): void
    {
        $collection = $this->createCollection()->joinRel()->addStatusFilter(Tag::STATUS_APPROVED)->setActiveFilter();
        $sql = $collection->getSelect()->__toString();
        $this->assertStringContainsString('main_table.status = 1', $sql);
        $this->assertStringContainsString('relation.active = 1', $sql);
    }

    public function testProductAndCustomerFilterSql(): void
    {
        $collection = $this->createCollection()->joinRel()->addProductFilter(10)->addCustomerFilter(6);
        $sql = $collection->getSelect()->__toString();
        $this->assertStringContainsString('tag_relation', $sql);
        $this->assertStringContainsString('`relation`.`product_id` = 10', $sql);
        $this->assertStringContainsString('relation.customer_id = 6', $sql);
    }

    public function testAddStoreFilterSql(): void
    {
        $all = $this->createCollection()->joinRel()->addStoreFilter(1)->getSelect()->__toString();
        $this->assertStringContainsString('summary_store.store_id IN (1)', $all);
        $this->assertStringContainsString('relation.store_id IN (1)', $all);

        $partial = $this->createCollection()->joinRel()->addStoreFilter(1, false)->getSelect()->__toString();
        $this->assertStringContainsString('summary_store.store_id IN (1)', $partial);
        $this->assertStringNotContainsString('relation.store_id IN (1)', $partial);
    }

    public function testAddSummaryAndPopularitySql(): void
    {
        $summary = $this->createCollection()->addSummary(1)->getSelect()->__toString();
        $this->assertStringContainsString('tag_summary', $summary);
        $this->assertStringContainsString('summary.store_id IN(1)', $summary);

        $popularity = $this->createCollection()->addPopularity(5)->getSelect()->__toString();
        $this->assertStringContainsString('tag_summary', $popularity);
        $this->assertStringContainsString('GROUP BY `main_table`.`tag_id`', $popularity);
        $this->assertStringContainsString('LIMIT 5', $popularity);
    }

    public function testAddTagGroupAndFieldMapSql(): void
    {
        $sql = $this->createCollection()->addTagGroup()->addFieldToFilter('tag_id', 3)
            ->getSelect()->__toString();
        $this->assertStringContainsString('GROUP BY `main_table`.`tag_id`', $sql);
        $this->assertStringContainsString('`main_table`.`tag_id` = 3', $sql);
    }

    public function testGetSelectCountSqlUsesDistinctCount(): void
    {
        $collection = $this->createCollection()->joinRel()->addTagGroup();
        $sql = $collection->getSelectCountSql()->__toString();
        $this->assertStringContainsString('COUNT(DISTINCT main_table.tag_id)', $sql);
        $this->assertStringNotContainsString('GROUP BY', $sql);
        $this->assertStringNotContainsString('ORDER BY', $sql);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testLoadFilteredByProductAndCustomer(): void
    {
        [$tagId, $productId, $customerId] = $this->createTaggedProduct();

        $collection = $this->createCollection()
            ->joinRel()
            ->addProductFilter($productId)
            ->addCustomerFilter($customerId)
            ->addStatusFilter(Tag::STATUS_APPROVED)
            ->addTagGroup();

        $this->assertSame(1, $collection->getSize());
        $this->assertSame(1, $collection->count());
        $this->assertEquals($tagId, $collection->getFirstItem()->getId());
        $this->assertSame('collection-tag', $collection->getFirstItem()->getName());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testLoadWithSummaryAndLimit(): void
    {
        [$tagId] = $this->createTaggedProduct();

        $collection = $this->createCollection()->addSummary(1)->addFieldToFilter('products', ['gte' => 1])->limit(1);
        $this->assertCount(1, $collection);
        $item = $collection->getFirstItem();
        $this->assertEquals($tagId, $item->getId());
        $this->assertEquals(1, $item->getData('products'));
        $this->assertEquals(1, $item->getData('customers'));
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testAddStoresVisibilityPopulatesStores(): void
    {
        $this->createTaggedProduct();

        $collection = $this->createCollection()->addStoresVisibility();
        $this->assertGreaterThan(0, $collection->count());
        foreach ($collection as $item) {
            $this->assertIsArray($item->getStores());
        }

        $this->assertContains(1, $collection->getFirstItem()->getStores());
    }
}
