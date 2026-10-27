<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response when IP is allowed', function () {
    Setting::setValue('allowed_ips', '127.0.0.1');
    $this->get('/')->assertOk();
});
