<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeBuyerPersona extends Model
{
    protected $table = 'youtube_buyer_personas';

    protected $fillable = [
        'youtube_video_id',
        'nombre',
        'edad',
        'ocupacion',
        'descripcion',
        'motivaciones',
        'pain_points',
        'suenos',
        'objeciones',
        'comportamiento',
        'canales_preferidos',
        'keywords_clave',
        'porcentaje_audiencia',
        'nivel_prioridad',
        'estrategia_recomendada',
        'total_comments_analyzed',
    ];

    protected $casts = [
        'motivaciones' => 'array',
        'pain_points' => 'array',
        'suenos' => 'array',
        'objeciones' => 'array',
        'canales_preferidos' => 'array',
        'keywords_clave' => 'array',
        'porcentaje_audiencia' => 'integer',
        'total_comments_analyzed' => 'integer',
    ];

    /**
     * Relación con YoutubeVideo
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }
}
