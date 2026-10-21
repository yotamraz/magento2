<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Model\ResourceModel\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var Tag
     */
    private $resource;

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);

        $resources = $this->createMock(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($this->connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createMock(Context::class);
        $context->method('getResources')->willReturn($resources);

        $this->resource = new Tag(
            $context,
            new StringUtils(),
            $this->createMock(StoreManagerInterface::class)
        );
    }

    public function testLoadByNameReturnsFalseOnEmptyName(): void
    {
        $this->connection->expects($this->never())->method('fetchRow');

        $this->assertFalse($this->resource->loadByName(new DataObject(), ''));
    }

    public function testLoadByNameSetsFetchedData(): void
    {
        $model = new DataObject();
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['name' => 'foo'])
            ->willReturn(['tag_id' => 1, 'name' => 'foo']);

        $this->assertNull($this->resource->loadByName($model, 'foo'));
        $this->assertSame(['tag_id' => 1, 'name' => 'foo'], $model->getData());
    }

    public function testLoadByNameTruncatesLongNameAndHandlesMissingRow(): void
    {
        $model = new DataObject(['stale' => true]);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($this->select, ['name' => str_repeat('a', 255)])
            ->willReturn(false);

        $this->resource->loadByName($model, str_repeat('a', 300));
        $this->assertSame([], $model->getData());
    }
}
