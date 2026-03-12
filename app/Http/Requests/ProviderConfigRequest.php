<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderConfigRequest extends FormRequest
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
            'location_id' => 'required_without:locationId|string|regex:/^[a-zA-Z0-9_-]+$/',
            'locationId' => 'required_without:location_id|string|regex:/^[a-zA-Z0-9_-]+$/',
            'live_secret_key' => 'nullable|string|starts_with:sk_live_',
            'live_publishable_key' => 'nullable|string|starts_with:pk_live_',
            'test_secret_key' => 'nullable|string|starts_with:sk_test_',
            'test_publishable_key' => 'nullable|string|starts_with:pk_test_',
        ];
    }
}
