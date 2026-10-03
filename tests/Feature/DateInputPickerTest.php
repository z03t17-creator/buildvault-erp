<?php

namespace Tests\Feature;

use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DateInputPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_form_exposes_date_inputs_for_calendar_picker(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            \Database\Seeders\DemoUsersSeeder::class,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Create')
            );

        // Component is client-side; assert the built JS module ships DateInput.
        $this->assertFileExists(resource_path('js/Components/DateInput.jsx'));
        $source = file_get_contents(resource_path('js/Components/DateInput.jsx'));
        $this->assertStringContainsString("type=\"date\"", $source);
        $this->assertStringContainsString('showPicker', $source);
        $this->assertStringContainsString('data-date-input', $source);
    }

    public function test_expense_and_money_forms_render_for_authenticated_users(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            \Database\Seeders\DemoUsersSeeder::class,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('expenses.create'))->assertOk();
        $this->get(route('vault.transactions.create', ['direction' => 'in']))->assertOk();
        $this->get(route('client-advances.create'))->assertOk();
    }
}
