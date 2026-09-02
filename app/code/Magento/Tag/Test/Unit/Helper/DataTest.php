<?php
/**
 * Copyright 2024 Adobe
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
    private Data $helper;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        $this->helper  = $objectManager->getObject(Data::class);
    }

    public function testGetStatusesArrayKeys(): void
    {
        $statuses = $this->helper->getStatusesArray();
        $this->assertArrayHasKey(Tag::STATUS_DISABLED, $statuses);
        $this->assertArrayHasKey(Tag::STATUS_PENDING, $statuses);
        $this->assertArrayHasKey(Tag::STATUS_APPROVED, $statuses);
    }

    public function testExtractTagsSpaceSeparated(): void
    {
        $result = $this->helper->extractTags('red blue green');
        $result = $this->helper->cleanTags($result);
        $this->assertContains('red', $result);
        $this->assertContains('blue', $result);
        $this->assertContains('green', $result);
    }

    public function testExtractTagsWithQuotedPhrase(): void
    {
        $result = $this->helper->extractTags("'multi word tag' simple");
        $result = $this->helper->cleanTags($result);
        $this->assertContains('multi word tag', $result);
        $this->assertContains('simple', $result);
    }

    public function testExtractTagsCommaSeparated(): void
    {
        // Commas are not separators in M1; only whitespace and quotes split tags.
        $result = $this->helper->extractTags('red,blue');
        $result = $this->helper->cleanTags($result);
        $this->assertContains('red,blue', $result);
    }

    public function testExtractTagsNewlineSeparated(): void
    {
        $result = $this->helper->extractTags("tag1\ntag2");
        $result = $this->helper->cleanTags($result);
        $this->assertContains('tag1', $result);
        $this->assertContains('tag2', $result);
    }

    public function testCleanTagsRemovesEmpty(): void
    {
        $result = $this->helper->cleanTags(['', 'red', '  ', 'blue', '']);
        $this->assertNotContains('', $result);
        $this->assertNotContains('  ', $result);
        $this->assertContains('red', $result);
        $this->assertContains('blue', $result);
    }

    public function testCleanTagsTrimsQuotes(): void
    {
        $result = $this->helper->cleanTags(["'hello world'"]);
        $this->assertContains('hello world', $result);
    }

    public function testExtractTagsEmptyString(): void
    {
        $result = $this->helper->cleanTags($this->helper->extractTags(''));
        $this->assertEmpty($result);
    }

    public function testGetStatusesOptionsArrayStructure(): void
    {
        $options = $this->helper->getStatusesOptionsArray();
        $this->assertCount(3, $options);
        foreach ($options as $option) {
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('value', $option);
        }
        $values = array_column($options, 'value');
        $this->assertContains(Tag::STATUS_DISABLED, $values);
        $this->assertContains(Tag::STATUS_PENDING, $values);
        $this->assertContains(Tag::STATUS_APPROVED, $values);
    }
}
