<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;

/**
 * On catalog_product_delete_after_done: remove tag–product relations and decrement product counts.
 *
 * Mirrors Mage_Tag_Model_Tag::productDeleteEventAction().
 */
class ProcessProductAfterDeleteObserver implements ObserverInterface
{
    public function __construct(
        private readonly CollectionFactory $tagCollectionFactory,
        private readonly TagResource $tagResource
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $collection = $this->tagCollectionFactory->create()
            ->joinRel()
            ->addProductFilter((int) $product->getId())
            ->addTagGroup()
            ->load();

        $tagIds = $collection->getAllIds();
        if ($tagIds) {
            $this->tagResource->decrementProducts($tagIds);
        }
    }
}
