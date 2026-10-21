<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Tag\Helper\Data;
use Magento\Tag\Model\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tag data helper
 */
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
            ['red', "'blue sky'", '', 'green'],
            $this->helper->extractTags("red 'blue sky' green")
        );
    }

    public function testCleanTags(): void
    {
        $this->assertSame(
            [0 => 'blue sky', 2 => 'green'],
            $this->helper->cleanTags(["'blue sky'", "''", ' green ', '  '])
        );
    }
}
