<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Schema;

use PHPUnit\Framework\TestCase;

/**
 * Characterization test: etc/db_schema.xml must stay identical to the legacy Tag module install-1.6.0.0 script
 */
class DbSchemaTest extends TestCase
{
    /**
     * Expected column => [type, unsigned, nullable, identity, default, length] per table
     */
    private const EXPECTED_COLUMNS = [
        'tag' => [
            'tag_id' => ['int', 'true', 'false', 'true', null, null],
            'name' => ['varchar', null, 'true', null, null, '255'],
            'status' => ['smallint', 'false', 'false', 'false', '0', null],
            'first_customer_id' => ['int', 'true', 'true', 'false', null, null],
            'first_store_id' => ['smallint', 'true', 'true', 'false', null, null],
        ],
        'tag_relation' => [
            'tag_relation_id' => ['int', 'true', 'false', 'true', null, null],
            'tag_id' => ['int', 'true', 'false', 'false', '0', null],
            'customer_id' => ['int', 'true', 'true', 'false', null, null],
            'product_id' => ['int', 'true', 'false', 'false', '0', null],
            'store_id' => ['smallint', 'true', 'false', 'false', '1', null],
            'active' => ['smallint', 'true', 'false', 'false', '1', null],
            'created_at' => ['timestamp', null, 'true', null, null, null],
        ],
        'tag_summary' => [
            'tag_id' => ['int', 'true', 'false', 'false', '0', null],
            'store_id' => ['smallint', 'true', 'false', 'false', '0', null],
            'customers' => ['int', 'true', 'false', 'false', '0', null],
            'products' => ['int', 'true', 'false', 'false', '0', null],
            'uses' => ['int', 'true', 'false', 'false', '0', null],
            'historical_uses' => ['int', 'true', 'false', 'false', '0', null],
            'popularity' => ['int', 'true', 'false', 'false', '0', null],
            'base_popularity' => ['int', 'true', 'false', 'false', '0', null],
        ],
        'tag_properties' => [
            'tag_id' => ['int', 'true', 'false', 'false', '0', null],
            'store_id' => ['smallint', 'true', 'false', 'false', '0', null],
            'base_popularity' => ['int', 'true', 'false', 'false', '0', null],
        ],
    ];

    /**
     * Expected primary keys
     */
    private const EXPECTED_PRIMARY = [
        'tag' => ['tag_id'],
        'tag_relation' => ['tag_relation_id'],
        'tag_summary' => ['tag_id', 'store_id'],
        'tag_properties' => ['tag_id', 'store_id'],
    ];

    /**
     * Expected foreign keys: referenceId => [table, column, referenceTable, referenceColumn, onDelete]
     */
    private const EXPECTED_FOREIGN_KEYS = [
        'TAG_FIRST_CUSTOMER_ID_CUSTOMER_ENTITY_ENTITY_ID'
            => ['tag', 'first_customer_id', 'customer_entity', 'entity_id', 'SET NULL'],
        'TAG_FIRST_STORE_ID_STORE_STORE_ID' => ['tag', 'first_store_id', 'store', 'store_id', 'SET NULL'],
        'TAG_RELATION_CUSTOMER_ID_CUSTOMER_ENTITY_ENTITY_ID'
            => ['tag_relation', 'customer_id', 'customer_entity', 'entity_id', 'CASCADE'],
        'TAG_RELATION_PRODUCT_ID_CATALOG_PRODUCT_ENTITY_ENTITY_ID'
            => ['tag_relation', 'product_id', 'catalog_product_entity', 'entity_id', 'CASCADE'],
        'TAG_RELATION_STORE_ID_STORE_STORE_ID' => ['tag_relation', 'store_id', 'store', 'store_id', 'CASCADE'],
        'TAG_RELATION_TAG_ID_TAG_TAG_ID' => ['tag_relation', 'tag_id', 'tag', 'tag_id', 'CASCADE'],
        'TAG_SUMMARY_STORE_ID_STORE_STORE_ID' => ['tag_summary', 'store_id', 'store', 'store_id', 'CASCADE'],
        'TAG_SUMMARY_TAG_ID_TAG_TAG_ID' => ['tag_summary', 'tag_id', 'tag', 'tag_id', 'CASCADE'],
        'TAG_PROPERTIES_STORE_ID_STORE_STORE_ID'
            => ['tag_properties', 'store_id', 'store', 'store_id', 'CASCADE'],
        'TAG_PROPERTIES_TAG_ID_TAG_TAG_ID' => ['tag_properties', 'tag_id', 'tag', 'tag_id', 'CASCADE'],
    ];

