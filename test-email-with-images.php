<?php

use App\Models\EmailMessage;
use App\Models\EmailAccount;
use App\Models\User;

// This script creates a test email with images to verify image display

$user = User::first();
$emailAccount = EmailAccount::where('company_id', $user->company_id)->first();

if (!$emailAccount) {
    echo "No email account found for user. Please connect an email account first.\n";
    exit(1);
}

$htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; }
        .header-image { width: 100%; max-width: 600px; height: auto; }
        .product-image { width: 200px; height: 200px; object-fit: cover; margin: 10px; }
        .logo { width: 150px; height: auto; }
    </style>
</head>
<body>
    <div class="email-container">
        <h1>Test Email with Images</h1>
        
        <p>This is a test email to verify that images are displayed correctly.</p>
        
        <h2>Remote Images</h2>
        <img src="https://via.placeholder.com/600x200/007bff/ffffff?text=Header+Image" alt="Header Image" class="header-image">
        
        <h2>Logo Example</h2>
        <img src="https://via.placeholder.com/150x50/28a745/ffffff?text=Logo" alt="Company Logo" class="logo">
        
        <h2>Product Images</h2>
        <div style="display: flex; flex-wrap: wrap;">
            <img src="https://via.placeholder.com/200x200/dc3545/ffffff?text=Product+1" alt="Product 1" class="product-image">
            <img src="https://via.placeholder.com/200x200/ffc107/ffffff?text=Product+2" alt="Product 2" class="product-image">
            <img src="https://via.placeholder.com/200x200/17a2b8/ffffff?text=Product+3" alt="Product 3" class="product-image">
        </div>
        
        <h2>Inline Base64 Image</h2>
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==" alt="1x1 Red Pixel" style="width: 50px; height: 50px; background-color: red;">
        
        <h2>Mixed Content</h2>
        <p>Here's some text with an inline image: <img src="https://via.placeholder.com/20x20/6c757d/ffffff?text=i" alt="info icon" style="vertical-align: middle;"> showing how images can be embedded within text.</p>
        
        <p>Thank you for testing the email image display functionality!</p>
    </div>
</body>
</html>
HTML;

$email = EmailMessage::create([
    'email_account_id' => $emailAccount->id,
    'message_id' => 'test-image-email-' . time() . '@example.com',
    'thread_id' => 'test-thread-' . time(),
    'subject' => 'Test Email with Images - ' . now()->format('Y-m-d H:i:s'),
    'from_email' => 'test@example.com',
    'sender_name' => 'Image Test Sender',
    'to_recipients' => [auth()->user()->email],
    'body_html' => $htmlContent,
    'body_plain' => strip_tags($htmlContent),
    'received_at' => now(),
    'is_read' => false,
    'is_starred' => false,
    'folder' => 'inbox',
]);

echo "Test email with images created successfully!\n";
echo "Email ID: {$email->id}\n";
echo "Subject: {$email->subject}\n";
echo "Please check your inbox to verify that images are displayed correctly.\n";