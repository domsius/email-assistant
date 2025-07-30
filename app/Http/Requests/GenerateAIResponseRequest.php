<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAIResponseRequest extends FormRequest
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
            'tone' => ['nullable', 'string', 'in:professional,friendly,formal,casual'],
            'length' => ['nullable', 'string', 'in:short,medium,long'],
            'include_context' => ['nullable', 'boolean'],
            'additional_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'tone.in' => 'The tone must be one of: professional, friendly, formal, casual.',
            'length.in' => 'The length must be one of: short, medium, long.',
            'additional_instructions.max' => 'Additional instructions cannot exceed 1000 characters.',
        ];
    }
}
