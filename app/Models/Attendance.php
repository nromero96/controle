<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'mark_type',
        'mark_date',
        'mark_time',
        'ip_address',
        'device',
        'photo',
    ];

    protected function casts(): array
    {
        return ['overtime_reviewed_at' => 'datetime', 'is_unscheduled_day' => 'boolean', 'branch_snapshot_captured' => 'boolean'];
    }

    public function recordedBranchLabel(): string
    {
        if (!$this->branch_snapshot_captured) return 'Sin sede histórica registrada';
        return $this->recorded_branch_name ?? 'Sin sede asignada';
    }

    public function effectiveTime(): ?string
    {
        return $this->corrected_time ?? $this->mark_time;
    }

    public function timeCorrections()
    {
        return $this->hasMany(AttendanceTimeCorrection::class)->orderByDesc('id');
    }

    public function lunchAdjustments()
    {
        return $this->hasMany(AttendanceLunchAdjustment::class)->orderByDesc('id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'overtime_reviewed_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
