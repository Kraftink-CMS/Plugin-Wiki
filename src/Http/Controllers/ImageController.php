<?php

namespace KraftInk\Plugins\Wiki\Http\Controllers;

use Illuminate\Http\Request;
use KraftInk\Plugins\Wiki\Services\DocumentImages;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController
{
    public function store(Request $request, DocumentImages $images): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:'.config('community.media_max_kilobytes', 8192),
        ]);
        $image = $images->store($request->user(), $data['file']);

        return response()
            ->json(['success' => true, 'data' => ['location' => $images->url($image)]], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $image, DocumentImages $images): StreamedResponse
    {
        return $images->response($image, $request->user());
    }
}
