<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\Tag as TagModel;

/**
 * Tag resource model
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Tag extends AbstractDb
{
    /**
     * @var StringUtils
     */
    private $string;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context $context
     * @param StringUtils $string
     * @param StoreManagerInterface $storeManager
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        StringUtils $string,
        StoreManagerInterface $storeManager,
        $connectionName = null
    ) {
        $this->string = $string;
        $this->storeManager = $storeManager;
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('tag', 'tag_id');
    }

    /**
     * Initialize unique fields
     *
     * @return $this
     */
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [[
            'field' => 'name',
            'title' => (string)__('Tag'),
        ]];
        return $this;
    }

    /**
     * Loading tag by name
     *
     * @param  TagModel|DataObject $model
     * @param  string $name
     * @return null|false
     */
    public function loadByName($model, $name)
    {
        if ($name) {
            $read = $this->getConnection();
            $select = $read->select();
            if ($this->string->strlen($name) > 255) {
                $name = $this->string->substr($name, 0, 255);
            }

            $select->from($this->getMainTable())
                ->where('name = :name');
            $data = $read->fetchRow($select, ['name' => $name]);

            $model->setData(is_array($data) ? $data : []);
        } else {
            return false;
        }

        return null;
    }

    /**
     * Before saving actions
     *
     * @param AbstractModel|TagModel $object
     * @return $this
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if (!$object->getId() && $object->getStatus() == $object->getApprovedStatus()) {
            $searchTag = new DataObject();
            $this->loadByName($searchTag, $object->getName());
            if ($searchTag->getData($this->getIdFieldName())
                && $searchTag->getStatus() == $object->getPendingStatus()
            ) {
                $object->setId($searchTag->getData($this->getIdFieldName()));
            }
        }

        if ($this->string->strlen((string)$object->getName()) > 255) {
            $object->setName($this->string->substr($object->getName(), 0, 255));
        }

        return parent::_beforeSave($object);
    }

    /**
     * Saving tag's base popularity
     *
     * @param AbstractModel|TagModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        if (!$object->getStore()
            || (int)$this->storeManager->getStore()->getId() !== Store::DEFAULT_STORE_ID
        ) {
            return parent::_afterSave($object);
        }

        $tagId = $object->isObjectNew() ? $object->getTagId() : $object->getId();

        $this->getConnection()->insertOnDuplicate($this->getTable('tag_properties'), [
            'tag_id'            => $tagId,
            'store_id'          => $object->getStore(),
            'base_popularity'   => $object->getBasePopularity() ? $object->getBasePopularity() : 0,
        ]);

        return parent::_afterSave($object);
    }

    /**
     * Getting base popularity per store view for specified tag
     *
     * @param  int   $tagId
     * @return array
     */
    protected function _getExistingBasePopularity($tagId)
    {
        $read = $this->getConnection();
        $selectSummary = $read->select()
            ->from(
                ['main' => $this->getTable('tag_summary')],
                ['store_id', 'base_popularity']
            )
            ->where('main.tag_id = :tag_id')
            ->where('main.store_id != 0');

        return $read->fetchAssoc($selectSummary, ['tag_id' => $tagId]);
    }

    /**
     * Get aggregation data per store view
     *
     * @param  int   $tagId
     * @return array
     */
    protected function _getAggregationPerStoreView($tagId)
    {
        $readAdapter = $this->getConnection();
        $selectLocal = $readAdapter->select()
            ->from(
                ['main'  => $this->getTable('tag_relation')],
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

        $selectLocalResult = $readAdapter->fetchAll($selectLocal, ['tag_id' => $tagId]);

        $selectHistorical = $readAdapter->select()
            ->from(
                ['main' => $this->getTable('tag_relation')],
                ['historical_uses' => 'COUNT(main.tag_relation_id)',
                    'store_id']
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
            ->group('main.store_id')
            ->where('main.tag_id = :tag_id')
            ->where('main.active = 1');

        $selectHistoricalResult = $readAdapter->fetchAll($selectHistorical, ['tag_id' => $tagId]);

        foreach ($selectHistoricalResult as $historical) {
            foreach ($selectLocalResult as $key => $local) {
                if ($local['store_id'] == $historical['store_id']) {
                    $selectLocalResult[$key]['historical_uses'] = $historical['historical_uses'];
                    break;
                }
            }
        }

        return $selectLocalResult;
    }

    /**
     * Get global aggregation data for row with store_id = 0
     *
     * @param  int   $tagId
     * @return array
     */
    protected function _getGlobalAggregation($tagId)
    {
        $readAdapter = $this->getConnection();
        // customers and products stats
        $selectGlobal = $readAdapter->select()
            ->from(
                ['main' => $this->getTable('tag_relation')],
                [
                    'customers' => 'COUNT(DISTINCT main.customer_id)',
                    'products'  => 'COUNT(DISTINCT main.product_id)',
                    'store_id'  => new Expression('0'),
                    'uses'      => 'COUNT(main.tag_relation_id)',
                ]
            )
            ->join(
                ['store' => $this->getTable('store')],
                'store.store_id=main.store_id AND store.store_id>0',
                []
            )
            ->join(
                ['product_website' => $this->getTable('catalog_product_website')],
                'product_website.website_id = store.website_id AND product_website.product_id = main.product_id',
                []
            )
            ->where('main.tag_id = :tag_id')
            ->where('main.active = 1');
        $result = $readAdapter->fetchRow($selectGlobal, ['tag_id' => $tagId]);
        if (!$result) {
            return [];
        }

        // historical uses stats
        $selectHistoricalGlobal = $readAdapter->select()
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
        $result['historical_uses'] = (int)$readAdapter->fetchOne($selectHistoricalGlobal, ['tag_id' => $tagId]);

        return $result;
    }

    /**
     * Getting statistics data into buffer.
     * Replacing our buffer array with new statistics and incoming data.
     *
     * @param  TagModel $object
     * @return TagModel
     */
    public function aggregate($object)
    {
        $tagId   = (int)$object->getId();
        $storeId = (int)$object->getStore();

        // create final summary from existing data and add specified base popularity
        $finalSummary = $this->_getExistingBasePopularity($tagId);
        if ($object->hasBasePopularity() && $storeId) {
            $finalSummary[$storeId]['store_id'] = $storeId;
            $finalSummary[$storeId]['base_popularity'] = $object->getBasePopularity();
        }

        // calculate aggregation data
        $summaries = $this->_getAggregationPerStoreView($tagId);
        $summariesGlobal = $this->_getGlobalAggregation($tagId);
        if ($summariesGlobal) {
            $summaries[] = $summariesGlobal;
        }

        // override final summary with aggregated data
        foreach ($summaries as $row) {
            $storeId = (int)$row['store_id'];
            foreach ($row as $key => $value) {
                $finalSummary[$storeId][$key] = $value;
            }
        }

        // prepare static parameters to final summary for insertion
        foreach ($finalSummary as $key => $row) {
            $finalSummary[$key]['tag_id'] = $tagId;
            foreach (['base_popularity', 'popularity', 'historical_uses', 'uses', 'products', 'customers'] as $str) {
                if (!isset($row[$str])) {
                    $finalSummary[$key][$str] = 0;
                }
            }

            $finalSummary[$key]['popularity'] = $finalSummary[$key]['historical_uses'];
        }

        // remove old and insert new data
        $write = $this->getConnection();
        $write->delete(
            $this->getTable('tag_summary'),
            ['tag_id = ?' => $tagId]
        );
        if ($finalSummary !== []) {
            $write->insertMultiple($this->getTable('tag_summary'), $finalSummary);
        }

        return $object;
    }

    /**
     * Decrementing tag products quantity as action for product delete
     *
     * @param array $tagsId
     * @return int The number of affected rows
     */
    public function decrementProducts(array $tagsId)
    {
        if ($tagsId === []) {
            return 0;
        }

        return $this->getConnection()->update(
            $this->getTable('tag_summary'),
            ['products' => new Expression('products - 1')],
            ['tag_id IN (?)' => $tagsId]
        );
    }

    /**
     * Add summary data to specified object
     *
     * @param  TagModel $object
     * @return TagModel
     */
    public function addSummary($object)
    {
        $read = $this->getConnection();
        $select = $read->select()
            ->from(['relation' => $this->getTable('tag_relation')], [])
            ->joinLeft(
                ['summary' => $this->getTable('tag_summary')],
                'relation.tag_id = summary.tag_id AND relation.store_id = summary.store_id',
                [
                    'customers',
                    'products',
                    'popularity',
                ]
            )
            ->where('relation.tag_id = :tag_id')
            ->where('relation.store_id = :store_id')
            ->limit(1);
        $bind = [
            'tag_id' => (int)$object->getId(),
            'store_id' => (int)$object->getStoreId(),
        ];
        $row = $read->fetchRow($select, $bind);
        if ($row) {
            $object->addData($row);
        }

        return $object;
    }

    /**
     * Retrieve select object for load object data
     * Redeclare parent method just for adding tag's base popularity if flag exists
     *
     * @param  string $field
     * @param  mixed $value
     * @param  AbstractModel|TagModel $object
     * @return \Magento\Framework\DB\Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        if ($object->getAddBasePopularity() && $object->hasStoreId()) {
            $select->joinLeft(
                ['properties' => $this->getTable('tag_properties')],
                'properties.tag_id = ' . $this->getMainTable() . '.tag_id AND properties.store_id = '
                    . (int)$object->getStoreId(),
                'base_popularity'
            );
        }

        return $select;
    }

    /**
     * Fetch store ids in which tag visible
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $read = $this->getConnection();
        $select = $read->select()
            ->from($this->getTable('tag_summary'), ['store_id'])
            ->where('tag_id = :tag_id');
        $storeIds = $read->fetchCol($select, ['tag_id' => $object->getId()]);

        $object->setVisibleInStoreIds($storeIds);

        return $this;
    }
}
