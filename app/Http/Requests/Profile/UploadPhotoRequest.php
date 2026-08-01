<?php

namespace App\Http\Requests\Profile;

use App\Models\Photo;
use App\Services\Photos\PhotoStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->profile !== null;
    }

    public function rules(): array
    {
        return [
            // `image` and `mimes` both read the real file, not the name it
            // arrived under. The service re-checks the bytes before writing
            // anything — two independent checks, because this is the one
            // endpoint that accepts arbitrary input from the public.
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:'.(PhotoStorageService::MAX_BYTES / 1024),
            ],
            'slot' => ['required', Rule::in(array_keys(Photo::SLOTS))],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => 'That photo is larger than 8 MB. A smaller one will upload faster too.',
            'photo.image' => 'That file does not look like a photo. JPEG, PNG or WebP, please.',
            'photo.mimes' => 'That file does not look like a photo. JPEG, PNG or WebP, please.',
        ];
    }
}
