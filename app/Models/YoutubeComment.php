<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class YoutubeComment extends Model
{
    protected $fillable = [
        'youtube_video_id',
        'video_id',
        'comment_id',
        'author',
        'author_image',
        'text',
        'text_original',
        'like_count',
        'reply_count',
        'published_at',
        'comment_updated_at',
        'replies',
    ];

    protected $casts = [
        'replies' => 'array',
        'published_at' => 'datetime',
        'comment_updated_at' => 'datetime',
        'like_count' => 'integer',
        'reply_count' => 'integer',
    ];

    /**
     * Obtener el video al que pertenece el comentario
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }

    /**
     * Obtener el análisis del comentario
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(YoutubeCommentAnalysis::class, 'youtube_comment_id');
    }
}
