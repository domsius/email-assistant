<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:pdf,doc,docx,txt,md,csv,xls,xlsx,json',
            ],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'documents.required' => 'Please select at least one document to upload.',
            'documents.max' => 'You can upload a maximum of 10 documents at a time.',
            'documents.*.file' => 'Each upload must be a valid file.',
            'documents.*.max' => 'Each document must not exceed 10MB.',
            'documents.*.mimes' => 'Supported formats: PDF, DOC, DOCX, TXT, MD, CSV, XLS, XLSX, JSON.',
        ];
    }
}
