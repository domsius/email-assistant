<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user can access this email
        $email = $this->route('email');

        return $email && $email->emailAccount->company_id === $this->user()->company_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:50000'],
            'subject' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'body.required' => 'The email body is required.',
            'body.max' => 'The email body cannot exceed 50,000 characters.',
            'subject.max' => 'The email subject cannot exceed 500 characters.',
        ];
    }
}
