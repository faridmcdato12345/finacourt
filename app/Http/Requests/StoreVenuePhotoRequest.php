<?php

namespace App\Http\Requests;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVenuePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && $this->user()?->can('update', $venue) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=8000,max_height=8000',
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Venue $venue */
            $venue = $this->route('venue');
            $incomingCount = count($this->file('photos', []));

            if ($venue->photos()->count() + $incomingCount > 10) {
                $validator->errors()->add('photos', 'A venue can have up to 10 photos.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photos.*.image' => 'Each upload must be a valid image.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, or WebP files.',
            'photos.*.max' => 'Each photo may be up to 5 MB.',
            'photos.*.dimensions' => 'Photos may be at most 8000 × 8000 pixels.',
        ];
    }
}
