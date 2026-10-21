<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 *
 * Ported from legacy Mage_Tag_Helper_Data (original source licensed under AFL 3.0 / OSL 3.0).
 */
declare(strict_types=1);

namespace Magento\Tag\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Tag\Model\Tag;

/**
 * Tag data helper
 */
class Data extends AbstractHelper
{
    /**
     * @return array<int, \Magento\Framework\Phrase>
     */
    public function getStatusesArray()
    {
        return [
            Tag::STATUS_DISABLED => __('Disabled'),
            Tag::STATUS_PENDING  => __('Pending'),
            Tag::STATUS_APPROVED => __('Approved'),
        ];
    }

    /**
     * @return array<int, array<string, int|\Magento\Framework\Phrase>>
     */
    public function getStatusesOptionsArray()
    {
        return [
            [
                'label' => __('Disabled'),
                'value' => Tag::STATUS_DISABLED,
            ],
            [
                'label' => __('Pending'),
                'value' => Tag::STATUS_PENDING,
            ],
            [
                'label' => __('Approved'),
                'value' => Tag::STATUS_APPROVED,
            ],
        ];
    }

    /**
     * Check tags on the correctness of symbols and split string to array of tags
     *
     * @param  string $tagNamesInString
     * @return array
     */
    public function extractTags($tagNamesInString)
    {
        return explode("\n", preg_replace("/(\'(.*?)\')|(\s+)/i", "$1\n", $tagNamesInString));
    }

    /**
     * Clear tag from the separating characters
     *
     * @param  array $tagNamesArr
     * @return array
     */
    public function cleanTags(array $tagNamesArr)
    {
        foreach (array_keys($tagNamesArr) as $key) {
            $tagNamesArr[$key] = trim($tagNamesArr[$key], "'");
            $tagNamesArr[$key] = trim($tagNamesArr[$key]);
            if ($tagNamesArr[$key] == '') {
                unset($tagNamesArr[$key]);
            }
        }

        return $tagNamesArr;
    }
}
