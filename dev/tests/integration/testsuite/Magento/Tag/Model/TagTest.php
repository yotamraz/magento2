<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration test of tag persistence: save a tag, assign a relation, reload.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class TagTest extends TestCase
{
    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var RelationFactory
     */
    private $relationFactory;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->tagFactory = $objectManager->get(TagFactory::class);
        $this->relationFactory = $objectManager->get(RelationFactory::class);
    }

    public function testSaveAndLoadByName(): void
    {
        $tag = $this->tagFactory->create();
        $tag->setName('integration_tag')->setStatus(Tag::STATUS_APPROVED)->save();
        $this->assertNotEmpty($tag->getId());

        $loaded = $this->tagFactory->create()->loadByName('integration_tag');
        $this->assertEquals($tag->getId(), $loaded->getTagId());
        $this->assertEquals(Tag::STATUS_APPROVED, $loaded->getStatus());

        $byId = $this->tagFactory->create()->load($tag->getId());
        $this->assertSame('integration_tag', $byId->getName());
        $this->assertIsArray($byId->getVisibleInStoreIds());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSaveRelationAndReload(): void
    {
        $productId = 1;
        $customerId = 1;
        $storeId = 1;

        $tag = $this->tagFactory->create();
        $tag->setName('relation_tag')->setStatus(Tag::STATUS_APPROVED)->save();

        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $tag->saveRelation($productId, $customerId, $storeId));

        /** @var Relation $relation */
        $relation = $this->relationFactory->create()
            ->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, $storeId);
        $this->assertNotEmpty($relation->getId());
        $this->assertEquals(Relation::STATUS_ACTIVE, $relation->getActive());
        $this->assertEquals([$productId], $this->relationFactory->create()
            ->setTagId($tag->getId())
            ->setCustomerId($customerId)
            ->getProductIds());

        $reloadedTag = $this->tagFactory->create()->load($tag->getId());
        $reloadedTag->setStoreId($storeId);
        $this->assertEquals([$productId], $reloadedTag->getRelatedProductIds());
    }
}
