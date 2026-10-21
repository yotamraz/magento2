<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt of Magento_Tag.
 */

declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Persistence smoke test for Magento_Tag: save a tag, assign a relation, reload both.
 *
 * @magentoDbIsolation enabled
 */
class TagTest extends TestCase
{
    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSaveTagAssignRelationAndReload(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com')->getId();
        $storeId = 1;

        /** @var TagFactory $tagFactory */
        $tagFactory = $objectManager->get(TagFactory::class);
        $tag = $tagFactory->create();
        $tag->setName('integration-tag')
            ->setStatus(Tag::STATUS_APPROVED)
            ->setFirstCustomerId($customerId)
            ->setFirstStoreId($storeId)
            ->save();
        $this->assertNotEmpty($tag->getId());

        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $tag->saveRelation($productId, $customerId, $storeId));

        $loaded = $tagFactory->create()->loadByName('integration-tag');
        $this->assertEquals($tag->getId(), $loaded->getTagId());
        $this->assertSame('integration-tag', $loaded->getName());
        $this->assertEquals(Tag::STATUS_APPROVED, $loaded->getStatus());

        /** @var Relation $relation */
        $relation = $objectManager->get(RelationFactory::class)->create()
            ->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, $storeId);
        $this->assertNotEmpty($relation->getId());
        $this->assertEquals(Relation::STATUS_ACTIVE, $relation->getActive());
        $this->assertEquals([$productId], $loaded->setStoreId($storeId)->getRelatedProductIds());

        $this->assertSame(Tag::ADD_STATUS_EXIST, $loaded->saveRelation($productId, $customerId, $storeId));
    }

    /**
     * @param string $name
     * @param int $status
     * @param int $customerId
     * @return Tag
     */
    private function createTag(string $name, int $status, int $customerId): Tag
    {
        $tag = Bootstrap::getObjectManager()->get(TagFactory::class)->create();
        $tag->setName($name)
            ->setStatus($status)
            ->setFirstCustomerId($customerId)
            ->setFirstStoreId(1)
            ->save();

        return $tag;
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSaveRelationPendingTag(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com')->getId();

        $tag = $this->createTag('pending-tag', Tag::STATUS_PENDING, $customerId);
        $this->assertSame(Tag::ADD_STATUS_NEW, $tag->saveRelation($productId, $customerId, 1));

        $relation = $objectManager->get(RelationFactory::class)->create()
            ->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, 1);
        $this->assertNotEmpty($relation->getId());

        $tag->setStoreId(1)->setStatusFilter(Tag::STATUS_APPROVED);
        $this->assertSame([], $tag->getRelatedProductIds());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSaveRelationDisabledTagPromotesToPending(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com')->getId();

        $tag = $this->createTag('disabled-tag', Tag::STATUS_DISABLED, $customerId);
        $this->assertSame(Tag::ADD_STATUS_NEW, $tag->saveRelation($productId, $customerId, 1));

        $reloaded = $objectManager->get(TagFactory::class)->create()->loadByName('disabled-tag');
        $this->assertEquals(Tag::STATUS_PENDING, $reloaded->getStatus());

        $reloaded->setStatus(Tag::STATUS_DISABLED);
        $this->assertSame(Tag::ADD_STATUS_REJECTED, $reloaded->saveRelation($productId, $customerId, 1));
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testReactivateInactiveRelation(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)$objectManager->get(ProductRepositoryInterface::class)->get('simple')->getId();
        $customerId = (int)$objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com')->getId();
        $relationFactory = $objectManager->get(RelationFactory::class);

        $tag = $this->createTag('reactivate-tag', Tag::STATUS_APPROVED, $customerId);
        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $tag->saveRelation($productId, $customerId, 1));

        $relation = $relationFactory->create()->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, 1);
        $relation->setActive(Relation::STATUS_NOT_ACTIVE)->save();
        $this->assertEquals(
            Relation::STATUS_NOT_ACTIVE,
            $relationFactory->create()->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, 1)
                ->getActive()
        );

        $this->assertSame(Tag::ADD_STATUS_EXIST, $tag->saveRelation($productId, $customerId, 1));

        $reloaded = $relationFactory->create()->loadByTagCustomer($productId, (int)$tag->getId(), $customerId, 1);
        $this->assertEquals(Relation::STATUS_ACTIVE, $reloaded->getActive());
        $this->assertEquals($relation->getId(), $reloaded->getId());
    }
}
