<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * Ported from legacy Mage_Tag_Model_Session (original source licensed under AFL 3.0 / OSL 3.0).
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Session\SessionManager;

/**
 * Tag session model (namespace "tag")
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Session extends SessionManager
{
    /**
     * Session namespace
     */
    public const SESSION_NAMESPACE = 'tag';
}
