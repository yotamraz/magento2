<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;

/**
 * Frontend "tag/" entry point; responds with a 404-safe forward to the noroute page
 */
class Index implements HttpGetActionInterface
{
    /**
     * @var ForwardFactory
     */
    private $resultForwardFactory;

    /**
     * @param ForwardFactory $resultForwardFactory
     */
    public function __construct(ForwardFactory $resultForwardFactory)
    {
        $this->resultForwardFactory = $resultForwardFactory;
    }

    /**
     * Forward to noroute
     *
     * @return Forward
     */
    public function execute()
    {
        return $this->resultForwardFactory->create()->forward('noroute');
    }
}
