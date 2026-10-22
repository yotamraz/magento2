<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
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
        $statuses = $this->helper->getStatusesArray();
        $this->assertSame(
            [Tag::STATUS_DISABLED, Tag::STATUS_PENDING, Tag::STATUS_APPROVED],
            array_keys($statuses)
        );
        $this->assertSame('Approved', (string)$statuses[Tag::STATUS_APPROVED]);
    }

    public function testGetStatusesOptionsArray(): void
    {
        $options = $this->helper->getStatusesOptionsArray();
        $this->assertCount(3, $options);
        $this->assertSame(Tag::STATUS_PENDING, $options[1]['value']);
        $this->assertSame('Pending', (string)$options[1]['label']);
    }

    public function testExtractAndCleanTags(): void
    {
        $extracted = $this->helper->extractTags("foo 'bar baz' qux");
        $this->assertSame(['foo', "'bar baz'", '', 'qux'], $extracted);
        $this->assertSame(['foo', 'bar baz', 'qux'], array_values($this->helper->cleanTags($extracted)));
    }

    public function testCleanTagsDropsEmptyValues(): void
    {
        $this->assertSame([0 => 'a', 2 => 'b'], $this->helper->cleanTags(['a', "' '", ' b ']));
    }
}
