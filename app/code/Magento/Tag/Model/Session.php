<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Session\SessionManager;

/**
 * Tag session model. The "tag" storage namespace is configured in etc/di.xml.
 */
class Session extends SessionManager
{
}
