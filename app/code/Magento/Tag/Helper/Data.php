<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */

namespace Magento\Tag\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Tag\Model\Tag;

/**
 * Tag data helper — parses tag input strings and provides status label arrays.
 */
class Data extends AbstractHelper
{
    /**
     * Return [status_code => label] array for all three tag statuses.
     *
     * @return array<int, string>
     */
    public function getStatusesArray(): array
    {
        return [
            Tag::STATUS_DISABLED => __('Disabled'),
            Tag::STATUS_PENDING  => __('Pending'),
            Tag::STATUS_APPROVED => __('Approved'),
        ];
    }

    /**
     * Return [{label, value}] option list for select inputs.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getStatusesOptionsArray(): array
    {
        return [
            ['label' => __('Disabled'), 'value' => Tag::STATUS_DISABLED],
            ['label' => __('Pending'),  'value' => Tag::STATUS_PENDING],
            ['label' => __('Approved'), 'value' => Tag::STATUS_APPROVED],
        ];
    }

    /**
     * Split a raw tag input string into individual tag strings.
     *
     * Single-quoted phrases are kept together; everything else is split on whitespace.
     * Identical to Mage 1 Mage_Tag_Helper_Data::extractTags().
     *
     * @param string $tagNamesInString
     * @return string[]
     */
    public function extractTags(string $tagNamesInString): array
    {
        return explode("\n", preg_replace("/(\'(.*?)\')|(\s+)/i", "$1\n", $tagNamesInString));
    }

    /**
     * Trim quotes and whitespace from each tag; remove empty entries.
     *
     * @param string[] $tagNamesArr
     * @return string[]
     */
    public function cleanTags(array $tagNamesArr): array
    {
        foreach (array_keys($tagNamesArr) as $key) {
            $tagNamesArr[$key] = trim($tagNamesArr[$key], "'");
            $tagNamesArr[$key] = trim($tagNamesArr[$key]);
            if ($tagNamesArr[$key] === '') {
                unset($tagNamesArr[$key]);
            }
        }
        return $tagNamesArr;
    }
}
