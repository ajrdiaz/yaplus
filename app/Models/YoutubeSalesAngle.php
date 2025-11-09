<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeSalesAngle extends Model
{
    protected $fillable = [
        'youtube_video_id',
        'titulo',
        'descripcion',
        'copy_ejemplo',
        'enfoque',
        'tipo_contenido',
        'orden',
    ];

    /**
     * Relación con el video de YouTube
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }
}
