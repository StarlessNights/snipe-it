<?php

namespace Tests\Feature\Departments\Ui;

use App\Models\User;
use Tests\TestCase;

class IndexDepartmentsTest extends TestCase
{
    public function testPermissionRequiredToViewDepartmentsList(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    public function testUserCanListDepartments(): void
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('departments.index'))
            ->assertOk();
    }
}
