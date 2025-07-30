<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GenerateResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'tone' => ['nullable', 'string', 'in:professional,friendly,formal,casual,empathetic'],
            'style' => ['nullable', 'string', 'in:concise,detailed,conversational'],
            'max_length' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'include_signature' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'size:2'], // ISO 639-1 code
            'custom_instructions' => ['nullable', 'string', 'max:1000'],
            'regenerate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'tone.in' => 'Invalid tone selected.',
            'style.in' => 'Invalid writing style selected.',
            'max_length.min' => 'Maximum length must be at least 50 characters.',
            'max_length.max' => 'Maximum length cannot exceed 5000 characters.',
            'language.size' => 'Language must be a valid 2-letter ISO code.',
            'custom_instructions.max' => 'Custom instructions cannot exceed 1000 characters.',
        ];
    }
}
