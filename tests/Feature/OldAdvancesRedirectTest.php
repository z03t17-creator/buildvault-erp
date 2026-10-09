<?php

namespace Tests\Feature;

use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OldAdvancesRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_client_and_staff_advance_urls_redirect_to_vault_forms(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            \Database\Seeders\DemoUsersSeeder::class,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get('/client-advances')
            ->assertRedirect('/vault/lines/advance');
        $this->get('/client-advances/create')
            ->assertRedirect('/vault/lines/advance');
        $this->get('/client-advances/1')
            ->assertRedirect(route('vault.lines.advance.create'));

        $this->get('/advances')
            ->assertRedirect('/vault/lines/job-pay');
        $this->get('/advances/create')
            ->assertRedirect('/vault/lines/job-pay');
        $this->get('/advances/1')
            ->assertRedirect(route('vault.lines.job-pay.create'));

        $this->get(route('vault.lines.advance.create'))->assertOk();
        $this->get(route('vault.lines.job-pay.create'))->assertOk();
        $this->get(route('vault.lines.salary.create'))->assertOk();
    }

    public function test_legacy_vault_transactions_ledger_redirects_to_simple_vault(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            \Database\Seeders\DemoUsersSeeder::class,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get('/vault/transactions')->assertRedirect('/dashboards/vault');
        $this->get('/vault')->assertRedirect('/dashboards/vault');
        $this->get(route('vault.transactions.create'))->assertRedirect('/dashboards/vault');
        $this->get(route('dashboards.vault'))->assertOk();

        $vaultSource = file_get_contents(resource_path('js/Pages/Dashboards/Vault.jsx'));
        $this->assertStringNotContainsString('vault.transactions', $vaultSource);
        $this->assertStringNotContainsString('vault_ledger', $vaultSource);
    }

    public function test_vault_form_labels_use_requested_kurdish_names(): void
    {
        $ckb = json_decode(file_get_contents(lang_path('ckb.json')), true);
        $this->assertSame('سلفە', $ckb['vault_form_advance']);
        $this->assertSame('پارەی ستاف', $ckb['vault_form_job_pay']);
        $this->assertStringContainsString('مووچە', $ckb['vault_form_salary']);
        $this->assertNotSame($ckb['vault_form_advance'], $ckb['vault_form_salary']);

        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $this->assertSame('سلفە', $en['vault_form_advance']);
        $this->assertSame('پارەی ستاف', $en['vault_form_job_pay']);

        $ar = json_decode(file_get_contents(lang_path('ar.json')), true);
        $this->assertSame('سلفە', $ar['vault_form_advance']);
        $this->assertSame('پارەی ستاف', $ar['vault_form_job_pay']);
    }
}
