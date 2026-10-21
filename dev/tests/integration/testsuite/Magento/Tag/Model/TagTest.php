<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the tag model, relation model and their resource models.
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

    /**
     * @var TagResource
     */
    private $tagResource;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->tagFactory = $objectManager->get(TagFactory::class);
        $this->relationFactory = $objectManager->get(RelationFactory::class);
        $this->tagResource = $objectManager->get(TagResource::class);
    }

    private function createTag(string $name, int $status): Tag
    {
        $tag = $this->tagFactory->create();
        $tag->setName($name)->setStatus($status)->setFirstStoreId(1);
        $tag->save();
        return $tag;
    }

    public function testSaveAndLoadByName(): void
    {
        $tag = $this->createTag('summer sale', Tag::STATUS_APPROVED);
        $this->assertNotEmpty($tag->getId());

        $loaded = $this->tagFactory->create();
        $loaded->loadByName('summer sale');
        $this->assertEquals($tag->getId(), $loaded->getTagId());
        $this->assertSame('summer sale', $loaded->getName());
        $this->assertEquals(Tag::STATUS_APPROVED, $loaded->getStatus());

        $byId = $this->tagFactory->create();
        $this->tagResource->load($byId, $tag->getId());
        $this->assertSame('summer sale', $byId->getName());
        $this->assertSame([], $byId->getVisibleInStoreIds());
    }

    public function testLoadByUnknownNameYieldsEmptyTag(): void
    {
        $loaded = $this->tagFactory->create();
        $loaded->loadByName('no such tag');
        $this->assertNull($loaded->getTagId());
    }

    public function testNameIsTruncatedTo255Characters(): void
    {
        $tag = $this->createTag(str_repeat('a', 300), Tag::STATUS_PENDING);

        $loaded = $this->tagFactory->create();
        $loaded->loadByName(str_repeat('a', 300));
        $this->assertEquals($tag->getId(), $loaded->getTagId());
        $this->assertSame(str_repeat('a', 255), $loaded->getName());
    }

    public function testDuplicateNameIsRejected(): void
    {
        $this->createTag('duplicate', Tag::STATUS_PENDING);

        $this->expectException(AlreadyExistsException::class);
        $this->createTag('duplicate', Tag::STATUS_PENDING);
    }

    public function testApprovedTagTakesOverPendingTagWithSameName(): void
    {
        $pending = $this->createTag('takeover', Tag::STATUS_PENDING);

        $approved = $this->tagFactory->create();
        $approved->setName('takeover')->setStatus(Tag::STATUS_APPROVED);
        $approved->save();

        $this->assertEquals($pending->getId(), $approved->getId());
        $loaded = $this->tagFactory->create();
        $loaded->loadByName('takeover');
        $this->assertEquals(Tag::STATUS_APPROVED, $loaded->getStatus());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSaveRelationAndReload(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)
            ->get('customer@example.com')
            ->getId();

        $tag = $this->createTag('fixture tag', Tag::STATUS_APPROVED);

        // first relation of an approved tag: success
        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $tag->saveRelation($productId, $customerId, 1));

        /** @var Relation $relation */
        $relation = $this->relationFactory->create();
        $relation->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, 1);
        $this->assertNotEmpty($relation->getId());
        $this->assertEquals(Relation::STATUS_ACTIVE, $relation->getActive());
        $this->assertNotEmpty($relation->getCreatedAt());

        // the product is now tagged
        $reloaded = $this->tagFactory->create();
        $reloaded->loadByName('fixture tag');
        $reloaded->setStoreId(1)->setStatusFilter(Tag::STATUS_APPROVED);
        $this->assertEquals([$productId], $reloaded->getRelatedProductIds());

        // second save of the same relation: exist, no duplicate row
        $this->assertSame(Tag::ADD_STATUS_EXIST, $tag->saveRelation($productId, $customerId, 1));
        $productIds = $this->relationFactory->create()
            ->setTagId($tag->getId())
            ->setCustomerId(null)
            ->getProductIds();
        $this->assertEquals([$productId], $productIds);

        // relation deactivation
        $relation->deactivate();
        $deactivated = $this->relationFactory->create();
        $deactivated->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, 1);
        $this->assertEquals(Relation::STATUS_NOT_ACTIVE, $deactivated->getActive());

        // related tag ids by product
        $related = $this->relationFactory->create()->setProductId($productId);
        $this->assertEquals([$tag->getId()], $related->getRelatedTagIds());
    }
}
