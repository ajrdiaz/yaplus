<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'nombre',
        'audiencia_objetivo',
        'descripcion',
        'puntos_dolor',
        'beneficios_clave',
        'propuesta_valor',
        // Campos de consolidación
        'top_5_buyer_personas',
        'pain_points_consolidados',
        'motivaciones_consolidadas',
        'suenos_consolidados',
        'objeciones_consolidadas',
        'keywords_consolidadas',
        'canales_preferidos',
        'demografia_promedio',
        'insights_youtube',
        'insights_google_forms',
        'total_buyer_personas',
        'total_youtube_personas',
        'total_google_form_personas',
        'ultima_consolidacion',
    ];

    protected $casts = [
        'top_5_buyer_personas' => 'array',
        'pain_points_consolidados' => 'array',
        'motivaciones_consolidadas' => 'array',
        'suenos_consolidados' => 'array',
        'objeciones_consolidadas' => 'array',
        'keywords_consolidadas' => 'array',
        'canales_preferidos' => 'array',
        'demografia_promedio' => 'array',
        'ultima_consolidacion' => 'datetime',
    ];

    /**
     * Obtener los videos de YouTube asociados a este producto
     */
    public function youtubeVideos(): HasMany
    {
        return $this->hasMany(YoutubeVideo::class);
    }

    /**
     * Obtener los formularios de Google Forms asociados a este producto
     */
    public function formSurveys(): HasMany
    {
        return $this->hasMany(FormSurvey::class);
    }

    /**
     * Obtener todos los buyer personas de YouTube para este producto
     */
    public function youtubeBuyerPersonas()
    {
        return $this->hasManyThrough(
            YoutubeBuyerPersona::class,
            YoutubeVideo::class,
            'product_id',
            'youtube_video_id',
            'id',
            'id'
        );
    }

    /**
     * Obtener todos los buyer personas de Google Forms para este producto
     */
    public function googleFormBuyerPersonas()
    {
        return $this->hasManyThrough(
            BuyerPersona::class,
            FormSurvey::class,
            'product_id',
            'form_survey_id',
            'id',
            'id'
        );
    }

    /**
     * Verificar si el producto tiene datos consolidados
     */
    public function hasConsolidatedData(): bool
    {
        return ! is_null($this->ultima_consolidacion) && ! is_null($this->top_5_buyer_personas);
    }

    /**
     * Verificar si la consolidación está desactualizada (más de 7 días)
     */
    public function isConsolidationStale(): bool
    {
        if (! $this->ultima_consolidacion) {
            return true;
        }

        return $this->ultima_consolidacion->diffInDays(now()) > 7;
    }
}
