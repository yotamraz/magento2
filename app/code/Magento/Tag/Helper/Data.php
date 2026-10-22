<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
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
     * Retrieve tag statuses as value => label map
     *
     * @return array
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
     * Retrieve tag statuses as option array
     *
     * @return array
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
     * Extract tag names from the string (quoted phrases are kept together)
     *
     * @param string $tagNamesInString
     * @return array
     */
    public function extractTags($tagNamesInString)
    {
        return explode("\n", preg_replace("/(\'(.*?)\')|(\s+)/i", "$1\n", $tagNamesInString));
    }

    /**
     * Clean tag names: strip quotes, trim and drop empty values
     *
     * @param array $tagNamesArr
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
