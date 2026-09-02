<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tag\Api\TagRepositoryInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Magento\Tag\Model\TagRepository.
 */
#[DbIsolation(true)]
class TagRepositoryTest extends TestCase
{
    private TagRepositoryInterface $repository;
    private SearchCriteriaBuilder $criteriaBuilder;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->repository      = $om->get(TagRepositoryInterface::class);
        $this->criteriaBuilder = $om->get(SearchCriteriaBuilder::class);
    }

    public function testSaveAndGet(): void
    {
        $tag = Bootstrap::getObjectManager()->create(Tag::class);
        $tag->setName('repo-save-' . uniqid())->setStatus(Tag::STATUS_PENDING);

        $saved = $this->repository->save($tag);
        $this->assertNotNull($saved->getId());

        $loaded = $this->repository->get((int) $saved->getId());
        $this->assertSame($saved->getName(), $loaded->getName());
    }

    public function testGetThrowsForMissingId(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->repository->get(999999);
    }

    public function testDeleteById(): void
    {
        $tag = Bootstrap::getObjectManager()->create(Tag::class);
        $tag->setName('repo-delete-' . uniqid())->setStatus(Tag::STATUS_APPROVED);
        $saved = $this->repository->save($tag);
        $tagId = (int) $saved->getId();

        $this->assertTrue($this->repository->deleteById($tagId));

        $this->expectException(NoSuchEntityException::class);
        $this->repository->get($tagId);
    }

    public function testGetList(): void
    {
        $suffix = uniqid();
        foreach (['alpha-' . $suffix, 'beta-' . $suffix] as $name) {
            $tag = Bootstrap::getObjectManager()->create(Tag::class);
            $tag->setName($name)->setStatus(Tag::STATUS_APPROVED);
            $this->repository->save($tag);
        }

        $criteria = $this->criteriaBuilder
            ->addFilter('name', '%-' . $suffix, 'like')
            ->create();
        $results = $this->repository->getList($criteria);
        $this->assertGreaterThanOrEqual(2, $results->getTotalCount());
    }
}
