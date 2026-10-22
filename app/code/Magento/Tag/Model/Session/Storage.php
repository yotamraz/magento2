<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Session;

use Magento\Framework\Session\Storage as SessionStorage;

/**
 * Tag session storage, bound to the "tag" session namespace
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
