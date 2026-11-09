<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCategories();
        $this->createDocuments();
        $this->createFiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki__files');
        Schema::dropIfExists('wiki__category_roles');
        Schema::dropIfExists('wiki__document_images');
        Schema::dropIfExists('wiki__images');
        Schema::dropIfExists('wiki__document_revisions');
        Schema::dropIfExists('wiki__documents');
        Schema::dropIfExists('wiki__categories');
    }

    private function createCategories(): void
    {
        Schema::create('wiki__categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('wiki__categories')->nullOnDelete();
            $table->string('locale', 12);
            $table->string('name', 120);
            $table->string('icon', 60)->default('book');
            $table->text('description')->nullable();
            $table->string('read_access', 16)->default('public');
            $table->string('permission', 100)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['locale', 'parent_id', 'name']);
        });
        Schema::create('wiki__category_roles', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('wiki__categories')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['category_id', 'role_id']);
        });
    }

    private function createDocuments(): void
    {
        Schema::create('wiki__documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('wiki__categories')->nullOnDelete();
            $table->uuid('translation_group')->nullable();
            $slugCategory = $table->unsignedBigInteger('slug_category');
            $categoryExpression = 'COALESCE(category_id, 0)';
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $slugCategory->storedAs($categoryExpression);
            } else {
                $slugCategory->virtualAs($categoryExpression);
            }
            $table->string('locale', 12);
            $table->string('slug', 180);
            $table->string('title', 180);
            $table->longText('body');
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->string('permission', 100)->nullable();
            $table->boolean('published')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['locale', 'published', 'position']);
            $table->index(['deleted_at', 'id'], 'wiki_documents_trash_index');
            $table->unique(['translation_group', 'locale'], 'wiki_translation_locale_unique');
            $table->unique(['locale', 'slug_category', 'slug'], 'wiki_document_category_slug_unique');
            $table->index(['published', 'deleted_at', 'updated_at'], 'wiki_documents_retention_index');
        });
        Schema::create('wiki__document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('wiki__documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('snapshot');
            $table->timestamp('created_at');
            $table->index(['document_id', 'created_at', 'id'], 'wiki_revisions_retention_index');
        });
    }

    private function createFiles(): void
    {
        Schema::create('wiki__images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 255);
            $table->string('hash', 64);
            $table->string('original_name', 255);
            $table->string('mime', 64);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
            $table->index(['uploader_id', 'hash']);
            $table->index('updated_at');
        });
        Schema::create('wiki__document_images', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained('wiki__documents')->cascadeOnDelete();
            $table->foreignUuid('image_id')->constrained('wiki__images')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->primary(['resource_id', 'image_id']);
            $table->index(['image_id', 'active']);
        });
        Schema::create('wiki__files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('document_id')->constrained('wiki__documents')->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('name', 180);
            $table->string('mime', 64);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->timestamp('created_at');
            $table->index(['document_id', 'created_at']);
            $table->index('uploader_id');
        });
    }
};
