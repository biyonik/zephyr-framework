<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Zephyr\Database\{Connection, QueryBuilder};

/**
 * QueryBuilder Tests
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class QueryBuilderTest extends TestCase
{
    protected QueryBuilder $query;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Connection::getInstance();
        $this->query = new QueryBuilder($connection);
    }

    public function testSelectAll(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->toSql();

        $this->assertSame('SELECT * FROM users', $sql);
    }

    public function testSelectColumns(): void
    {
        $sql = $this->query->select('id', 'name', 'email')
            ->from('users')
            ->toSql();

        $this->assertSame('SELECT id, name, email FROM users', $sql);
    }

    public function testWhereClause(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->where('status', '=', 'active')
            ->toSql();

        $this->assertSame('SELECT * FROM users WHERE status = ?', $sql);
        $this->assertSame(['active'], $this->query->getBindings());
    }

    public function testMultipleWhere(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->where('status', '=', 'active')
            ->where('age', '>', 18)
            ->toSql();

        $this->assertStringContainsString('WHERE status = ? AND age > ?', $sql);
        $this->assertSame(['active', 18], $this->query->getBindings());
    }

    public function testWhereIn(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->whereIn('id', [1, 2, 3])
            ->toSql();

        $this->assertStringContainsString('WHERE id IN (?, ?, ?)', $sql);
        $this->assertSame([1, 2, 3], $this->query->getBindings());
    }

    public function testJoin(): void
    {
        $sql = $this->query->select('users.*', 'orders.total')
            ->from('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->toSql();

        $this->assertStringContainsString('INNER JOIN orders ON users.id = orders.user_id', $sql);
    }

    public function testOrderBy(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testLimit(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testOffset(): void
    {
        $sql = $this->query->select('*')
            ->from('users')
            ->limit(10)
            ->offset(20)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10 OFFSET 20', $sql);
    }

    public function testComplexQuery(): void
    {
        $sql = $this->query
            ->select('users.name', 'COUNT(orders.id) as order_count')
            ->from('users')
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->where('users.status', '=', 'active')
            ->groupBy('users.id')
            ->having('COUNT(orders.id)', '>', 5)
            ->orderBy('order_count', 'DESC')
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }
}