<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Tag resource model.
 */
class Tag extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('tag', 'tag_id');
        $this->_uniqueFields = [['field' => 'name', 'title' => 'Tag']];
    }

    /**
     * Load tag by name string.
     */
    public function loadByName(AbstractModel $model, string $name): void
    {
        $name = mb_substr($name, 0, 255);
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('name = :name');
        $data = $connection->fetchRow($select, ['name' => $name]);
        $model->setData(is_array($data) ? $data : []);
    }

    /**
     * Promote a PENDING tag to APPROVED when being saved with approved status (merges duplicates).
     */
    protected function _beforeSave(AbstractModel $object): static
    {
        if (!$object->getId() && $object->getStatus() == \Magento\Tag\Model\Tag::STATUS_APPROVED) {
            $existing = new \Magento\Framework\DataObject();
            $this->loadByName($existing, (string) $object->getName());
            if ($existing->getData($this->getIdFieldName())
                && $existing->getData('status') == \Magento\Tag\Model\Tag::STATUS_PENDING
            ) {
                $object->setId($existing->getData($this->getIdFieldName()));
            }
        }

        if (mb_strlen((string) $object->getName()) > 255) {
            $object->setData('name', mb_substr((string) $object->getName(), 0, 255));
        }

        return parent::_beforeSave($object);
    }

    /**
     * Save per-store base popularity when the object carries a store context.
     */
    protected function _afterSave(AbstractModel $object): static
    {
        $storeId = $object->getData('store');
        if ($storeId) {
            $tagId = $object->isObjectNew() ? $object->getData('tag_id') : (int) $object->getId();
            $this->getConnection()->insertOnDuplicate(
                $this->getTable('tag_properties'),
                [
                    'tag_id'          => $tagId,
                    'store_id'        => (int) $storeId,
                    'base_popularity' => (int) $object->getData('base_popularity'),
                ]
            );
        }

        return parent::_afterSave($object);
    }

    /**
     * Populate visible_in_store_ids from tag_summary after load.
     */
    protected function _afterLoad(AbstractModel $object): static
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('tag_summary'), ['store_id'])
            ->where('tag_id = :tag_id');
        $storeIds = $connection->fetchCol($select, ['tag_id' => (int) $object->getId()]);
        $object->setData('visible_in_store_ids', $storeIds);
        return parent::_afterLoad($object);
    }

    /**
     * Extend load select to optionally join base_popularity from tag_properties.
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        if ($object->getData('add_base_popularity') && $object->getData('store_id')) {
            $select->joinLeft(
                ['properties' => $this->getTable('tag_properties')],
                "properties.tag_id = {$this->getMainTable()}.tag_id"
                . " AND properties.store_id = " . (int) $object->getData('store_id'),
                ['base_popularity']
            );
        }
        return $select;
    }

    /**
     * Aggregate tag statistics into tag_summary for the given tag object.
     */
    public function aggregate(AbstractModel $object): AbstractModel
    {
        $tagId   = (int) $object->getId();
        $storeId = (int) $object->getData('store');
        $conn    = $this->getConnection();

        $existingBasePopularity = $this->_getExistingBasePopularity($tagId);
        if ($object->getData('has_base_popularity') && $storeId) {
            $existingBasePopularity[$storeId] = [
                'store_id'        => $storeId,
                'base_popularity' => (int) $object->getData('base_popularity'),
            ];
        }

        $summaries       = $this->_getAggregationPerStoreView($tagId);
        $summariesGlobal = $this->_getGlobalAggregation($tagId);
        if ($summariesGlobal) {
            $summaries[] = $summariesGlobal;
        }

        $finalSummary = $existingBasePopularity;
        foreach ($summaries as $row) {
            $sid = (int) $row['store_id'];
            foreach ($row as $key => $value) {
                $finalSummary[$sid][$key] = $value;
            }
        }

        $defaults = ['base_popularity', 'popularity', 'historical_uses', 'uses', 'products', 'customers'];
        foreach ($finalSummary as $sid => $row) {
            $finalSummary[$sid]['tag_id'] = $tagId;
            foreach ($defaults as $col) {
                if (!isset($finalSummary[$sid][$col])) {
                    $finalSummary[$sid][$col] = 0;
                }
            }
            $finalSummary[$sid]['popularity'] = $finalSummary[$sid]['historical_uses']
                + ($finalSummary[$sid]['base_popularity'] ?? 0);
        }

        $conn->delete($this->getTable('tag_summary'), ['tag_id = ?' => $tagId]);
        if (!empty($finalSummary)) {
            $conn->insertMultiple($this->getTable('tag_summary'), array_values($finalSummary));
        }

        return $object;
    }

    /**
     * Decrement product count in tag_summary for all given tag IDs.
     */
    public function decrementProducts(array $tagIds): int
    {
        if (empty($tagIds)) {
            return 0;
        }
        return (int) $this->getConnection()->update(
            $this->getTable('tag_summary'),
            ['products' => new \Zend_Db_Expr('products - 1')],
            ['tag_id IN (?)' => $tagIds]
        );
    }

    /**
     * Add summary data (customers, products, popularity) from tag_summary to the given tag object.
     */
    public function addSummary(AbstractModel $object): AbstractModel
    {
        $conn = $this->getConnection();
        $select = $conn->select()
            ->from(['summary' => $this->getTable('tag_summary')], ['customers', 'products', 'popularity'])
            ->where('summary.tag_id = :tag_id')
            ->where('summary.store_id = :store_id')
            ->limit(1);
        $row = $conn->fetchRow($select, [
            'tag_id'   => (int) $object->getId(),
            'store_id' => (int) $object->getData('store_id'),
        ]);
        if ($row) {
            $object->addData($row);
        }
        return $object;
    }

    private function _getExistingBasePopularity(int $tagId): array
    {
        $conn = $this->getConnection();
        $select = $conn->select()
            ->from(['main' => $this->getTable('tag_summary')], ['store_id', 'base_popularity'])
            ->where('main.tag_id = :tag_id')
            ->where('main.store_id != 0');
        return $conn->fetchAssoc($select, ['tag_id' => $tagId]);
    }

    private function _getAggregationPerStoreView(int $tagId): array
    {
        $conn = $this->getConnection();
        $select = $conn->select()
            ->from(
                ['main' => $this->getTable('tag_relation')],
                [
                    'customers' => 'COUNT(DISTINCT main.customer_id)',
                    'products'  => 'COUNT(DISTINCT main.product_id)',
                    'store_id',
                    'uses'      => 'COUNT(main.tag_relation_id)',
                ]
            )
            ->join(
                ['store' => $this->getTable('store')],
                'store.store_id = main.store_id AND store.store_id > 0',
                []
            )
            ->join(
                ['product_website' => $this->getTable('catalog_product_website')],
                'product_website.website_id = store.website_id AND product_website.product_id = main.product_id',
                []
            )
            ->where('main.tag_id = :tag_id')
            ->where('main.active = 1')
            ->group('main.store_id');
        $localResult = $conn->fetchAll($select, ['tag_id' => $tagId]);

        $selectHist = $conn->select()
            ->from(
                ['main' => $this->getTable('tag_relation')],
                ['historical_uses' => 'COUNT(main.tag_relation_id)', 'store_id']
            )
            ->join(
                ['store' => $this->getTable('store')],
                'store.store_id = main.store_id AND store.store_id > 0',
                []
            )
            ->join(
                ['product_website' => $this->getTable('catalog_product_website')],
                'product_website.website_id = store.website_id AND product_website.product_id = main.product_id',
                []
            )
            ->where('main.tag_id = :tag_id')
            ->where('main.active = 1')
            ->group('main.store_id');
        $histResult = $conn->fetchAll($selectHist, ['tag_id' => $tagId]);

        foreach ($histResult as $hist) {
            foreach ($localResult as $key => $local) {
                if ($local['store_id'] == $hist['store_id']) {
                    $localResult[$key]['historical_uses'] = $hist['historical_uses'];
                    break;
                }
            }
        }

        return $localResult;
    }

    private function _getGlobalAggregation(int $tagId): array
    {
        $conn = $this->getConnection();
        $select = $conn->select()
            ->from(
                ['main' => $this->getTable('tag_relation')],
                [
                    'customers' => 'COUNT(DISTINCT main.customer_id)',
                    'products'  => 'COUNT(DISTINCT main.product_id)',
                    'store_id'  => new \Zend_Db_Expr('0'),
                    'uses'      => 'COUNT(main.tag_relation_id)',
                ]
            )
            ->join(
                ['store' => $this->getTable('store')],
                'store.store_id = main.store_id AND store.store_id > 0',
                []
            )
            ->join(
                ['product_website' => $this->getTable('catalog_product_website')],
                'product_website.website_id = store.website_id AND product_website.product_id = main.product_id',
                []
            )
            ->where('main.tag_id = :tag_id')
            ->where('main.active = 1');
        $result = $conn->fetchRow($select, ['tag_id' => $tagId]);
        if (!$result) {
            return [];
        }

        $selectHistGlobal = $conn->select()
            ->from(
                ['main' => $this->getTable('tag_relation')],
                ['historical_uses' => 'COUNT(main.tag_relation_id)']
            )
            ->join(
                ['store' => $this->getTable('store')],
                'store.store_id = main.store_id AND store.store_id > 0',
                []
            )
            ->join(
                ['product_website' => $this->getTable('catalog_product_website')],
                'product_website.website_id = store.website_id AND product_website.product_id = main.product_id',
                []
            )
            ->where('main.tag_id = :tag_id')
            ->where('main.active = 1');
        $result['historical_uses'] = (int) $conn->fetchOne($selectHistGlobal, ['tag_id' => $tagId]);

        return $result;
    }
}
