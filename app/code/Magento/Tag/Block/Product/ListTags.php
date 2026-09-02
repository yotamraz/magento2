<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Block\Product;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory;
use Magento\Tag\Model\Tag;

/**
 * Block that renders the approved tags on a product detail page.
 *
 * Mirrors Mage_Tag_Block_Product_List: loads the approved tag collection for
 * the current product and exposes helpers used by list.phtml.
 */
class ListTags extends Template
{
    protected $_template = 'Magento_Tag::tag/list.phtml';

    private ?iterable $collection = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CollectionFactory $tagCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return the current product entity ID, or null when not on a PDP.
     */
    public function getProductId(): ?int
    {
        $product = $this->registry->registry('current_product');
        return $product ? (int) $product->getId() : null;
    }

    /**
     * @return \Magento\Tag\Model\Tag[]
     */
    public function getTags(): array
    {
        return iterator_to_array($this->_getCollection(), false);
    }

    public function getCount(): int
    {
        return count($this->getTags());
    }

    /**
     * URL for the tag-save POST action.
     */
    public function getFormAction(): string
    {
        return $this->getUrl('tag/index/save', [
            'product' => $this->getProductId(),
            '_secure' => $this->getRequest()->isSecure(),
        ]);
    }

    /**
     * Return the full URL for "products tagged with $tag".
     */
    public function getTaggedProductsUrl(Tag $tag): string
    {
        return $this->getUrl('tag/product/list', ['tagId' => $tag->getTagId()]);
    }

    protected function _beforeToHtml(): static
    {
        if (!$this->getProductId()) {
            $this->setTemplate('');
        }
        return parent::_beforeToHtml();
    }

    private function _getCollection(): iterable
    {
        if ($this->collection === null) {
            $productId = $this->getProductId();
            if (!$productId) {
                return $this->collection = [];
            }
            $storeId          = $this->_storeManager->getStore()->getId();
            $this->collection = $this->tagCollectionFactory->create()
                ->addStatusFilter(Tag::STATUS_APPROVED)
                ->joinRel()
                ->addProductFilter($productId)
                ->addStoreFilter($storeId)
                ->setActiveFilter()
                ->addTagGroup()
                ->load();
        }
        return $this->collection;
    }
}
