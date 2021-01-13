<?php

namespace TheCodingMachine\TDBM\Bundle\Utils;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;

/**
 * A stub for schema manager that simply returns the schema we are providing.
 */
class StubSchemaManager extends AbstractSchemaManager
{
    /**
     * @var Schema
     */
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Creates a schema instance for the current database.
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function createSchema()
    {
        return $this->schema;
    }

    /**
     * Gets Table Column Definition.
     *
     * @param mixed[] $tableColumn
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    protected function _getPortableTableColumnDefinition($tableColumn): Column
    {
        throw new \RuntimeException('Not implemented');
    }
}
