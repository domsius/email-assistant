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
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md,csv,json|max:20480', // 20MB
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'The document must be a PDF, Word document, text file, Markdown, CSV, or JSON file.',
            'file.max' => 'The document must not be larger than 20MB.',
        ];
    }
}
