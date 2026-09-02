<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use Magento\Tag\Model\Tag;

/**
 * FPC-safe CustomerData section for tag data.
 *
 * Serves the logged-in customer's recent/active tag names via the /customer/section/load
 * AJAX endpoint so the block output can remain fully page-cached.
 */
class Tags implements SectionSourceInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $tagCollectionFactory
    ) {
    }

    /**
     * @return array{tags: list<array{name: string, id: int}>}
     */
    public function getSectionData(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['tags' => []];
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        $collection = $this->tagCollectionFactory->create()
            ->addStatusFilter(Tag::STATUS_APPROVED)
            ->joinRel()
            ->addCustomerFilter($customerId)
            ->setActiveFilter()
            ->addTagGroup();
        $collection->getSelect()->limit(10);
        $collection->load();

        $tags = [];
        foreach ($collection as $tag) {
            $tags[] = [
                'id'   => (int) $tag->getTagId(),
                'name' => $tag->getName(),
            ];
        }

        return ['tags' => $tags];
    }
}
