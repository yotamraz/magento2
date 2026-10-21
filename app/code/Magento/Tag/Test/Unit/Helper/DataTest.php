<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Helper;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Helper\Data;
use Magento\Tag\Model\Tag;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = (new ObjectManager($this))->getObject(Data::class);
    }

    public function testGetStatusesArray(): void
    {
        $this->assertSame(
            [
                Tag::STATUS_DISABLED => 'Disabled',
                Tag::STATUS_PENDING => 'Pending',
                Tag::STATUS_APPROVED => 'Approved',
            ],
            $this->helper->getStatusesArray()
        );
    }

    public function testGetStatusesOptionsArray(): void
    {
        $this->assertSame(
            [
                ['label' => 'Disabled', 'value' => -1],
                ['label' => 'Pending', 'value' => 0],
                ['label' => 'Approved', 'value' => 1],
            ],
            $this->helper->getStatusesOptionsArray()
        );
    }

    public function testExtractTags(): void
    {
        $this->assertSame(
            ['foo', 'bar', "'two words'", 'baz'],
            array_values(array_filter($this->helper->extractTags("foo bar 'two words' baz"), 'strlen'))
        );
    }

    public function testCleanTags(): void
    {
        $this->assertSame(
            [0 => 'foo', 2 => 'two words'],
            $this->helper->cleanTags(["'foo'", "''", " 'two words' "])
        );
    }
}
