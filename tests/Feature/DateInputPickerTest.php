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

        // Component is client-side; assert the built JS module ships the sheet picker.
        $this->assertFileExists(resource_path('js/Components/DateInput.jsx'));
        $source = file_get_contents(resource_path('js/Components/DateInput.jsx'));
        $this->assertStringNotContainsString('type="date"', $source);
        $this->assertStringContainsString('data-date-input', $source);
        $this->assertStringContainsString('createPortal', $source);
        $this->assertStringContainsString('date_pick_title', $source);
        $this->assertStringContainsString('bvSheetIn', $source);
        $this->assertStringContainsString('showPicker', $source);
        // RTL month nav: flex mirrors prev/next; icons rotate so tips point the travel direction.
        $this->assertStringContainsString('rtl:rotate-180', $source);
        $this->assertStringContainsString("name=\"chevronLeft\"", $source);
        $this->assertStringContainsString("name=\"chevronRight\"", $source);

        // Shared civil-calendar util — never trust Date#toISOString for day identity.
        $this->assertFileExists(resource_path('js/lib/isoDate.js'));
        $isoUtil = file_get_contents(resource_path('js/lib/isoDate.js'));
        $this->assertStringContainsString('export function isValidIsoDate', $isoUtil);
        $this->assertStringContainsString('dt.getFullYear()', $isoUtil);
        $this->assertStringNotContainsString(
            'toISOString().slice(0, 10) === value',
            $isoUtil,
        );

        $projectForm = file_get_contents(resource_path('js/Pages/Projects/ProjectForm.jsx'));
        $this->assertStringContainsString("from '@/lib/isoDate'", $projectForm);
        $this->assertStringNotContainsString(
            'toISOString().slice(0, 10) === value',
            $projectForm,
        );
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
        $this->get(route('vault.transactions.create', ['direction' => 'in']))
            ->assertRedirect('/dashboards/vault');
        $this->get(route('vault.lines.advance.create'))->assertOk();
        $this->get(route('client-advances.create'))
            ->assertRedirect(route('vault.lines.advance.create'));
        $this->get(route('advances.create'))
            ->assertRedirect(route('vault.lines.job-pay.create'));
    }

    public function test_date_picker_translations_cover_sheet_labels(): void
    {
        foreach (['en', 'ckb', 'ar'] as $locale) {
            $path = lang_path($locale.'.json');
            $this->assertFileExists($path);
            $json = json_decode(file_get_contents($path), true);
            $this->assertIsArray($json);
            foreach ([
                'date_pick_title',
                'date_month',
                'date_year',
                'date_today',
                'date_clear',
                'date_prev_month',
                'date_next_month',
                'date_placeholder',
                'done',
            ] as $key) {
                $this->assertArrayHasKey($key, $json, "Missing {$key} in {$locale}");
                $this->assertNotSame('', $json[$key]);
            }
            $this->assertStringNotContainsString(
                'YYYY-MM-DD',
                $json['validation_date_required'],
                "{$locale} validation should not tell users to type YYYY-MM-DD",
            );
        }
    }
}
