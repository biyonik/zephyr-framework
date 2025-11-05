<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Zephyr\Database\Connection;
use Zephyr\Database\QueryBuilder;

class QueryBuilderSecurityTest extends TestCase
{
    private QueryBuilder $query;

    protected function setUp(): void
    {
        // Veritabanına bağlanmadan, sadece SQL oluşturmak için mock connection
        $connection = $this->createMock(Connection::class);
        $this->query = new QueryBuilder($connection); //
        $this->query->from('users');
    }

    public function test_orderby_allows_valid_column_names()
    {
        $sql = $this->query->orderBy('name', 'ASC')->toSql();
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
        
        $sql = $this->query->orderBy('users.id', 'DESC')->toSql();
        $this->assertStringContainsString('ORDER BY users.id DESC', $sql);
    }

    public function test_orderby_throws_exception_on_invalid_direction()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->query->orderBy('name', 'INVALID'); // Yamamız bunu yakalamalı
    }

    public function test_orderby_throws_exception_on_sql_injection_attempt()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // Rapor #4'teki senaryo
        $this->query->orderBy('id; DROP TABLE users; --', 'ASC');
    }
    
    public function test_orderby_throws_exception_on_complex_injection()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->query->orderBy('name) (SELECT * FROM users)', 'ASC');
    }
}