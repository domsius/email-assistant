<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

// Get the latest email
$email = \App\Models\EmailMessage::latest()->first();

if ($email) {
    echo 'Email ID: '.$email->id."\n";
    echo 'Subject: '.$email->subject."\n";
    echo 'From: '.$email->from_email."\n";
    echo 'Body HTML length: '.strlen($email->body_html ?? '')."\n";
    echo 'Body Plain length: '.strlen($email->body_plain ?? '')."\n";
} else {
    echo "No emails found\n";
}
