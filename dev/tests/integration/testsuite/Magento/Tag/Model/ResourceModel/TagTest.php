<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt of Magento_Tag.
 */

declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\Tag\Model\TagFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Real-SQL checks of the Tag and Tag\Relation resource models (aggregation, summary, relations).
 *
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Catalog/_files/product_simple.php
 * @magentoDataFixture Magento/Customer/_files/customer.php
 */
class TagTest extends TestCase
{
    private const STORE_ID = 1;

    /**
     * @var int
     */
    private $productId;

    /**
     * @var int
     */
    private $customerId;

    /**
     * @var Tag
     */
    private $tag;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $this->customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)
            ->get('customer@example.com')
            ->getId();

        $this->tag = $objectManager->get(TagFactory::class)->create();
        $this->tag->setName('resource-tag')
            ->setStatus(Tag::STATUS_APPROVED)
            ->setFirstCustomerId($this->customerId)
            ->setFirstStoreId(self::STORE_ID)
            ->save();
        $this->tag->saveRelation($this->productId, $this->customerId, self::STORE_ID);
    }

    /**
     * @param int $storeId
     * @return array|false
     */
    private function fetchSummary(int $storeId)
    {
        $resourceConnection = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        return $connection->fetchRow(
            $connection->select()
                ->from($resourceConnection->getTableName('tag_summary'))
                ->where('tag_id = ?', (int)$this->tag->getId())
                ->where('store_id = ?', $storeId)
        );
    }

    /**
     * @return Relation
     */
    private function createRelation(): Relation
    {
        return Bootstrap::getObjectManager()->get(RelationFactory::class)->create();
    }

    public function testAggregateCreatesSummaryRows(): void
    {
        $this->assertFalse($this->fetchSummary(self::STORE_ID));

        $this->tag->aggregate();

        $storeRow = $this->fetchSummary(self::STORE_ID);
        $this->assertNotEmpty($storeRow);
        $this->assertEquals(1, $storeRow['customers']);
        $this->assertEquals(1, $storeRow['products']);
        $this->assertEquals(1, $storeRow['uses']);
        $this->assertEquals(1, $storeRow['popularity']);

        $globalRow = $this->fetchSummary(0);
        $this->assertNotEmpty($globalRow);
        $this->assertEquals(1, $globalRow['customers']);
        $this->assertEquals(1, $globalRow['products']);
    }

    public function testAddSummaryPopulatesTag(): void
    {
        $this->tag->aggregate();

        $loaded = Bootstrap::getObjectManager()->get(TagFactory::class)->create()->load($this->tag->getId());
        $loaded->addSummary(self::STORE_ID);

        $this->assertEquals(1, $loaded->getData('customers'));
        $this->assertEquals(1, $loaded->getData('products'));
        $this->assertEquals(1, $loaded->getPopularity());
        $this->assertEqualsCanonicalizing(
            [self::STORE_ID, 0],
            array_map('intval', $loaded->getVisibleInStoreIds())
        );
    }

    public function testDecrementProductsLowersSummaryProducts(): void
    {
        $this->tag->aggregate();

        $resource = Bootstrap::getObjectManager()->get(TagResource::class);
        $this->assertSame(0, $resource->decrementProducts([]));
        $this->assertGreaterThan(0, $resource->decrementProducts([(int)$this->tag->getId()]));

        $this->assertEquals(0, $this->fetchSummary(self::STORE_ID)['products']);
        $this->assertEquals(0, $this->fetchSummary(0)['products']);
    }

    public function testAggregateIgnoresDeactivatedRelations(): void
    {
        $this->createRelation()->setTagId($this->tag->getId())->setCustomerId($this->customerId)->deactivate();
        $this->tag->aggregate();

        $this->assertFalse($this->fetchSummary(self::STORE_ID));
        $this->assertFalse($this->fetchSummary(0));
    }

    public function testRelationDeactivateFlipsActiveFlag(): void
    {
        $relation = $this->createRelation()
            ->loadByTagCustomer($this->productId, (int)$this->tag->getId(), $this->customerId, self::STORE_ID);
        $this->assertEquals(Relation::STATUS_ACTIVE, $relation->getActive());

        $this->createRelation()
            ->setTagId($this->tag->getId())
            ->setCustomerId($this->customerId)
            ->deactivate();

        $reloaded = $this->createRelation()
            ->loadByTagCustomer($this->productId, (int)$this->tag->getId(), $this->customerId, self::STORE_ID);
        $this->assertNotEmpty($reloaded->getId());
        $this->assertEquals(Relation::STATUS_NOT_ACTIVE, $reloaded->getActive());
    }

    public function testRelationGetProductIdsAndRelatedTagIds(): void
    {
        $productIds = $this->createRelation()->setTagId($this->tag->getId())->getProductIds();
        $this->assertEquals([$this->productId], $productIds);

        $this->assertEquals(
            [],
            $this->createRelation()->setTagId($this->tag->getId())->setCustomerId($this->customerId + 100)
                ->getProductIds()
        );
        $this->assertEquals(
            [$this->productId],
            $this->createRelation()->setTagId($this->tag->getId())->setStoreId(self::STORE_ID)
                ->setStatusFilter(Tag::STATUS_APPROVED)->getProductIds()
        );
        $this->assertEquals(
            [],
            $this->createRelation()->setTagId($this->tag->getId())->setStatusFilter(Tag::STATUS_PENDING)
                ->getProductIds()
        );

        $tagIds = $this->createRelation()->setProductId($this->productId)->getRelatedTagIds();
        $this->assertContains((int)$this->tag->getId(), array_map('intval', $tagIds));
    }

    public function testAddRelationsReplacesProductsForStore(): void
    {
        $this->tag->setStore(self::STORE_ID);
        $this->createRelation()->addRelations($this->tag, []);

        $this->assertEquals([], $this->createRelation()->setTagId($this->tag->getId())->getProductIds());

        $this->createRelation()->addRelations($this->tag, [$this->productId]);
        $this->assertEquals(
            [$this->productId],
            $this->createRelation()->setTagId($this->tag->getId())->getProductIds()
        );
    }
}
