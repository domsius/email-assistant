<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectEmailAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:gmail,outlook,yahoo,custom'],
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            // For custom IMAP/SMTP providers
            'imap_host' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'imap_port' => ['required_if:provider,custom', 'nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'imap_password' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'imap_encryption' => ['nullable', 'string', 'in:ssl,tls,none'],
            'smtp_host' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['required_if:provider,custom', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'smtp_password' => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:ssl,tls,none'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Please select an email provider.',
            'provider.in' => 'Invalid email provider selected.',
            'email.email' => 'Please provide a valid email address.',
            'imap_host.required_if' => 'IMAP host is required for custom providers.',
            'imap_port.required_if' => 'IMAP port is required for custom providers.',
            'smtp_host.required_if' => 'SMTP host is required for custom providers.',
            'smtp_port.required_if' => 'SMTP port is required for custom providers.',
        ];
    }
}
