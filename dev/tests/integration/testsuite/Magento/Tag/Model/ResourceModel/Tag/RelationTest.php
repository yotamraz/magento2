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
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Magento\Tag\Model\ResourceModel\Tag\Relation.
 */
#[DbIsolation(true)]
class RelationTest extends TestCase
{
    private RelationResource $relationResource;
    private RelationFactory $relationFactory;
    private TagFactory $tagFactory;
    private TagResource $tagResource;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->relationResource = $om->get(RelationResource::class);
        $this->relationFactory  = $om->get(RelationFactory::class);
        $this->tagFactory       = $om->get(TagFactory::class);
        $this->tagResource      = $om->get(TagResource::class);
    }

    private function createTag(string $name, int $status = Tag::STATUS_APPROVED): Tag
    {
        $tag = $this->tagFactory->create();
        $tag->setName($name)->setStatus($status);
        $this->tagResource->save($tag);
        return $tag;
    }

    public function testSaveAndLoadRelation(): void
    {
        $tag      = $this->createTag('rel-save-test-' . uniqid());
        $relation = $this->relationFactory->create();
        $relation->setTagId((int) $tag->getId())
            ->setProductId(1)
            ->setCustomerId(1)
            ->setStoreId(1)
            ->setActive(Relation::STATUS_ACTIVE);

        $this->relationResource->save($relation);
        $this->assertNotNull($relation->getId());
    }

    public function testGetProductIds(): void
    {
        $tag      = $this->createTag('getprodids-' . uniqid());
        $relation = $this->relationFactory->create();
        $relation->setTagId((int) $tag->getId())
            ->setProductId(42)
            ->setCustomerId(1)
            ->setStoreId(1)
            ->setActive(Relation::STATUS_ACTIVE);
        $this->relationResource->save($relation);

        $query = $this->relationFactory->create();
        $query->setTagId((int) $tag->getId());
        $ids = $this->relationResource->getProductIds($query);
        $this->assertContains(42, array_map('intval', $ids));
    }

    public function testDeactivate(): void
    {
        $tag      = $this->createTag('deactivate-' . uniqid());
        $relation = $this->relationFactory->create();
        $relation->setTagId((int) $tag->getId())
            ->setProductId(10)
            ->setCustomerId(5)
            ->setStoreId(1)
            ->setActive(Relation::STATUS_ACTIVE);
        $this->relationResource->save($relation);

        $this->relationResource->deactivate((int) $tag->getId(), 5);

        $loaded = $this->relationFactory->create();
        $this->relationResource->load($loaded, $relation->getId());
        $this->assertSame(Relation::STATUS_NOT_ACTIVE, $loaded->getActive());
    }

    public function testGetRelatedTagIds(): void
    {
        $tag1 = $this->createTag('rel-tag-a-' . uniqid());
        $tag2 = $this->createTag('rel-tag-b-' . uniqid());

        foreach ([$tag1, $tag2] as $tag) {
            $r = $this->relationFactory->create();
            $r->setTagId((int) $tag->getId())->setProductId(99)->setCustomerId(1)->setStoreId(1)
                ->setActive(Relation::STATUS_ACTIVE);
            $this->relationResource->save($r);
        }

        $query = $this->relationFactory->create();
        $query->setData('product_id', 99);
        $tagIds = array_map('intval', $this->relationResource->getRelatedTagIds($query));
        $this->assertContains((int) $tag1->getId(), $tagIds);
        $this->assertContains((int) $tag2->getId(), $tagIds);
    }
}
