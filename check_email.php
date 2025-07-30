<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$email = \App\Models\EmailMessage::first();
if ($email) {
    echo 'Email ID: '.$email->id.PHP_EOL;
    echo 'Subject: '.$email->subject.PHP_EOL;
    echo 'Has HTML content: '.(! empty($email->body_html) ? 'Yes' : 'No').PHP_EOL;
    echo 'Has Plain content: '.(! empty($email->body_plain) ? 'Yes' : 'No').PHP_EOL;
    echo 'HTML content length: '.strlen($email->body_html ?? '').PHP_EOL;
    echo 'Plain content length: '.strlen($email->body_plain ?? '').PHP_EOL;

    if (! empty($email->body_html)) {
        echo 'First 200 chars of HTML: '.substr($email->body_html, 0, 200).'...'.PHP_EOL;
    }
} else {
    echo 'No email found'.PHP_EOL;
}
