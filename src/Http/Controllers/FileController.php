<?php

namespace KraftInk\Plugins\Wiki\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use KraftInk\Plugins\Wiki\Services\DocumentFiles;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController
{
    public function store(Request $request, int $id, DocumentFiles $files): RedirectResponse
    {
        $data = $request->validate(['file' => 'required|file|max:10240']);
        $files->store($request->user(), $id, $data['file']);

        return back()->with('status', __('wiki::messages.files.saved'));
    }

    public function download(Request $request, string $file, DocumentFiles $files): StreamedResponse
    {
        return $files->download($file, $request->user(), $request->routeIs('plugins.wiki.admin.files.download'));
    }

    public function destroy(int $id, string $file, DocumentFiles $files): RedirectResponse
    {
        $files->delete($id, $file);

        return back()->with('status', __('wiki::messages.files.deleted'));
    }
}
