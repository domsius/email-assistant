<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncEmailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user owns this email account
        $emailAccount = $this->route('emailAccount');

        return $emailAccount && $emailAccount->company_id === $this->user()->company_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'after_date' => ['nullable', 'date', 'before:tomorrow'],
            'folder' => ['nullable', 'string', 'in:inbox,sent,drafts,spam,trash,all'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'limit.max' => 'Cannot sync more than 500 emails at once.',
            'after_date.before' => 'The date must be in the past.',
            'folder.in' => 'Invalid folder specified.',
        ];
    }
}
