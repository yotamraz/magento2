<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * On catalog_product_save_commit_after: invalidate tag_summary index when a product's
 * visibility or status changes.
 *
 * Mirrors Mage_Tag_Model_Tag::productEventAggregate().
 */
class ProcessProductVisibilityChangeObserver implements ObserverInterface
{
    public function __construct(private readonly IndexerRegistry $indexerRegistry)
    {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        if ($product->dataHasChangedFor('visibility') || $product->dataHasChangedFor('status')) {
            $indexer = $this->indexerRegistry->get('tag_summary');
            if (!$indexer->isScheduled()) {
                $indexer->invalidate();
            }
        }
    }
}
