<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeCommentAnalysis extends Model
{
    protected $table = 'youtube_comment_analysis';

    protected $fillable = [
        'youtube_comment_id',
        'youtube_video_id',
        'category',
        'ia_analysis',
        'sentiment',
        'relevance_score',
        'keywords',
        'insights',
        'ai_model',
        'tokens_used',
        'analyzed_at',
        'is_relevant',
    ];

    protected $casts = [
        'keywords' => 'array',
        'insights' => 'array',
        'analyzed_at' => 'datetime',
        'is_relevant' => 'boolean',
        'relevance_score' => 'integer',
        'tokens_used' => 'integer',
    ];

    /**
     * Relación con el comentario
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(YoutubeComment::class, 'youtube_comment_id');
    }

    /**
     * Relación con el video
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }

    /**
     * Scope para filtrar por categoría
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para comentarios relevantes
     */
    public function scopeRelevant($query)
    {
        return $query->where('is_relevant', true);
    }

    /**
     * Scope por sentimiento
     */
    public function scopeBySentiment($query, $sentiment)
    {
        return $query->where('sentiment', $sentiment);
    }
}
