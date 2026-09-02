<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Tag\Model\ResourceModel\Indexer\TagSummary as TagSummaryResource;

/**
 * Tag summary indexer — plugs into admin Index Management and the Mview changelog framework.
 *
 * Full reindex: rebuilds all tag_summary rows.
 * Incremental reindex: rebuilds rows for the changed tag IDs supplied by Mview.
 */
class TagSummary implements ActionInterface, MviewActionInterface
{
    public function __construct(private readonly TagSummaryResource $resource)
    {
    }

    /**
     * Full reindex — triggered from admin "Reindex" or CLI indexer:reindex tag_summary.
     */
    public function executeFull(): void
    {
        $this->resource->reindexAll();
    }

    /**
     * Partial reindex for a list of entity IDs.
     */
    public function executeList(array $ids): void
    {
        $this->resource->reindexByIds(array_map('intval', $ids));
    }

    /**
     * Single-row reindex.
     */
    public function executeRow($id): void
    {
        $this->resource->reindexByIds([(int) $id]);
    }

    /**
     * Mview incremental update — called with changelog IDs.
     */
    public function execute($ids): void
    {
        $this->resource->reindexByIds(array_map('intval', (array) $ids));
    }
}
