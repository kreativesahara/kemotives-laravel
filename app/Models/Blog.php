<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $table = 'blogs';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'image_url',
        'content',
        'backlinks',
        'author_id',
        'meta_description',
        'meta_keywords',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function toArray()
    {
        $array = parent::toArray();
        return [
            'id' => $array['id'] ?? null,
            'title' => $array['title'] ?? null,
            'slug' => $array['slug'] ?? null,
            'description' => $array['description'] ?? null,
            'imageUrl' => $array['image_url'] ?? null,
            'content' => $array['content'] ?? null,
            'backlinks' => $array['backlinks'] ?? null,
            'authorId' => $array['author_id'] ?? null,
            'metaDescription' => $array['meta_description'] ?? null,
            'metaKeywords' => $array['meta_keywords'] ?? null,
            'isPublished' => $array['is_published'] ?? false,
            'publishedAt' => $array['published_at'] ?? null,
            'createdAt' => $array['created_at'] ?? null,
            'updatedAt' => $array['updated_at'] ?? null,
            'votes' => $array['votes'] ?? 0,
        ];
    }
}
