<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem\Test\Models;

use SimplePhpModelSystem\Database;
use SimplePhpModelSystem\Tests\DatabaseAbstractTestCase;

class DatabaseTest extends DatabaseAbstractTestCase
{

    public function testGetDbName(): void
    {
        $this->assertNotEmpty(Database::getInstance()->getDbName());
    }

}
