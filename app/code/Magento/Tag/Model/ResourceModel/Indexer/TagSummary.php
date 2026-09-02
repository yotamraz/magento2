<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\ResourceModel\Indexer;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * SQL aggregation logic for tag_summary — used by the TagSummary indexer.
 */
class TagSummary extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('tag_summary', 'tag_id');
    }

    /**
     * Full reindex: rebuild tag_summary for all approved tags across all stores.
     */
    public function reindexAll(): void
    {
        $conn = $this->getConnection();

        $select = $conn->select()
            ->from(
                ['tr' => $this->getTable('tag_relation')],
                [
                    'tag_id'          => 'tr.tag_id',
                    'store_id'        => 'tr.store_id',
                    'customers'       => 'COUNT(DISTINCT tr.customer_id)',
                    'products'        => 'COUNT(DISTINCT tr.product_id)',
                    'uses'            => 'COUNT(tr.tag_relation_id)',
                    'historical_uses' => 'COUNT(tr.tag_relation_id)',
                    'popularity'      => 'COUNT(tr.tag_relation_id)',
                    'base_popularity' => new \Zend_Db_Expr('IFNULL(tp.base_popularity, 0)'),
                ]
            )
            ->joinLeft(
                ['tp' => $this->getTable('tag_properties')],
                'tp.tag_id = tr.tag_id AND tp.store_id = tr.store_id',
                []
            )
            ->where('tr.active = ?', 1)
            ->group(['tr.tag_id', 'tr.store_id']);

        $conn->query($conn->insertFromSelect(
            $select,
            $this->getTable('tag_summary'),
            ['tag_id', 'store_id', 'customers', 'products', 'uses', 'historical_uses', 'popularity', 'base_popularity'],
            \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_ON_DUPLICATE
        ));
    }

    /**
     * Resolve a mixed list of tag IDs and/or product entity IDs to tag IDs.
     *
     * The mview changelog combines IDs from two subscriptions: tag_relation
     * (entity_column=tag_id) and catalog_product_entity (entity_column=entity_id).
     * Both sets arrive in the same execute() call, so we look up both directions
     * in tag_relation and return the union of matching tag IDs.
     */
    public function resolveTagIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $conn   = $this->getConnection();
        $select = $conn->select()
            ->from(['tr' => $this->getTable('tag_relation')], ['tag_id'])
            ->where('tr.tag_id IN (?) OR tr.product_id IN (?)', $ids, $ids)
            ->distinct(true);

        return array_map('intval', $conn->fetchCol($select));
    }

    /**
     * Partial reindex: rebuild tag_summary rows for the given tag IDs.
     */
    public function reindexByIds(array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }
        $conn = $this->getConnection();

        $conn->delete($this->getTable('tag_summary'), ['tag_id IN (?)' => $tagIds]);

        $select = $conn->select()
            ->from(
                ['tr' => $this->getTable('tag_relation')],
                [
                    'tag_id'          => 'tr.tag_id',
                    'store_id'        => 'tr.store_id',
                    'customers'       => 'COUNT(DISTINCT tr.customer_id)',
                    'products'        => 'COUNT(DISTINCT tr.product_id)',
                    'uses'            => 'COUNT(tr.tag_relation_id)',
                    'historical_uses' => 'COUNT(tr.tag_relation_id)',
                    'popularity'      => 'COUNT(tr.tag_relation_id)',
                    'base_popularity' => new \Zend_Db_Expr('IFNULL(tp.base_popularity, 0)'),
                ]
            )
            ->joinLeft(
                ['tp' => $this->getTable('tag_properties')],
                'tp.tag_id = tr.tag_id AND tp.store_id = tr.store_id',
                []
            )
            ->where('tr.tag_id IN (?)', $tagIds)
            ->where('tr.active = ?', 1)
            ->group(['tr.tag_id', 'tr.store_id']);

        $rows = $conn->fetchAll($select);
        if ($rows) {
            $conn->insertMultiple($this->getTable('tag_summary'), $rows);
        }
    }
}
