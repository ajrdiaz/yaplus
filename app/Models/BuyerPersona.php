<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerPersona extends Model
{
    protected $table = 'form_buyer_personas';

    protected $fillable = [
        'form_survey_id',
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
        'total_responses_analyzed',
    ];

    protected $casts = [
        'motivaciones' => 'array',
        'pain_points' => 'array',
        'suenos' => 'array',
        'objeciones' => 'array',
        'canales_preferidos' => 'array',
        'keywords_clave' => 'array',
        'porcentaje_audiencia' => 'integer',
        'total_responses_analyzed' => 'integer',
    ];

    /**
     * Relación con FormSurvey
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(FormSurvey::class, 'form_survey_id');
    }
}
