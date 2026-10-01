<?php

namespace App\Http\Requests;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManage();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['has_warranty' => $this->boolean('has_warranty')]);
    }

    public function rules(): array
    {
        $file = ['nullable', 'file', 'max:'.config('pms.upload_max_kb'), 'extensions:'.implode(',', config('pms.upload_extensions'))];

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('equipment', 'code')->ignore($this->route('equipment'))],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(EquipmentCategory::class)],
            'department_id' => ['required', Rule::exists('departments', 'id')],
            'location' => ['nullable', 'string', 'max:200'],
            'status' => ['required', Rule::enum(EquipmentStatus::class)],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:200'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'has_warranty' => ['boolean'],
            'warranty_start' => ['nullable', 'exclude_unless:has_warranty,true', 'date'],
            'warranty_end' => ['exclude_unless:has_warranty,true', 'required', 'date', 'after_or_equal:warranty_start'],
            'warranty_provider' => ['nullable', 'exclude_unless:has_warranty,true', 'string', 'max:200'],
            'warranty_number' => ['nullable', 'exclude_unless:has_warranty,true', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'photo' => ['nullable', 'image', 'max:'.config('pms.upload_max_kb')],
            'manual' => $file,
            'warranty_doc' => $file,
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => __('Error_CodeExists'),
            'warranty_end.required' => __('Error_WarrantyEndRequired'),
            'warranty_end.after_or_equal' => __('Error_WarrantyDates'),
        ];
    }

    /** @return array<string, mixed> */
    public function equipmentData(): array
    {
        $data = collect($this->validated())->except(['photo', 'manual', 'warranty_doc'])->all();
        if (! $data['has_warranty']) {
            $data = [...$data, 'warranty_start' => null, 'warranty_end' => null, 'warranty_provider' => null,
                'warranty_number' => null, 'warranty_document_url' => null];
        }

        return $data;
    }
}
