<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Tag\Helper\Data;
use Magento\Tag\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = new Data($this->createMock(Context::class));
    }

    public function testGetStatusesArray(): void
    {
        $statuses = $this->helper->getStatusesArray();

        $this->assertSame(
            [Tag::STATUS_DISABLED, Tag::STATUS_PENDING, Tag::STATUS_APPROVED],
            array_keys($statuses)
        );
        $this->assertSame(
            ['Disabled', 'Pending', 'Approved'],
            array_map(static fn ($label) => $label->getText(), array_values($statuses))
        );
    }

    public function testGetStatusesOptionsArray(): void
    {
        $options = $this->helper->getStatusesOptionsArray();

        $this->assertCount(3, $options);
        $this->assertSame(
            [Tag::STATUS_DISABLED, Tag::STATUS_PENDING, Tag::STATUS_APPROVED],
            array_column($options, 'value')
        );
        $this->assertSame(
            ['Disabled', 'Pending', 'Approved'],
            array_map(static fn ($label) => $label->getText(), array_column($options, 'label'))
        );
    }

    /**
     * @param string $input
     * @param array $expected
     */
    #[DataProvider('extractTagsDataProvider')]
    public function testExtractTags(string $input, array $expected): void
    {
        $this->assertSame($expected, $this->helper->extractTags($input));
    }

    /**
     * @return array
     */
    public static function extractTagsDataProvider(): array
    {
        return [
            'single' => ['foo', ['foo']],
            'whitespace separated' => ["foo bar\tbaz", ['foo', 'bar', 'baz']],
            'trailing whitespace' => ['a b ', ['a', 'b', '']],
            'quoted phrase' => ["'hello world' tag", ["'hello world'", '', 'tag']],
        ];
    }

    /**
     * @param array $input
     * @param array $expected
     */
    #[DataProvider('cleanTagsDataProvider')]
    public function testCleanTags(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->helper->cleanTags($input));
    }

    /**
     * @return array
     */
    public static function cleanTagsDataProvider(): array
    {
        return [
            'trims quotes and spaces, drops empty, keeps keys' => [
                ["'a'", ' b ', "''", '  ', 'c'],
                [0 => 'a', 1 => 'b', 4 => 'c'],
            ],
            'empty input' => [[], []],
        ];
    }

    public function testExtractAndCleanTagsRoundTrip(): void
    {
        $this->assertSame(
            [0 => 'hello world', 2 => 'tag'],
            $this->helper->cleanTags($this->helper->extractTags("'hello world' tag"))
        );
    }
}
