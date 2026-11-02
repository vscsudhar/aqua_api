<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit') ?? $this->route('id');
        if ($unitId instanceof \App\Models\Unit) {
            $unitId = $unitId->id;
        }

        $user = $this->user();
        $companyId = $user?->company_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'code')
                    ->where(function ($query) use ($companyId) {
                        return $companyId ? $query->where('company_id', $companyId) : $query;
                    })
                    ->ignore($unitId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
