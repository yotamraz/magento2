<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RelationTest extends TestCase
{
    /**
     * @var RelationResource|MockObject
     */
    private $resource;

    /**
     * @var Relation
     */
    private $model;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(RelationResource::class);
        $this->model = (new ObjectManager($this))->getObject(
            Relation::class,
            ['resource' => $this->resource]
        );
    }

    public function testConstants(): void
    {
        $this->assertSame(1, Relation::STATUS_ACTIVE);
        $this->assertSame(0, Relation::STATUS_NOT_ACTIVE);
        $this->assertSame('tag_relation', Relation::ENTITY);
    }

    public function testLoadByTagCustomer(): void
    {
        $this->resource->expects($this->once())->method('loadByTagCustomer')->with($this->model);
        $this->assertSame($this->model, $this->model->loadByTagCustomer(5, 6, 7));
        $this->assertSame(5, $this->model->getProductId());
        $this->assertSame(6, $this->model->getTagId());
        $this->assertSame(7, $this->model->getCustomerId());
        $this->assertFalse($this->model->hasStoreId());
    }

    public function testLoadByTagCustomerSetsStore(): void
    {
        $this->model->loadByTagCustomer(5, 6, 7, 2);
        $this->assertSame(2, $this->model->getStoreId());
    }

    public function testGetProductIdsIsCached(): void
    {
        $this->resource->expects($this->once())->method('getProductIds')->willReturn([1, 2]);
        $this->assertSame([1, 2], $this->model->getProductIds());
        $this->assertSame([1, 2], $this->model->getProductIds());
    }

    public function testGetRelatedTagIdsIsCached(): void
    {
        $this->resource->expects($this->once())->method('getRelatedTagIds')->willReturn([3]);
        $this->assertSame([3], $this->model->getRelatedTagIds());
        $this->assertSame([3], $this->model->getRelatedTagIds());
    }

    public function testDeactivate(): void
    {
        $this->model->setTagId(4)->setCustomerId(9);
        $this->resource->expects($this->once())->method('deactivate')->with(4, 9);
        $this->assertSame($this->model, $this->model->deactivate());
    }
}
