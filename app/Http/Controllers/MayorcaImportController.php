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

        $path = $this->resolveWorkbookPath();
        if (! is_file($path)) {
            return back()->with('error', __('mayorca_workbook_missing'));
        }

        $wiper->wipe(dryRun: false);
        $result = $importer->import($path, dryRun: false);

        $people = (int) ($result['people'] ?? 0);
        $warnings = count($result['warnings'] ?? []);

        return back()->with(
            'success',
            __('mayorca_import_success', [
                'people' => $people,
                'warnings' => $warnings,
            ])
        );
    }

    protected function resolveWorkbookPath(): string
    {
        $path = MayorcaWorkbookImportService::sampleAbsolutePath();
        if (is_file($path)) {
            return $path;
        }

        $bundled = base_path('resources/imports/samples/hsabati-mayorca-zhako.xlsx');
        if (is_file($bundled)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            copy($bundled, $path);

            return $path;
        }

        return $path;
    }
}
