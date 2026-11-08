<?php
namespace App\Jobs;

use App\Exports\WorkspaceReportExport;
use App\Models\User;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceReports;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/** Builds a requested report off the request thread. Authorization is re-checked at generation time. */
class GenerateWorkspaceExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const LIFETIME_HOURS = 24;
    public $tries = 2; public $backoff = [60]; public $timeout = 300;

    public function __construct(public int $exportId) { $this->afterCommit(); $this->onConnection('workspace'); $this->onQueue('workspace'); }

    public function handle(WorkspaceAccess $access, WorkspaceReports $reports): void
    {
        $export = DB::table('workspace_exports')->find($this->exportId);
        if (!$export || $export->status !== 'queued') { return; }
        $fail = fn (string $error) => DB::table('workspace_exports')->where('id', $export->id)->update(['status' => 'failed', 'error' => $error, 'updated_at' => now()]);
        $user = User::find($export->user_id);
        try {
            $context = $access->resolve($user, $export->context);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $fail('This workspace is no longer available to you.'); return;
        }
        if (!$access->can($context, 'reports.export')) { $fail('You no longer have permission to export reports.'); return; }
        $filters = json_decode($export->filters, true);
        try {
            $report = $reports->build($export->type, $context, CarbonImmutable::parse($filters['from']), CarbonImmutable::parse($filters['to']),
                $filters['school_id'] ?? null, $filters['role'] ?? null, $export->format === 'pdf' ? WorkspaceReports::MAX_ROWS : WorkspaceReports::EXPORT_MAX_ROWS);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $fail('This report is no longer available to you.'); return;
        }
        $path = 'exports/'.$export->id.'-'.bin2hex(random_bytes(8)).'.'.$export->format;
        $rows = array_map(fn ($row) => array_map([WorkspaceReports::class, 'safe'], $row), $report['rows']);
        if ($export->format === 'pdf') {
            Storage::disk('private')->put($path, Pdf::loadView('workspace.report-pdf', ['report' => $report, 'context' => $context,
                'from' => CarbonImmutable::parse($filters['from']), 'to' => CarbonImmutable::parse($filters['to'])])
                ->setPaper('a4', count($report['columns']) > 5 ? 'landscape' : 'portrait')->output());
        } elseif ($export->format === 'xlsx') {
            Excel::store(new WorkspaceReportExport($report['columns'], $rows), $path, 'private', \Maatwebsite\Excel\Excel::XLSX);
        } else {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $report['columns']);
            foreach ($rows as $row) { fputcsv($out, $row); }
            rewind($out);
            Storage::disk('private')->put($path, stream_get_contents($out));
            fclose($out);
        }
        DB::table('workspace_exports')->where('id', $export->id)->update(['status' => 'ready', 'path' => $path, 'rows' => count($rows),
            'expires_at' => now()->addHours(self::LIFETIME_HOURS), 'updated_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('workspace_exports')->where('id', $this->exportId)->where('status', 'queued')
            ->update(['status' => 'failed', 'error' => 'The report could not be generated. Try again.', 'updated_at' => now()]);
    }
}
