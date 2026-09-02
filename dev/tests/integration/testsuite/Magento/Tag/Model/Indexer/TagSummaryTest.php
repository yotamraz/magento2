<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Indexer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Indexer\TagSummary;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Magento\Tag\Model\Indexer\TagSummary.
 *
 * Product fixtures are created inline via ObjectManager within the DB isolation
 * transaction to satisfy the FK constraint on tag_relation.product_id.
 * customer_id is set to null (nullable column) to avoid a separate customer fixture.
 *
 * @magentoAppArea adminhtml
 */
#[DbIsolation(true)]
class TagSummaryTest extends TestCase
{
    private TagSummary $indexer;
    private TagFactory $tagFactory;
    private TagResource $tagResource;
    private RelationFactory $relationFactory;
    private RelationResource $relationResource;
    private \Magento\Framework\App\ResourceConnection $resourceConnection;
    private ProductRepositoryInterface $productRepository;
    private \Magento\Catalog\Model\ProductFactory $productFactory;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->indexer             = $om->get(TagSummary::class);
        $this->tagFactory          = $om->get(TagFactory::class);
        $this->tagResource         = $om->get(TagResource::class);
        $this->relationFactory     = $om->get(RelationFactory::class);
        $this->relationResource    = $om->get(RelationResource::class);
        $this->resourceConnection  = $om->get(\Magento\Framework\App\ResourceConnection::class);
        $this->productRepository   = $om->get(ProductRepositoryInterface::class);
        $this->productFactory      = $om->get(\Magento\Catalog\Model\ProductFactory::class);
    }

    /**
     * Create a minimal simple product and return its entity_id.
     */
    private function createProduct(string $sku): int
    {
        /** @var Product $product */
        $product = $this->productFactory->create();
        $product->setSku($sku)
            ->setName('Tag Test Product ' . $sku)
            ->setTypeId('simple')
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setPrice(10.00)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);
        $this->productRepository->save($product);
        return (int) $product->getId();
    }

    private function createTagWithRelation(string $name, int $productId, int $storeId = 1): Tag
    {
        $tag = $this->tagFactory->create();
        $tag->setName($name)->setStatus(Tag::STATUS_APPROVED);
        $this->tagResource->save($tag);

        $relation = $this->relationFactory->create();
        $relation->setTagId((int) $tag->getId())
            ->setProductId($productId)
            ->setCustomerId(null)
            ->setStoreId($storeId)
            ->setActive(Relation::STATUS_ACTIVE);
        $this->relationResource->save($relation);

        return $tag;
    }

    private function getSummaryRow(int $tagId, int $storeId = 1): ?array
    {
        $conn = $this->resourceConnection->getConnection();
        $select = $conn->select()
            ->from('tag_summary')
            ->where('tag_id = ?', $tagId)
            ->where('store_id = ?', $storeId);
        $row = $conn->fetchRow($select);
        return $row ?: null;
    }

    public function testExecuteListRebuildsTagSummaryRows(): void
    {
        $productId = $this->createProduct('tag-inttest-' . uniqid());
        $tag       = $this->createTagWithRelation('idx-test-' . uniqid(), $productId);
        $tagId     = (int) $tag->getId();

        $conn = $this->resourceConnection->getConnection();
        $conn->delete('tag_summary', ['tag_id = ?' => $tagId]);

        $this->indexer->executeList([$tagId]);

        $row = $this->getSummaryRow($tagId);
        $this->assertNotNull($row, 'tag_summary row should exist after partial reindex');
        $this->assertGreaterThanOrEqual(1, (int) $row['uses']);
    }

    public function testExecuteFullRebuildsAllRows(): void
    {
        $productId = $this->createProduct('tag-full-' . uniqid());
        $tag       = $this->createTagWithRelation('full-idx-' . uniqid(), $productId);
        $tagId     = (int) $tag->getId();

        $conn = $this->resourceConnection->getConnection();
        $conn->delete('tag_summary', ['tag_id = ?' => $tagId]);

        $this->indexer->executeFull();

        $row = $this->getSummaryRow($tagId);
        $this->assertNotNull($row, 'tag_summary row should exist after full reindex');
    }
}
