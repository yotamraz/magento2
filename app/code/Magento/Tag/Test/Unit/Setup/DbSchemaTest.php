<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Setup;

use PHPUnit\Framework\TestCase;

/**
 * Characterization test: etc/db_schema.xml must match the legacy tag_setup/install-1.6.0.0 definition.
 */
class DbSchemaTest extends TestCase
{
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Expected columns: name => [type, unsigned, nullable, default, length|null, identity]
     *
     * @return array
     */
    private static function expectedColumns(): array
    {
        $uInt = static fn (?string $default = '0') => ['int', true, false, $default, null, false];
        return [
            'tag' => [
                'tag_id' => ['int', true, false, null, null, true],
                'name' => ['varchar', false, true, null, '255', false],
                'status' => ['smallint', false, false, '0', null, false],
                'first_customer_id' => ['int', true, true, null, null, false],
                'first_store_id' => ['smallint', true, true, null, null, false],
            ],
            'tag_relation' => [
                'tag_relation_id' => ['int', true, false, null, null, true],
                'tag_id' => $uInt(),
                'customer_id' => ['int', true, true, null, null, false],
                'product_id' => $uInt(),
                'store_id' => ['smallint', true, false, '1', null, false],
                'active' => ['smallint', true, false, '1', null, false],
                'created_at' => ['timestamp', false, true, null, null, false],
            ],
            'tag_summary' => [
                'tag_id' => $uInt(),
                'store_id' => ['smallint', true, false, '0', null, false],
                'customers' => $uInt(),
                'products' => $uInt(),
                'uses' => $uInt(),
                'historical_uses' => $uInt(),
                'popularity' => $uInt(),
                'base_popularity' => $uInt(),
            ],
            'tag_properties' => [
                'tag_id' => $uInt(),
                'store_id' => ['smallint', true, false, '0', null, false],
                'base_popularity' => $uInt(),
            ],
        ];
    }

    /**
     * @return array
     */
    private static function expectedPrimaryKeys(): array
    {
        return [
            'tag' => ['tag_id'],
            'tag_relation' => ['tag_relation_id'],
            'tag_summary' => ['tag_id', 'store_id'],
            'tag_properties' => ['tag_id', 'store_id'],
        ];
    }

    /**
     * Expected foreign keys: table => [column => [referenceTable, referenceColumn, onDelete]]
     *
     * @return array
     */
    private static function expectedForeignKeys(): array
    {
        return [
            'tag' => [
                'first_customer_id' => ['customer_entity', 'entity_id', 'SET NULL'],
                'first_store_id' => ['store', 'store_id', 'SET NULL'],
            ],
            'tag_relation' => [
                'customer_id' => ['customer_entity', 'entity_id', 'CASCADE'],
                'product_id' => ['catalog_product_entity', 'entity_id', 'CASCADE'],
                'store_id' => ['store', 'store_id', 'CASCADE'],
                'tag_id' => ['tag', 'tag_id', 'CASCADE'],
            ],
            'tag_summary' => [
                'store_id' => ['store', 'store_id', 'CASCADE'],
                'tag_id' => ['tag', 'tag_id', 'CASCADE'],
            ],
            'tag_properties' => [
                'store_id' => ['store', 'store_id', 'CASCADE'],
                'tag_id' => ['tag', 'tag_id', 'CASCADE'],
            ],
        ];
    }

    /**
     * Expected (non-unique) indexes: table => list of column lists
     *
     * @return array
     */
    private static function expectedIndexes(): array
    {
        return [
            'tag' => [],
            'tag_relation' => [['product_id'], ['tag_id'], ['customer_id'], ['store_id']],
            'tag_summary' => [['store_id'], ['tag_id']],
            'tag_properties' => [['store_id']],
        ];
    }

