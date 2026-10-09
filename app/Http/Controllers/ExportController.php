<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exports,
    ) {}

    public function projectExcel(Project $project): BinaryFileResponse
    {
        $this->authorize('manageExports', Vault::class);
        $this->authorize('view', $project);

        return $this->exports->downloadProjectExcel($project);
    }

    public function workerPdf(Request $request, Worker $worker): Response
    {
        $this->authorize('manageExports', Vault::class);
        $this->authorize('view', $worker);

        $monthInput = (string) $request->query('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        return $this->exports->downloadWorkerPdf(
            $worker,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );
    }

    public function payoutVoucher(Payout $payout): Response
    {
        $this->authorize('manageExports', Vault::class);
        $this->authorize('view', $payout);

        return $this->exports->downloadPayoutVoucher($payout);
    }
}
