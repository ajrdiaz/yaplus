<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YoutubeVideo extends Model
{
    protected $fillable = [
        'video_id',
        'title',
        'description',
        'channel_id',
        'channel_title',
        'thumbnail_url',
        'thumbnail_default',
        'thumbnail_medium',
        'thumbnail_high',
        'url',
        'duration',
        'view_count',
        'like_count',
        'comment_count',
        'published_at',
        // Contexto de negocio
        'product_name',
        'product_description',
        'target_audience',
        'research_goal',
        'additional_context',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
    ];

    /**
     * Obtener los comentarios del video
     */
    public function comments(): HasMany
    {
        return $this->hasMany(YoutubeComment::class);
    }

    /**
     * Obtener los análisis de IA del video
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(YoutubeCommentAnalysis::class);
    }

    /**
     * Obtener el thumbnail principal (high > medium > default)
     */
    public function getThumbnailAttribute(): ?string
    {
        return $this->thumbnail_high 
            ?? $this->thumbnail_medium 
            ?? $this->thumbnail_default 
            ?? $this->thumbnail_url;
    }
}
