<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Session\SessionManager;
use Magento\Tag\Model\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function testExtendsSessionManager(): void
    {
        $this->assertTrue(is_subclass_of(Session::class, SessionManager::class));
    }

    public function testStorageNamespaceIsConfiguredInDi(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        $virtual = $di->xpath('//virtualType[@name="Magento\Tag\Model\Session\Storage"]/arguments/argument[@name="namespace"]');
        $this->assertCount(1, $virtual);
        $this->assertSame('tag', (string)$virtual[0]);
    }
}
