<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Branch;

class AttendanceBranchOptions
{
    public static function all()
    {
        $branches = Branch::orderBy('name')->get()->keyBy('id');
        $historical = Attendance::where('branch_snapshot_captured', true)->whereNotNull('recorded_branch_id')
            ->select('recorded_branch_id', 'recorded_branch_name')->distinct()->get();
        foreach ($historical as $mark) {
            if (!$branches->has($mark->recorded_branch_id)) {
                $branches->put($mark->recorded_branch_id, (object) ['id' => $mark->recorded_branch_id, 'name' => ($mark->recorded_branch_name ?? 'Sede eliminada').' (histórica)']);
            }
        }
        return $branches->sortBy('name')->values();
    }
}
