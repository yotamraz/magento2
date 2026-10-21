<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    /**
     * @var TagResource|MockObject
     */
    private $resource;

    /**
     * @var Tag
     */
    private $model;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(TagResource::class);
        $this->model = (new ObjectManager($this))->getObject(Tag::class, ['resource' => $this->resource]);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(-1, Tag::STATUS_DISABLED);
        $this->assertSame(0, Tag::STATUS_PENDING);
        $this->assertSame(1, Tag::STATUS_APPROVED);
        $this->assertSame(-1, $this->model->getDisabledStatus());
        $this->assertSame(0, $this->model->getPendingStatus());
        $this->assertSame(1, $this->model->getApprovedStatus());
        $this->assertSame('success', Tag::ADD_STATUS_SUCCESS);
        $this->assertSame('new', Tag::ADD_STATUS_NEW);
        $this->assertSame('exist', Tag::ADD_STATUS_EXIST);
        $this->assertSame('rejected', Tag::ADD_STATUS_REJECTED);
    }

    public function testLoadByNameDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('loadByName')->with($this->model, 'foo');
        $this->assertSame($this->model, $this->model->loadByName('foo'));
    }

    public function testAggregateDelegatesToResource(): void
    {
        $this->resource->expects($this->once())->method('aggregate')->with($this->model);
        $this->assertSame($this->model, $this->model->aggregate());
    }

    public function testAddSummarySetsStoreId(): void
    {
        $this->resource->expects($this->once())->method('addSummary')->with($this->model);
        $this->model->addSummary(3);
        $this->assertSame(3, $this->model->getStoreId());
    }

    public function testAddBasePopularityFlag(): void
    {
        $this->assertFalse($this->model->getAddBasePopularity());
        $this->model->setAddBasePopularity();
        $this->assertTrue($this->model->getAddBasePopularity());
    }

    public function testSimpleAccessors(): void
    {
        $this->model->setData(['tag_id' => 7, 'name' => 'x', 'popularity' => 2]);
        $this->model->setRatio(5);
        $this->assertSame(7, $this->model->getTagId());
        $this->assertSame('x', $this->model->getName());
        $this->assertSame(2, $this->model->getPopularity());
        $this->assertSame(5, $this->model->getRatio());
    }

    public function testIsAvailableInStore(): void
    {
        $this->model->setVisibleInStoreIds([1, 2]);
        $this->assertTrue($this->model->isAvailableInStore(2));
        $this->assertFalse($this->model->isAvailableInStore(3));
    }
}
