<?php

namespace App\Http\Controllers;

use App\Services\BusinessDataWipeService;
use App\Services\MayorcaWorkbookImportService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MayorcaImportController extends Controller
{
    public function store(
        Request $request,
        BusinessDataWipeService $wiper,
        MayorcaWorkbookImportService $importer,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user && $user->hasRole(Roles::SUPER_ADMIN), 403);

        $request->validate([
            'confirm_wipe' => ['required', 'accepted'],
        ]);

        $path = MayorcaWorkbookImportService::ensureBundledSampleSynced();
        if (! is_file($path)) {
            return back()->with('error', __('mayorca_workbook_missing'));
        }

        $wiper->wipe(dryRun: false);
        $result = $importer->import($path, dryRun: false);

        $people = (int) ($result['people'] ?? 0);
        $warnings = count($result['warnings'] ?? []);
        $expenses = (int) ($result['counts']['expenses'] ?? 0);

        return back()->with(
            'success',
            __('mayorca_import_success', [
                'people' => $people,
                'warnings' => $warnings,
            ]).' (expenses: '.$expenses.', workbook: '.filesize($path).' B)'
        );
    }
}
