<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Indexer;

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

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->indexer            = $om->get(TagSummary::class);
        $this->tagFactory         = $om->get(TagFactory::class);
        $this->tagResource        = $om->get(TagResource::class);
        $this->relationFactory    = $om->get(RelationFactory::class);
        $this->relationResource   = $om->get(RelationResource::class);
        $this->resourceConnection = $om->get(\Magento\Framework\App\ResourceConnection::class);
    }

    private function createTagWithRelation(string $name, int $productId = 1, int $storeId = 1): Tag
    {
        $tag = $this->tagFactory->create();
        $tag->setName($name)->setStatus(Tag::STATUS_APPROVED);
        $this->tagResource->save($tag);

        $relation = $this->relationFactory->create();
        $relation->setTagId((int) $tag->getId())
            ->setProductId($productId)
            ->setCustomerId(1)
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
        $tag = $this->createTagWithRelation('idx-test-' . uniqid(), 1, 1);
        $tagId = (int) $tag->getId();

        $conn = $this->resourceConnection->getConnection();
        $conn->delete('tag_summary', ['tag_id = ?' => $tagId]);

        $this->indexer->executeList([$tagId]);

        $row = $this->getSummaryRow($tagId, 1);
        $this->assertNotNull($row, 'tag_summary row should exist after partial reindex');
        $this->assertGreaterThanOrEqual(1, (int) $row['uses']);
    }

    public function testExecuteFullRebuildsAllRows(): void
    {
        $tag = $this->createTagWithRelation('full-idx-' . uniqid(), 1, 1);
        $tagId = (int) $tag->getId();

        $conn = $this->resourceConnection->getConnection();
        $conn->delete('tag_summary', ['tag_id = ?' => $tagId]);

        $this->indexer->executeFull();

        $row = $this->getSummaryRow($tagId, 1);
        $this->assertNotNull($row, 'tag_summary row should exist after full reindex');
    }
}
