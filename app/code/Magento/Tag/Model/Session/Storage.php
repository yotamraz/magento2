<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * Replaces the legacy Mage_Tag_Model_Session "tag" namespace (original source licensed under AFL 3.0 / OSL 3.0).
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Session;

use Magento\Framework\Session\Storage as SessionStorage;

/**
 * Tag session storage bound to the "tag" namespace
 */
class Storage extends SessionStorage
{
    /**
     * @param string $namespace
     * @param array $data
     */
    public function __construct($namespace = 'tag', array $data = [])
    {
        parent::__construct($namespace, $data);
    }
}
