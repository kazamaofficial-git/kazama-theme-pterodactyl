<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Custom background per user. Selalu di-blur (blur minimal 4px, maksimal 40px). */
class BackgroundController extends Controller
{
    public const MIN_BLUR = 4;
    public const MAX_BLUR = 40;

    public static function read(int $userId): array
    {
        $p = DB::table('kazama_user_prefs')->where('user_id', $userId)->first();

        return [
            'enabled' => $p ? (bool) $p->bg_enabled && !empty($p->bg_url) : false,
            'type' => $p->bg_type ?? 'url',
            'url' => $p->bg_url ?? '',
            'blur' => max(self::MIN_BLUR, min(self::MAX_BLUR, (int) ($p->bg_blur ?? 12))),
            'min' => self::MIN_BLUR,
            'max' => self::MAX_BLUR,
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return new JsonResponse(self::read($request->user()->id));
    }

    public function update(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'type' => ['required', 'in:url,upload'],
            'url' => ['nullable', 'string', 'max:500'],
            'blur' => ['required', 'integer', 'between:' . self::MIN_BLUR . ',' . self::MAX_BLUR],
        ], ['blur.between' => 'Blur harus antara ' . self::MIN_BLUR . ' dan ' . self::MAX_BLUR . ' px (background tanpa blur tidak diizinkan).']);

        $url = trim((string) ($data['url'] ?? ''));
        if ($data['enabled']) {
            if ($data['type'] === 'url') {
                abort_unless(preg_match('#^https?://[^\s"\'()<>\\\\]+$#i', $url), 422, 'URL gambar tidak valid.');
            } else {
                abort_unless(str_starts_with($url, "/kazama/uploads/{$uid}-"), 422, 'Foto belum diunggah.');
            }
        }

        DB::table('kazama_user_prefs')->updateOrInsert(['user_id' => $uid], [
            'bg_enabled' => $data['enabled'], 'bg_type' => $data['type'], 'bg_url' => $url, 'bg_blur' => $data['blur'],
        ]);

        return new JsonResponse(self::read($uid));
    }

    public function upload(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']], ['photo.max' => 'Ukuran foto maksimal 5 MB.']);

        $dir = public_path('kazama/uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        foreach (glob("{$dir}/{$uid}-*") ?: [] as $old) {
            @unlink($old);
        }
        $file = $request->file('photo');
        $name = $uid . '-' . Str::lower(Str::random(12)) . '.' . strtolower($file->guessExtension() ?: 'jpg');
        $file->move($dir, $name);

        DB::table('kazama_user_prefs')->updateOrInsert(['user_id' => $uid], [
            'bg_enabled' => true, 'bg_type' => 'upload', 'bg_url' => "/kazama/uploads/{$name}",
            'bg_blur' => DB::table('kazama_user_prefs')->where('user_id', $uid)->value('bg_blur') ?? 12,
        ]);

        return new JsonResponse(self::read($uid));
    }
}
