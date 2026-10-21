<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt of Magento_Tag.
 */

declare(strict_types=1);

namespace Magento\Tag\Controller;

use Magento\Framework\App\Route\ConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Milestone 1 ships no tag controllers (those arrive in Milestone 3), so this only verifies that the
 * "tag" frontend route is registered for Magento_Tag. Dispatching "tag/" belongs to Milestone 3.
 */
class RouteTest extends TestCase
{
    /**
     * @magentoAppArea frontend
     */
    public function testTagFrontNameIsRegisteredForModule(): void
    {
        /** @var ConfigInterface $routeConfig */
        $routeConfig = Bootstrap::getObjectManager()->get(ConfigInterface::class);

        $this->assertSame('tag', $routeConfig->getRouteFrontName('tag', 'frontend'));
        $this->assertContains('Magento_Tag', $routeConfig->getModulesByFrontName('tag', 'frontend'));
    }
}
