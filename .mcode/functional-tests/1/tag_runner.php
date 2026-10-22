<?php
// Bootstraps Magento and exercises Tag models; prints JSON result.
use Magento\Framework\App\Bootstrap;
require '/l2l/workspace/magento2/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
$conn = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$out = [];
$run = 'ftrun_' . uniqid();
$conn->query("SET FOREIGN_KEY_CHECKS=1");
$conn->insert('catalog_product_entity', ['sku' => $run, 'type_id' => 'simple', 'attribute_set_id' => 4]);
$pid = (int)$conn->lastInsertId('catalog_product_entity');
try {
    /** @var \Magento\Tag\Model\Tag $tag */
    $tag = $om->create(\Magento\Tag\Model\Tag::class);
    $tag->setName($run)->setStatus(\Magento\Tag\Model\Tag::STATUS_APPROVED)->save();
    $out['saved_id'] = (int)$tag->getId();
    $loaded = $om->create(\Magento\Tag\Model\Tag::class)->loadByName($run);
    $out['load_by_name_id'] = (int)$loaded->getTagId();
    $out['load_by_name_name'] = $loaded->getName();
    $out['load_missing_id'] = $om->create(\Magento\Tag\Model\Tag::class)->loadByName('nonexistent_' . $run)->getTagId();
    $out['status_consts'] = [
        \Magento\Tag\Model\Tag::STATUS_DISABLED, \Magento\Tag\Model\Tag::STATUS_PENDING, \Magento\Tag\Model\Tag::STATUS_APPROVED,
        \Magento\Tag\Model\Tag::ADD_STATUS_SUCCESS, \Magento\Tag\Model\Tag::ADD_STATUS_NEW,
        \Magento\Tag\Model\Tag::ADD_STATUS_EXIST, \Magento\Tag\Model\Tag::ADD_STATUS_REJECTED,
        \Magento\Tag\Model\Tag\Relation::STATUS_ACTIVE, \Magento\Tag\Model\Tag\Relation::STATUS_NOT_ACTIVE,
    ];
    $out['status_getters'] = [$tag->getApprovedStatus(), $tag->getPendingStatus(), $tag->getDisabledStatus()];

    // Relation save / reload
    $rel = $om->create(\Magento\Tag\Model\Tag\Relation::class);
    $rel->setTagId($tag->getId())->setProductId($pid)->setStoreId(1)->setActive(1)->setCreatedAt(date('Y-m-d H:i:s'))->save();
    $out['relation_id'] = (int)$rel->getId();
    $rel2 = $om->create(\Magento\Tag\Model\Tag\Relation::class)->load($rel->getId());
    $out['relation_reload'] = [(int)$rel2->getTagId(), (int)$rel2->getProductId(), (int)$rel2->getStoreId(), (int)$rel2->getActive()];
    $out['relation_product_ids'] = array_map('intval', $om->create(\Magento\Tag\Model\Tag\Relation::class)
        ->setTagId($tag->getId())->setStoreId(1)->getProductIds());

    // aggregate / summary
    $tag->aggregate();
    $row = $conn->fetchRow('SELECT * FROM tag_summary WHERE tag_id=? AND store_id=1', [$tag->getId()]);
    $out['summary_row'] = $row;
    $global = $conn->fetchRow('SELECT * FROM tag_summary WHERE tag_id=? AND store_id=0', [$tag->getId()]);
    $out['summary_global'] = $global;
    $t3 = $om->create(\Magento\Tag\Model\Tag::class)->load($tag->getId());
    $t3->addSummary(1);
    $out['addSummary'] = ['products' => $t3->getProducts(), 'popularity' => $t3->getPopularity()];

    // Helper\Data
    $h = $om->get(\Magento\Tag\Helper\Data::class);
    $out['helper_statuses'] = array_keys($h->getStatusesArray());
    $out['helper_options'] = count($h->getStatusesOptionsArray());
    $out['helper_extract'] = $h->cleanTags($h->extractTags("foo 'bar baz' qux"));
    // Session
    $s = $om->get(\Magento\Tag\Model\Session::class);
    $out['session_class'] = get_class($s);
    $s->setData('ft_key', 'v');
    $out['session_val'] = $s->getData('ft_key');
    // FK cascade: delete tag removes relation & summary
    $tag->delete();
    $out['after_delete'] = [
        (int)$conn->fetchOne('SELECT COUNT(*) FROM tag_relation WHERE tag_id=?', [$out['saved_id']]),
        (int)$conn->fetchOne('SELECT COUNT(*) FROM tag_summary WHERE tag_id=?', [$out['saved_id']]),
    ];
    $out['ok'] = true;
} catch (\Throwable $e) {
    $out['ok'] = false; $out['error'] = get_class($e) . ': ' . $e->getMessage();
} finally {
    $conn->delete('tag', ['name = ?' => $run]);
    $conn->delete('catalog_product_entity', ['entity_id = ?' => $pid]);
}
echo "\nJSON:" . json_encode($out) . "\n";
