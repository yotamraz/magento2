<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Session\SessionManager;
use Magento\Tag\Model\Session;
use Magento\Tag\Model\Session\Storage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SessionTest extends TestCase
{
    public function testSessionNamespaceConstant(): void
    {
        $this->assertSame('tag', Session::SESSION_NAMESPACE);
    }

    public function testExtendsSessionManager(): void
    {
        $this->assertTrue((new ReflectionClass(Session::class))->isSubclassOf(SessionManager::class));
    }

    public function testStorageBoundToTagNamespaceStorage(): void
    {
        $this->assertSame('tag', (new Storage())->getNamespace());
    }
}
