<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel;

use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Magento\Tag\Model\ResourceModel\Tag.
 *
 * Requires a fully bootstrapped Magento 2 integration test environment.
 * All tests run inside a database transaction that is rolled back after each test.
 */
#[DbIsolation(true)]
class TagTest extends TestCase
{
    private Tag $tagResource;
    private TagFactory $tagFactory;

    protected function setUp(): void
    {
        $objectManager    = Bootstrap::getObjectManager();
        $this->tagResource = $objectManager->get(Tag::class);
        $this->tagFactory  = $objectManager->get(TagFactory::class);
    }

    public function testSaveAndLoad(): void
    {
        $tag = $this->tagFactory->create();
        $tag->setName('integration-test-tag-' . uniqid())
            ->setStatus(\Magento\Tag\Model\Tag::STATUS_PENDING);

        $this->tagResource->save($tag);
        $this->assertNotNull($tag->getId(), 'Tag ID should be set after save');

        $loaded = $this->tagFactory->create();
        $this->tagResource->load($loaded, $tag->getId());
        $this->assertSame($tag->getName(), $loaded->getName());
        $this->assertSame(\Magento\Tag\Model\Tag::STATUS_PENDING, $loaded->getStatus());
    }

    public function testLoadByName(): void
    {
        $uniqueName = 'loadbyname-test-' . uniqid();
        $tag = $this->tagFactory->create();
        $tag->setName($uniqueName)->setStatus(\Magento\Tag\Model\Tag::STATUS_APPROVED);
        $this->tagResource->save($tag);

        $found = $this->tagFactory->create();
        $this->tagResource->loadByName($found, $uniqueName);
        $this->assertSame($tag->getId(), $found->getId());
    }

    public function testLoadByNameReturnsEmptyForNonExistent(): void
    {
        $found = $this->tagFactory->create();
        $this->tagResource->loadByName($found, 'definitely-does-not-exist-' . uniqid());
        $this->assertNull($found->getId());
    }

    public function testDeleteRemovesTag(): void
    {
        $tag = $this->tagFactory->create();
        $tag->setName('delete-test-' . uniqid())->setStatus(\Magento\Tag\Model\Tag::STATUS_DISABLED);
        $this->tagResource->save($tag);
        $tagId = $tag->getId();

        $this->tagResource->delete($tag);

        $reloaded = $this->tagFactory->create();
        $this->tagResource->load($reloaded, $tagId);
        $this->assertNull($reloaded->getId(), 'Tag should be gone after delete');
    }

    public function testSaveApprovedStatusMergesExistingPending(): void
    {
        $name = 'merge-test-' . uniqid();

        $pending = $this->tagFactory->create();
        $pending->setName($name)->setStatus(\Magento\Tag\Model\Tag::STATUS_PENDING);
        $this->tagResource->save($pending);
        $pendingId = $pending->getId();

        // Save a new tag with same name and approved status — should reuse the pending row.
        $approved = $this->tagFactory->create();
        $approved->setName($name)->setStatus(\Magento\Tag\Model\Tag::STATUS_APPROVED);
        $this->tagResource->save($approved);

        $this->assertSame($pendingId, $approved->getId(), 'Approved tag should reuse pending row ID');
    }
}
