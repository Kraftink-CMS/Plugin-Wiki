<?php

use Illuminate\Support\Facades\Route;
use KraftInk\Plugins\Wiki\Http\Controllers\DocumentController;
use KraftInk\Plugins\Wiki\Http\Controllers\FileController;
use KraftInk\Plugins\Wiki\Http\Controllers\ImageController;

Route::prefix('wiki')
    ->name('plugins.wiki.public.')
    ->middleware(['web', 'permission:wiki.view'])
    ->group(function () {
        Route::prefix('images')
            ->name('images.')
            ->group(function () {
                Route::get('/{image}', [ImageController::class, 'show'])
                    ->whereUuid('image')
                    ->name('show');
            });

        Route::prefix('files')
            ->name('files.')
            ->group(function () {
                Route::get('/{file}', [FileController::class, 'download'])
                    ->whereUuid('file')
                    ->name('download');
            });

        Route::name('documents.')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])
                ->middleware('capability:extension,wiki')
                ->name('index');
            Route::get('/{locale}/categories/{category}/pages/{slug}', [DocumentController::class, 'show'])
                ->where(['locale' => '[A-Za-z0-9_-]{2,12}', 'category' => '[0-9]+', 'slug' => '[a-z0-9-]+'])
                ->name('show');
        });
    });
