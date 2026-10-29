<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceTimeCorrection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['previous_overtime_review' => 'array'];
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