    private function loadSchema(): \SimpleXMLElement
    {
        $path = dirname(__DIR__, 3) . '/etc/db_schema.xml';
        $this->assertFileExists($path);
        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml);
        return $xml;
    }

    /**
     * @param \SimpleXMLElement $schema
     * @param string $name
     * @return \SimpleXMLElement
     */
    private function getTable(\SimpleXMLElement $schema, string $name): \SimpleXMLElement
    {
        foreach ($schema->table as $table) {
            if ((string)$table['name'] === $name) {
                return $table;
            }
        }
        $this->fail("Table {$name} is not declared in db_schema.xml");
    }

    private function columnType(\SimpleXMLElement $node): string
    {
        return (string)$node->attributes(self::XSI)['type'];
    }

    public function testExactlyLegacyTablesAreDeclared(): void
    {
        $names = [];
        foreach ($this->loadSchema()->table as $table) {
            $names[] = (string)$table['name'];
        }
        $this->assertEqualsCanonicalizing(array_keys(self::expectedColumns()), $names);
    }

    public function testColumns(): void
    {
        $schema = $this->loadSchema();
        foreach (self::expectedColumns() as $tableName => $columns) {
            $table = $this->getTable($schema, $tableName);
            $actual = [];
            foreach ($table->column as $column) {
                $actual[(string)$column['name']] = $column;
            }
            $this->assertSame(array_keys($columns), array_keys($actual), "Columns of {$tableName}");

            foreach ($columns as $name => [$type, $unsigned, $nullable, $default, $length, $identity]) {
                $column = $actual[$name];
                $label = "{$tableName}.{$name}";
                $this->assertSame($type, $this->columnType($column), "{$label} type");
                $this->assertSame(
                    $unsigned,
                    isset($column['unsigned']) && (string)$column['unsigned'] === 'true',
                    "{$label} unsigned"
                );
                $this->assertSame(
                    $nullable,
                    !isset($column['nullable']) || (string)$column['nullable'] === 'true',
                    "{$label} nullable"
                );
                $this->assertSame(
                    $default,
                    isset($column['default']) ? (string)$column['default'] : null,
                    "{$label} default"
                );
                $this->assertSame(
                    $length,
                    isset($column['length']) ? (string)$column['length'] : null,
                    "{$label} length"
                );
                $this->assertSame(
                    $identity,
                    isset($column['identity']) && (string)$column['identity'] === 'true',
                    "{$label} identity"
                );
            }
        }
    }

    public function testPrimaryKeys(): void
    {
        $schema = $this->loadSchema();
        foreach (self::expectedPrimaryKeys() as $tableName => $columns) {
            $actual = [];
            foreach ($this->getTable($schema, $tableName)->constraint as $constraint) {
                if ($this->columnType($constraint) === 'primary') {
                    foreach ($constraint->column as $column) {
                        $actual[] = (string)$column['name'];
                    }
                }
            }
            $this->assertSame($columns, $actual, "Primary key of {$tableName}");
        }
    }

    public function testForeignKeys(): void
    {
        $schema = $this->loadSchema();
        foreach (self::expectedForeignKeys() as $tableName => $expected) {
            $actual = [];
            foreach ($this->getTable($schema, $tableName)->constraint as $constraint) {
                if ($this->columnType($constraint) === 'foreign') {
                    $this->assertSame($tableName, (string)$constraint['table']);
                    $actual[(string)$constraint['column']] = [
                        (string)$constraint['referenceTable'],
                        (string)$constraint['referenceColumn'],
                        (string)$constraint['onDelete'],
                    ];
                }
            }
            ksort($actual);
            ksort($expected);
            $this->assertSame($expected, $actual, "Foreign keys of {$tableName}");
        }
    }

    public function testRelationUniqueConstraint(): void
    {
        $table = $this->getTable($this->loadSchema(), 'tag_relation');
        $unique = [];
        foreach ($table->constraint as $constraint) {
            if ($this->columnType($constraint) === 'unique') {
                $columns = [];
                foreach ($constraint->column as $column) {
                    $columns[] = (string)$column['name'];
                }
                $unique[] = $columns;
            }
        }
        $this->assertSame([['tag_id', 'customer_id', 'product_id', 'store_id']], $unique);
    }

    public function testIndexes(): void
    {
        $schema = $this->loadSchema();
        foreach (self::expectedIndexes() as $tableName => $expected) {
            $actual = [];
            foreach ($this->getTable($schema, $tableName)->index as $index) {
                $columns = [];
                foreach ($index->column as $column) {
                    $columns[] = (string)$column['name'];
                }
                $actual[] = $columns;
            }
            $this->assertEqualsCanonicalizing($expected, $actual, "Indexes of {$tableName}");
        }
    }

    public function testTablesUseInnoDb(): void
    {
        foreach ($this->loadSchema()->table as $table) {
            $this->assertSame('innodb', (string)$table['engine']);
        }
    }
}
