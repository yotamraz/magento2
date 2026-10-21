<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Session\SessionManager;

/**
 * Tag session model
 *
 * The session namespace ("tag") is configured through the
 * Magento\Tag\Model\Session\Storage virtual type in etc/di.xml.
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Session extends SessionManager
{
}
