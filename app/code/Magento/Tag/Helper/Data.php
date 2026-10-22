<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Tag\Model\Tag;

/**
 * Tag helper
 */
class Data extends AbstractHelper
{
    /**
     * @return array<int, string>
     */
    public function getStatusesArray()
    {
        return [
            Tag::STATUS_DISABLED => (string)__('Disabled'),
            Tag::STATUS_PENDING => (string)__('Pending'),
            Tag::STATUS_APPROVED => (string)__('Approved'),
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function getStatusesOptionsArray()
    {
        return [
            [
                'label' => (string)__('Disabled'),
                'value' => Tag::STATUS_DISABLED,
            ],
            [
                'label' => (string)__('Pending'),
                'value' => Tag::STATUS_PENDING,
            ],
            [
                'label' => (string)__('Approved'),
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
        return explode("\n", preg_replace("/(\'(.*?)\')|(\s+)/i", "$1\n", (string)$tagNamesInString));
    }

    /**
     * Clear tag from the separating characters
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
