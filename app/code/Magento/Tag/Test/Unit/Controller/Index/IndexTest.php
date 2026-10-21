<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Controller\Index;

use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Tag\Controller\Index\Index;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the tag landing controller
 */
class IndexTest extends TestCase
{
    public function testExecuteForwardsToNoroute(): void
    {
        $forward = $this->createMock(Forward::class);
        $forward->expects($this->once())->method('forward')->with('noroute')->willReturnSelf();
        $factory = $this->createMock(ForwardFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($forward);

        $this->assertSame($forward, (new Index($factory))->execute());
    }
}
