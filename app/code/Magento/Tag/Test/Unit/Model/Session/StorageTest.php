<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Session;

use Magento\Tag\Model\Session\Storage;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    public function testDefaultNamespaceIsTag(): void
    {
        $storage = new Storage();

        $this->assertSame('tag', $storage->getNamespace());
    }

    public function testDataIsStoredUnderTagNamespace(): void
    {
        $storage = new Storage('tag', ['tag' => ['a' => 1]]);
        $storage->setData('b', 2);

        $this->assertSame(1, $storage->getData('a'));
        $this->assertSame(2, $storage->getData('b'));
        $this->assertSame(['a' => 1, 'b' => 2], $_SESSION['tag']);
    }

    public function testCustomNamespaceOverride(): void
    {
        $storage = new Storage('custom');

        $this->assertSame('custom', $storage->getNamespace());
    }
}
