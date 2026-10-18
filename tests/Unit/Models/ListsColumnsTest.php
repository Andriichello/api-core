<?php

namespace Core\Tests\Unit\Models;

use Core\Models\User;
use Core\Tests\TestCase;

/**
 * Class ListsColumnsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class ListsColumnsTest extends TestCase
{
    /**
     * Test that the list of columns includes the hidden ones, such as the password.
     *
     * @return void
     */
    public function testListOfColumnsIncludesHiddenColumns(): void
    {
        $columns = (new User)->listOfColumns();

        $this->assertContains('email', $columns);
        $this->assertContains('password', $columns);
    }

    /**
     * Test that the list of queryable columns leaves out the hidden ones, such as the password, and keeps its keys
     * sequential.
     *
     * @return void
     */
    public function testListOfQueryableColumnsExcludesHiddenColumns(): void
    {
        $columns = (new User)->listOfQueryableColumns();

        $this->assertContains('id', $columns);
        $this->assertContains('email', $columns);
        $this->assertNotContains('password', $columns);
        $this->assertSame(array_values($columns), $columns);
    }
}
