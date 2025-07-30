<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeEmailRequest extends FormRequest
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
            'analysis_types' => ['nullable', 'array'],
            'analysis_types.*' => ['string', 'in:sentiment,urgency,topic,language,intent,entities'],
            'reanalyze' => ['nullable', 'boolean'],
            'deep_analysis' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'analysis_types.*.in' => 'Invalid analysis type specified.',
        ];
    }
}