    /**
     * Expected non-unique indexes: table => [referenceId => columns]
     */
    private const EXPECTED_INDEXES = [
        'tag' => [],
        'tag_relation' => [
            'TAG_RELATION_PRODUCT_ID' => ['product_id'],
            'TAG_RELATION_TAG_ID' => ['tag_id'],
            'TAG_RELATION_CUSTOMER_ID' => ['customer_id'],
            'TAG_RELATION_STORE_ID' => ['store_id'],
        ],
        'tag_summary' => [
            'TAG_SUMMARY_STORE_ID' => ['store_id'],
            'TAG_SUMMARY_TAG_ID' => ['tag_id'],
        ],
        'tag_properties' => [
            'TAG_PROPERTIES_STORE_ID' => ['store_id'],
        ],
    ];

    /**
     * @var \SimpleXMLElement
     */
    private $schema;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/etc/db_schema.xml';
        $this->assertFileExists($path);
        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml);
        $this->schema = $xml;
    }

    public function testTableList(): void
    {
        $tables = [];
        foreach ($this->schema->table as $table) {
            $tables[] = (string)$table['name'];
        }
        $this->assertSame(array_keys(self::EXPECTED_COLUMNS), $tables);
    }

    public function testColumns(): void
    {
        foreach ($this->schema->table as $table) {
            $name = (string)$table['name'];
            $actual = [];
            foreach ($table->column as $column) {
                $actual[(string)$column['name']] = [
                    (string)$column->attributes('xsi', true)['type'],
                    isset($column['unsigned']) ? (string)$column['unsigned'] : null,
                    isset($column['nullable']) ? (string)$column['nullable'] : null,
                    isset($column['identity']) ? (string)$column['identity'] : null,
                    isset($column['default']) ? (string)$column['default'] : null,
                    isset($column['length']) ? (string)$column['length'] : null,
                ];
            }
            $this->assertSame(self::EXPECTED_COLUMNS[$name], $actual, 'Columns differ for table ' . $name);
        }
    }

    public function testPrimaryKeys(): void
    {
        foreach ($this->schema->table as $table) {
            $columns = [];
            foreach ($table->constraint as $constraint) {
                if ((string)$constraint->attributes('xsi', true)['type'] === 'primary') {
                    foreach ($constraint->column as $column) {
                        $columns[] = (string)$column['name'];
                    }
                }
            }
            $this->assertSame(self::EXPECTED_PRIMARY[(string)$table['name']], $columns);
        }
    }

    public function testForeignKeys(): void
    {
        $actual = [];
        foreach ($this->schema->table as $table) {
            foreach ($table->constraint as $constraint) {
                if ((string)$constraint->attributes('xsi', true)['type'] !== 'foreign') {
                    continue;
                }
                $actual[(string)$constraint['referenceId']] = [
                    (string)$constraint['table'],
                    (string)$constraint['column'],
                    (string)$constraint['referenceTable'],
                    (string)$constraint['referenceColumn'],
                    (string)$constraint['onDelete'],
                ];
            }
        }
        $this->assertSame(self::EXPECTED_FOREIGN_KEYS, $actual);
    }

    public function testIndexesAndUniqueKey(): void
    {
        foreach ($this->schema->table as $table) {
            $actual = [];
            foreach ($table->index as $index) {
                $columns = [];
                foreach ($index->column as $column) {
                    $columns[] = (string)$column['name'];
                }
                $actual[(string)$index['referenceId']] = $columns;
            }
            $this->assertSame(self::EXPECTED_INDEXES[(string)$table['name']], $actual);
        }

        $unique = [];
        foreach ($this->schema->table as $table) {
            foreach ($table->constraint as $constraint) {
                if ((string)$constraint->attributes('xsi', true)['type'] === 'unique') {
                    foreach ($constraint->column as $column) {
                        $unique[(string)$table['name']][] = (string)$column['name'];
                    }
                }
            }
        }
        $this->assertSame(['tag_relation' => ['tag_id', 'customer_id', 'product_id', 'store_id']], $unique);
    }

    public function testWhitelistCoversEverythingDeclared(): void
    {
        $whitelist = json_decode(
            (string)file_get_contents(dirname(__DIR__, 3) . '/etc/db_schema_whitelist.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame(array_keys(self::EXPECTED_COLUMNS), array_keys($whitelist));
        foreach (self::EXPECTED_COLUMNS as $table => $columns) {
            $this->assertSame(array_keys($columns), array_keys($whitelist[$table]['column']));
            $this->assertSame(
                array_keys(self::EXPECTED_INDEXES[$table]),
                array_keys($whitelist[$table]['index'] ?? [])
            );
        }
        foreach (self::EXPECTED_FOREIGN_KEYS as $referenceId => $definition) {
            $this->assertArrayHasKey($referenceId, $whitelist[$definition[0]]['constraint']);
        }
        $this->assertArrayHasKey(
            'TAG_RELATION_TAG_ID_CUSTOMER_ID_PRODUCT_ID_STORE_ID',
            $whitelist['tag_relation']['constraint']
        );
        foreach (array_keys(self::EXPECTED_COLUMNS) as $table) {
            $this->assertArrayHasKey('PRIMARY', $whitelist[$table]['constraint']);
        }
    }
}
