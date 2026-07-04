<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;

trait CreatesUsers
{
    protected function createAdmin(): User
    {
        return $this->createUserWithRole('admin');
    }

    protected function createTeacher(): User
    {
        return $this->createUserWithRole('teacher');
    }

    protected function createStudent(): User
    {
        return $this->createUserWithRole('student');
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        $user = User::factory()->create([
            'role_id'   => $role->id,
            'is_active' => true,
        ]);

        return $user;
    }
}
