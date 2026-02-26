<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueryUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation, sanitizing text inputs.
     */
    protected function prepareForValidation()
    {
        $this->merge($this->sanitizeData($this->all()));
    }

    private function sanitizeData(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            } elseif (is_string($value)) {
                $data[$key] = trim(strip_tags($value));
            }
        }
        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:verify,refund,list_payment_methods,charge_payment',
            'apiKey' => ['nullable', 'string', 'regex:/^sk_(test|live)_[a-zA-Z0-9]+$/'],
            'chargeId' => ['nullable', 'string', 'regex:/^(pi|cs|pay)_[a-zA-Z0-9]+$/'],
            'transactionId' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
        ];
    }
}
