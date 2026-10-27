<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Services\AttendanceReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AttendanceReportController extends Controller
{
    public function index(Request $request, AttendanceReport $report)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $to = $filters['to'] ?? now()->toDateString();
        $from = $filters['from'] ?? Carbon::parse($to)->startOfMonth()->toDateString();
        if ($from > $to || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 365) {
            throw ValidationException::withMessages(['to' => 'Seleccione un rango válido de hasta 366 días.']);
        }
        $employees = Employee::with(['schedules', 'branch'])
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where(function ($query) use ($id, $from, $to) {
                $query->where('branch_id', $id)->orWhereHas('attendances', fn ($marks) => $marks->whereBetween('mark_date', [$from, $to])->where('branch_snapshot_captured', true)->where('recorded_branch_id', $id));
            }))
            ->orderBy('first_name')->get();
        $result = $report->build($employees, $from, $to, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $selectedEmployee = !empty($filters['employee_id']);
        $chartPoints = $selectedEmployee
            ? $result['rows']->sortBy('date')->values()->map(fn ($row) => [
                'label' => Carbon::parse($row['date'])->format('d/m/Y'),
                'scheduled' => $row['planned'], 'worked' => $row['complete'] ? $row['worked'] : null,
                'pending' => !$row['complete'] ? null : ($row['status'] === 'pending' ? $row['eligible'] : 0),
                'approved' => $row['complete'] ? $row['approved'] : null,
                'rejected' => !$row['complete'] ? null : ($row['status'] === 'rejected' ? $row['eligible'] : 0),
                'note' => $row['complete'] ? ($row['schedule_estimated'] ? 'Programación estimada.' : '') : $row['reason'],
            ])
            : $result['summaries']->map(fn ($summary) => [
                'label' => $summary['employee']->first_name.' '.$summary['employee']->last_name_father.' · '.$summary['employee']->document_number,
                'scheduled' => $summary['unknown_schedule_days'] && !$summary['scheduled'] ? null : $summary['scheduled'], 'worked' => $summary['complete_days'] ? $summary['worked'] : null,
                'pending' => $summary['complete_days'] ? $summary['pending'] : null, 'approved' => $summary['complete_days'] ? $summary['approved'] : null, 'rejected' => $summary['complete_days'] ? $summary['rejected'] : null,
                'note' => $summary['incomplete_days'].' jornadas con incidencia; '.$summary['unmarked_days'].' sin marcas; '.$summary['estimated_days'].' con programación estimada.',
            ])->values();
        $page = max(1, (int) $request->query('page', 1));
        $days = new LengthAwarePaginator($result['rows']->forPage($page, 25)->values(), $result['rows']->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);
        return view('pages.reports.index', [
            'data' => ['page_title' => 'Reportes de asistencia'],
            'rows' => $days->getCollection(), 'days' => $days, 'summaries' => $result['summaries'],
            'from' => $from, 'to' => $to,
            'chartData' => ['mode' => $selectedEmployee ? 'daily' : 'employees', 'points' => $chartPoints],
            'selectedEmployee' => $selectedEmployee ? Employee::find($filters['employee_id']) : null,
            'employees' => Employee::orderBy('first_name')->get(),
            'branches' => \App\Services\AttendanceBranchOptions::all(),
        ]);
    }
}
