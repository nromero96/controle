<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'document_type',
        'document_number',
        'first_name',
        'last_name_father',
        'last_name_mother',
        'email',
        'status',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
