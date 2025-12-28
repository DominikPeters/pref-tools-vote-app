<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Database;

class DatabaseTest extends TestCase
{
    public function test_can_get_database_instance(): void
    {
        $db = Database::getInstance();

        $this->assertInstanceOf(Database::class, $db);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $db1 = Database::getInstance();
        $db2 = Database::getInstance();

        $this->assertSame($db1, $db2);
    }

    public function test_is_sqlite(): void
    {
        $db = Database::getInstance();

        $this->assertTrue($db->isSqlite());
        $this->assertFalse($db->isMysql());
    }

    public function test_can_insert_and_fetch(): void
    {
        $db = Database::getInstance();

        $id = $db->insert('users', [
            'email' => 'test@example.com',
            'password_hash' => 'hashed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNotEmpty($id);

        $row = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);

        $this->assertNotNull($row);
        $this->assertEquals('test@example.com', $row['email']);
    }

    public function test_can_update(): void
    {
        $db = Database::getInstance();

        $id = $db->insert('users', [
            'email' => 'original@example.com',
            'password_hash' => 'hashed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $affected = $db->update(
            'users',
            ['email' => 'updated@example.com'],
            'id = :id',
            ['id' => $id]
        );

        $this->assertEquals(1, $affected);

        $row = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        $this->assertEquals('updated@example.com', $row['email']);
    }

    public function test_can_delete(): void
    {
        $db = Database::getInstance();

        $id = $db->insert('users', [
            'email' => 'todelete@example.com',
            'password_hash' => 'hashed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $affected = $db->delete('users', 'id = :id', ['id' => $id]);

        $this->assertEquals(1, $affected);

        $row = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        $this->assertNull($row);
    }

    public function test_fetch_all(): void
    {
        $db = Database::getInstance();

        // Insert multiple users
        for ($i = 1; $i <= 3; $i++) {
            $db->insert('users', [
                'email' => "user{$i}@example.com",
                'password_hash' => 'hashed',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $rows = $db->fetchAll('SELECT * FROM users ORDER BY id');

        $this->assertCount(3, $rows);
    }

    public function test_fetch_column(): void
    {
        $db = Database::getInstance();

        $db->insert('users', [
            'email' => 'count@example.com',
            'password_hash' => 'hashed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $count = $db->fetchColumn('SELECT COUNT(*) FROM users');

        $this->assertEquals(1, $count);
    }

    public function test_table_exists(): void
    {
        $db = Database::getInstance();

        $this->assertTrue($db->tableExists('users'));
        $this->assertTrue($db->tableExists('polls'));
        $this->assertFalse($db->tableExists('nonexistent_table'));
    }

    public function test_transactions(): void
    {
        $db = Database::getInstance();

        $db->beginTransaction();

        $id = $db->insert('users', [
            'email' => 'transaction@example.com',
            'password_hash' => 'hashed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->rollback();

        $row = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        $this->assertNull($row, 'Row should not exist after rollback');
    }
}
