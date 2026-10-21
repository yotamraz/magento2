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
}
