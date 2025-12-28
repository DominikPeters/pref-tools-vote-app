<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Auth;

class UserTest extends TestCase
{
    public function test_user_has_default_role(): void
    {
        $user = $this->createUser();

        $this->assertEquals(User::ROLE_USER, $user->role);
        $this->assertFalse($user->isSysadmin());
    }

    public function test_sysadmin_has_sysadmin_role(): void
    {
        $user = $this->createSysadmin();

        $this->assertEquals(User::ROLE_SYSADMIN, $user->role);
        $this->assertTrue($user->isSysadmin());
    }

    public function test_role_is_persisted_to_database(): void
    {
        $user = $this->createSysadmin('sysadmin@test.com');

        // Fetch fresh from database
        $freshUser = User::find($user->id);

        $this->assertEquals(User::ROLE_SYSADMIN, $freshUser->role);
        $this->assertTrue($freshUser->isSysadmin());
    }

    public function test_role_is_included_in_to_array(): void
    {
        $user = $this->createSysadmin();
        $array = $user->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertEquals(User::ROLE_SYSADMIN, $array['role']);
    }

    public function test_can_update_user_role(): void
    {
        $user = $this->createUser();

        $this->assertFalse($user->isSysadmin());

        $user->updateRole(User::ROLE_SYSADMIN);

        $this->assertTrue($user->isSysadmin());

        // Verify persisted
        $freshUser = User::find($user->id);
        $this->assertTrue($freshUser->isSysadmin());
    }

    public function test_can_count_users(): void
    {
        $this->createUser('user1@test.com');
        $this->createUser('user2@test.com');
        $this->createSysadmin('admin@test.com');

        $this->assertEquals(3, User::count());
    }

    public function test_can_get_all_users(): void
    {
        $this->createUser('user1@test.com');
        $this->createUser('user2@test.com');
        $this->createSysadmin('admin@test.com');

        $users = User::all();

        $this->assertCount(3, $users);
        $this->assertInstanceOf(User::class, $users[0]);
    }

    public function test_can_get_users_with_pagination(): void
    {
        $this->createUser('user1@test.com');
        $this->createUser('user2@test.com');
        $this->createUser('user3@test.com');

        $page1 = User::all(2, 0);
        $page2 = User::all(2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }

    public function test_can_delete_user(): void
    {
        $user = $this->createUser();
        $userId = $user->id;

        $user->delete();

        $this->assertNull(User::find($userId));
    }

    public function test_find_by_email_works(): void
    {
        $user = $this->createSysadmin('admin@test.com');

        $found = User::findByEmail('admin@test.com');

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
        $this->assertTrue($found->isSysadmin());
    }

    public function test_role_constants_are_defined(): void
    {
        $this->assertEquals('user', User::ROLE_USER);
        $this->assertEquals('sysadmin', User::ROLE_SYSADMIN);
    }
}
