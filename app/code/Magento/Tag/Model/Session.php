<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * Ported from legacy Mage_Tag_Model_Session (original source licensed under AFL 3.0 / OSL 3.0).
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\ValidatorInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Tag\Model\Session\Storage;

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

    /**
     * @param Http $request
     * @param SidResolverInterface $sidResolver
     * @param ConfigInterface $sessionConfig
     * @param SaveHandlerInterface $saveHandler
     * @param ValidatorInterface $validator
     * @param Storage $storage Storage bound to the "tag" namespace
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param State $appState
     * @param SessionStartChecker|null $sessionStartChecker
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Http $request,
        SidResolverInterface $sidResolver,
        ConfigInterface $sessionConfig,
        SaveHandlerInterface $saveHandler,
        ValidatorInterface $validator,
        Storage $storage,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        State $appState,
        ?SessionStartChecker $sessionStartChecker = null
    ) {
        parent::__construct(
            $request,
            $sidResolver,
            $sessionConfig,
            $saveHandler,
            $validator,
            $storage,
            $cookieManager,
            $cookieMetadataFactory,
            $appState,
            $sessionStartChecker
        );
    }
}
