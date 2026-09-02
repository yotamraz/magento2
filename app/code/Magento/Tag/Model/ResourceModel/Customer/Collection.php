<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Customer;

use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;

/**
 * Customers who applied a tag — extends the customer EAV collection.
 *
 * Joins tag_relation (alias "tr") and tag (alias "t") to the customer select.
 */
class Collection extends CustomerCollection
{
    private bool $allowDisableGrouping = true;
    private string $countAttribute = 'tr.tag_id';

    protected function _initSelect(): static
    {
        parent::_initSelect();
        $this->_joinFields();
        $this->_setIdFieldName('tag_relation_id');
        return $this;
    }

    private function _joinFields(): void
    {
        $this->addAttributeToSelect('firstname')
            ->addAttributeToSelect('middlename')
            ->addAttributeToSelect('lastname')
            ->addAttributeToSelect('email');

        $this->getSelect()
            ->join(
                ['tr' => $this->getTable('tag_relation')],
                'tr.customer_id = e.entity_id',
                ['tag_relation_id', 'product_id', 'active', 'added_in' => 'store_id']
            )
            ->join(
                ['t' => $this->getTable('tag')],
                't.tag_id = tr.tag_id',
                ['tag_id', 'name', 'status', 'first_customer_id', 'first_store_id']
            );
    }

    public function addTagFilter(int $tagId): static
    {
        $this->getSelect()->where('tr.tag_id = ?', $tagId);
        return $this;
    }

    public function addProductFilter(int $productId): static
    {
        $this->getSelect()->where('tr.product_id = ?', $productId);
        return $this;
    }

    public function addStoreFilter(int|array $storeId): static
    {
        $this->getSelect()->where('tr.store_id IN (?)', $storeId);
        return $this;
    }

    public function addStatusFilter(int $status): static
    {
        $this->getSelect()->where('t.status = ?', $status);
        return $this;
    }

    public function addCustomerFilter(int $customerId): static
    {
        $this->getSelect()->where('tr.customer_id = ?', $customerId);
        return $this;
    }

    public function addGroupByTag(): static
    {
        $this->getSelect()->group('tr.tag_id');
        $this->allowDisableGrouping = true;
        return $this;
    }

    public function addGroupByCustomer(): static
    {
        $this->getSelect()->group('tr.customer_id');
        $this->allowDisableGrouping = false;
        return $this;
    }

    public function addGroupByCustomerProduct(): static
    {
        $this->allowDisableGrouping = false;
        return $this;
    }

    public function addDescOrder(): static
    {
        $this->getSelect()->order('tr.tag_relation_id DESC');
        return $this;
    }

    public function setCountAttribute(string $value): static
    {
        $this->countAttribute = $value;
        return $this;
    }

    public function getCountAttribute(): string
    {
        return $this->countAttribute;
    }

    public function getSelectCountSql(): \Magento\Framework\DB\Select
    {
        $countSelect = parent::getSelectCountSql();
        if ($this->allowDisableGrouping) {
            $countSelect->reset(\Zend_Db_Select::COLUMNS);
            $countSelect->reset(\Zend_Db_Select::GROUP);
            $countSelect->columns('COUNT(DISTINCT ' . $this->countAttribute . ')');
        }
        return $countSelect;
    }

    public function addFieldToFilter($attribute, $condition = null): static
    {
        if ($attribute === 'name') {
            $this->getSelect()->where($this->_getConditionSql('t.name', $condition));
            return $this;
        }
        return parent::addFieldToFilter($attribute, $condition);
    }
}
