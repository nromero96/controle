<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'employee_id',
        'day',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
