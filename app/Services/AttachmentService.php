<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public function store(Model $model, UploadedFile $file, ?int $createdBy = null, ?string $directory = null): Attachment
    {
        $folder = $directory ?: $this->directoryFor($model);
        $path = $file->store($folder, 'public');

        return $model->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 180),
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'created_by' => $createdBy,
        ]);
    }

    public function storeOptional(Model $model, ?UploadedFile $file, ?int $createdBy = null, ?string $directory = null): ?Attachment
    {
        if (! $file) {
            return null;
        }

        return $this->store($model, $file, $createdBy, $directory);
    }

    public function deleteFor(Model $model, ?int $actorId = null): void
    {
        $model->loadMissing('attachments');

        foreach ($model->attachments as $attachment) {
            $this->delete($attachment, $actorId);
        }
    }

    public function delete(Attachment $attachment, ?int $actorId = null): void
    {
        $attachment->delete();

        Log::info('Attachment deleted.', [
            'attachment_id' => $attachment->id,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
            'path' => $attachment->path,
            'user_id' => $actorId,
        ]);
    }

    public function inlineLatest(Model $model): StreamedResponse
    {
        $model->loadMissing('latestAttachment');
        $attachment = $model->latestAttachment;

        abort_unless($attachment instanceof Attachment, 404);

        return $this->inline($attachment);
    }

    public function inline(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk ?: 'public');

        abort_unless($attachment->path && $disk->exists($attachment->path), 404);

        $downloadName = $attachment->original_name ?: basename($attachment->path);

        return $disk->response(
            $attachment->path,
            $downloadName,
            [
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $downloadName).'"',
            ]
        );
    }

    private function directoryFor(Model $model): string
    {
        $alias = Str::of($model->getMorphClass())->replace('\\', '/')->lower();
        $key = $model->getKey() ?: 'pending';

        return 'attachments/'.$alias.'/'.$key;
    }
}
