<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Persistence tests for the Tag model: save a tag, assign a relation, reload it.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class TagTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

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
        $this->objectManager = Bootstrap::getObjectManager();
        $this->tagFactory = $this->objectManager->get(TagFactory::class);
        $this->relationFactory = $this->objectManager->get(RelationFactory::class);
    }

    public function testSaveAndReloadTag(): void
    {
        $tag = $this->tagFactory->create();
        $tag->setName('foundation tag')->setStatus(Tag::STATUS_PENDING)->save();
        $this->assertNotEmpty($tag->getId());

        $reloaded = $this->tagFactory->create()->load($tag->getId());
        $this->assertSame('foundation tag', $reloaded->getName());
        $this->assertEquals(Tag::STATUS_PENDING, $reloaded->getStatus());
        $this->assertSame([], $reloaded->getVisibleInStoreIds());

        $byName = $this->tagFactory->create()->loadByName('foundation tag');
        $this->assertEquals($tag->getId(), $byName->getId());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testAssignRelationAndReload(): void
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $productId = (int)$productRepository->get('simple')->getId();

        $tag = $this->tagFactory->create();
        $tag->setName('related tag')->setStatus(Tag::STATUS_APPROVED)->save();

        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $tag->saveRelation($productId, null, 1));

        $reloaded = $this->tagFactory->create()->load($tag->getId());
        $reloaded->setStoreId(1);
        $this->assertEquals([$productId], $reloaded->getRelatedProductIds());

        $relation = $this->relationFactory->create();
        $relation->setTagId($tag->getId());
        $this->assertEquals([$tag->getId()], $relation->setProductId($productId)->getRelatedTagIds());

        /** @var Collection $collection */
        $collection = $this->objectManager->create(Collection::class);
        $collection->joinRel()->addProductFilter($productId)->addTagGroup();
        $this->assertSame([(string)$tag->getId()], array_map('strval', $collection->getAllIds()));
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testAddRelationsViaTagSave(): void
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $productId = (int)$productRepository->get('simple')->getId();

        $tag = $this->tagFactory->create();
        $tag->setName('assigned tag')
            ->setStatus(Tag::STATUS_APPROVED)
            ->setStore(1)
            ->setData('tag_assigned_products', [$productId])
            ->save();

        $relation = $this->relationFactory->create();
        $relation->setTagId($tag->getId());
        $this->assertEquals([$productId], $relation->getProductIds());
    }
}
