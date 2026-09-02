<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Tag;

use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Magento\Tag\Model\ResourceModel\Tag\Collection.
 */
#[DbIsolation(true)]
class CollectionTest extends TestCase
{
    private CollectionFactory $collectionFactory;
    private TagFactory $tagFactory;
    private TagResource $tagResource;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->collectionFactory = $om->get(CollectionFactory::class);
        $this->tagFactory        = $om->get(TagFactory::class);
        $this->tagResource       = $om->get(TagResource::class);
    }

    private function createTag(string $name, int $status): Tag
    {
        $tag = $this->tagFactory->create();
        $tag->setName($name)->setStatus($status);
        $this->tagResource->save($tag);
        return $tag;
    }

    public function testAddStatusFilterReturnsOnlyMatchingTags(): void
    {
        $suffix   = uniqid();
        $approved = $this->createTag('approved-' . $suffix, Tag::STATUS_APPROVED);
        $pending  = $this->createTag('pending-' . $suffix, Tag::STATUS_PENDING);

        $collection = $this->collectionFactory->create()
            ->addStatusFilter(Tag::STATUS_APPROVED)
            ->addFieldToFilter('name', ['like' => '%-' . $suffix]);

        $ids = $collection->getAllIds();
        $this->assertContains($approved->getId(), $ids);
        $this->assertNotContains($pending->getId(), $ids);
    }

    public function testCollectionCountMatchesInserted(): void
    {
        $suffix = uniqid();
        $this->createTag('col-test-a-' . $suffix, Tag::STATUS_APPROVED);
        $this->createTag('col-test-b-' . $suffix, Tag::STATUS_APPROVED);
        $this->createTag('col-test-c-' . $suffix, Tag::STATUS_PENDING);

        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('name', ['like' => 'col-test-%-' . $suffix]);

        $this->assertCount(3, $collection->getItems());
    }

    public function testGetSelectCountSql(): void
    {
        $suffix = uniqid();
        $this->createTag('count-sql-a-' . $suffix, Tag::STATUS_APPROVED);
        $this->createTag('count-sql-b-' . $suffix, Tag::STATUS_APPROVED);

        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('name', ['like' => 'count-sql-%-' . $suffix]);

        $this->assertSame(2, (int) $collection->getSize());
    }
}
