<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    private Tag $tag;
    private RelationFactory&MockObject $relationFactoryMock;
    private IndexerRegistry&MockObject $indexerRegistryMock;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $registryMock = $this->createMock(Registry::class);
        $this->relationFactoryMock = $this->createMock(RelationFactory::class);
        $this->indexerRegistryMock = $this->createMock(IndexerRegistry::class);

        $this->tag = new Tag(
            $contextMock,
            $registryMock,
            $this->relationFactoryMock,
            $this->indexerRegistryMock
        );
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(-1, Tag::STATUS_DISABLED);
        $this->assertSame(0, Tag::STATUS_PENDING);
        $this->assertSame(1, Tag::STATUS_APPROVED);
    }

    public function testAddStatusConstants(): void
    {
        $this->assertSame('success', Tag::ADD_STATUS_SUCCESS);
        $this->assertSame('new', Tag::ADD_STATUS_NEW);
        $this->assertSame('exist', Tag::ADD_STATUS_EXIST);
        $this->assertSame('rejected', Tag::ADD_STATUS_REJECTED);
    }

    public function testGetApprovedStatus(): void
    {
        $this->assertSame(Tag::STATUS_APPROVED, $this->tag->getApprovedStatus());
    }

    public function testGetPendingStatus(): void
    {
        $this->assertSame(Tag::STATUS_PENDING, $this->tag->getPendingStatus());
    }

    public function testGetDisabledStatus(): void
    {
        $this->assertSame(Tag::STATUS_DISABLED, $this->tag->getDisabledStatus());
    }

    public function testSetAndGetName(): void
    {
        $this->tag->setData('name', 'test-tag');
        $this->assertSame('test-tag', $this->tag->getName());
    }

    public function testSetAndGetStatus(): void
    {
        $this->tag->setStatus(Tag::STATUS_APPROVED);
        $this->assertSame(Tag::STATUS_APPROVED, $this->tag->getStatus());
    }

    public function testGetTagIdReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->tag->getTagId());
    }

    public function testGetFirstCustomerIdNullable(): void
    {
        $this->tag->setFirstCustomerId(42);
        $this->assertSame(42, $this->tag->getFirstCustomerId());
    }

    public function testGetFirstStoreIdNullable(): void
    {
        $this->tag->setFirstStoreId(1);
        $this->assertSame(1, $this->tag->getFirstStoreId());
    }

    public function testGetIdentitiesIncludesId(): void
    {
        $this->tag->setData('tag_id', 7);
        $identities = $this->tag->getIdentities();
        $this->assertContains(Tag::CACHE_TAG . '_7', $identities);
    }

    public function testAfterCommitCallbackInvalidatesIndexer(): void
    {
        $indexerMock = $this->createMock(IndexerInterface::class);
        $indexerMock->expects($this->once())->method('isScheduled')->willReturn(false);
        $indexerMock->expects($this->once())->method('invalidate');

        $this->indexerRegistryMock
            ->expects($this->once())
            ->method('get')
            ->with('tag_summary')
            ->willReturn($indexerMock);

        $this->tag->afterCommitCallback();
    }

    public function testAfterCommitCallbackSkipsInvalidateWhenScheduled(): void
    {
        $indexerMock = $this->createMock(IndexerInterface::class);
        $indexerMock->expects($this->once())->method('isScheduled')->willReturn(true);
        $indexerMock->expects($this->never())->method('invalidate');

        $this->indexerRegistryMock
            ->expects($this->once())
            ->method('get')
            ->with('tag_summary')
            ->willReturn($indexerMock);

        $this->tag->afterCommitCallback();
    }
}
