<?php

namespace App\Http\Requests;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManage();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_warranty' => $this->boolean('has_warranty'),
            'food_contact' => $this->boolean('food_contact'),
            'is_critical' => $this->boolean('is_critical'),
            'hygienic_design' => $this->boolean('hygienic_design'),
            'is_measuring_device' => $this->boolean('is_measuring_device'),
        ]);
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
            'food_contact' => ['boolean'],
            'is_critical' => ['boolean'],
            'ccp_reference' => ['nullable', 'string', 'max:100'],
            'hygienic_design' => ['boolean'],
            'is_measuring_device' => ['boolean'],
            'calibration_interval_days' => ['nullable', 'exclude_unless:is_measuring_device,true', 'integer', 'min:1', 'max:3650'],
            'last_calibration_date' => ['nullable', 'exclude_unless:is_measuring_device,true', 'date'],
            'next_calibration_date' => ['nullable', 'exclude_unless:is_measuring_device,true', 'date'],
            'calibration_provider' => ['nullable', 'exclude_unless:is_measuring_device,true', 'string', 'max:200'],
            'calibration_certificate' => $file,
            'purchase_spec' => $file,
            'conformity_doc' => $file,
        ];
    }

    /** Food-safety-relevant equipment may only be "Working" once a trial run has been signed off. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $equipment = $this->route('equipment');
                $commissioned = $equipment instanceof Equipment && $equipment->commissioned_at !== null;
                $relevant = $this->boolean('food_contact') || $this->boolean('is_critical') || filled($this->input('ccp_reference'));
                if (! $commissioned && $relevant && $this->input('status') === EquipmentStatus::Working->value) {
                    $validator->errors()->add('status', __('Error_CommissioningRequired'));
                }
            },
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
        $data = collect($this->validated())->except(['photo', 'manual', 'warranty_doc', 'calibration_certificate', 'purchase_spec', 'conformity_doc'])->all();
        if (! $data['has_warranty']) {
            $data = [...$data, 'warranty_start' => null, 'warranty_end' => null, 'warranty_provider' => null,
                'warranty_number' => null, 'warranty_document_url' => null];
        }
        if (! $data['is_measuring_device']) {
            $data = [...$data, 'calibration_interval_days' => null, 'last_calibration_date' => null,
                'next_calibration_date' => null, 'calibration_provider' => null];
        } elseif (empty($data['next_calibration_date']) && ! empty($data['last_calibration_date']) && ! empty($data['calibration_interval_days'])) {
            $data['next_calibration_date'] = Carbon::parse($data['last_calibration_date'])->addDays((int) $data['calibration_interval_days'])->toDateString();
        }

        return $data;
    }
}
