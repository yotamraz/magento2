<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\TestCase;

class RelationTest extends TestCase
{
    private Relation $relation;

    protected function setUp(): void
    {
        $objectManager    = new ObjectManager($this);
        $this->relation   = $objectManager->getObject(Relation::class);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(1, Relation::STATUS_ACTIVE);
        $this->assertSame(0, Relation::STATUS_NOT_ACTIVE);
    }

    public function testSetAndGetTagId(): void
    {
        $this->relation->setTagId(5);
        $this->assertSame(5, $this->relation->getTagId());
    }

    public function testSetAndGetProductId(): void
    {
        $this->relation->setProductId(99);
        $this->assertSame(99, $this->relation->getProductId());
    }

    public function testSetAndGetStoreId(): void
    {
        $this->relation->setStoreId(2);
        $this->assertSame(2, $this->relation->getStoreId());
    }

    public function testSetAndGetCustomerIdNullable(): void
    {
        $this->relation->setCustomerId(null);
        $this->assertNull($this->relation->getCustomerId());

        $this->relation->setCustomerId(10);
        $this->assertSame(10, $this->relation->getCustomerId());
    }

    public function testSetAndGetActive(): void
    {
        $this->relation->setActive(Relation::STATUS_ACTIVE);
        $this->assertSame(Relation::STATUS_ACTIVE, $this->relation->getActive());

        $this->relation->setActive(Relation::STATUS_NOT_ACTIVE);
        $this->assertSame(Relation::STATUS_NOT_ACTIVE, $this->relation->getActive());
    }

    public function testGetTagRelationIdNullWhenNotSet(): void
    {
        $this->assertNull($this->relation->getTagRelationId());
    }
}
