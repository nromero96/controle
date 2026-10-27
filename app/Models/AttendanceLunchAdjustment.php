<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLunchAdjustment extends Model
{
    protected $fillable = ['lunch_start', 'lunch_end', 'reason', 'recorded_by', 'source_fingerprint', 'previous_overtime_review'];

    protected function casts(): array
    {
        return ['previous_overtime_review' => 'array'];
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
