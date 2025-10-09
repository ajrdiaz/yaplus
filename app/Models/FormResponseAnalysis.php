<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormResponseAnalysis extends Model
{
    protected $fillable = [
        'form_response_id',
        'form_survey_id',
        'category',
        'sentiment',
        'relevance_score',
        'is_relevant',
        'ia_analysis',
        'keywords',
        'insights',
    ];

    protected $casts = [
        'relevance_score' => 'integer',
        'is_relevant' => 'boolean',
        'keywords' => 'array',
        'insights' => 'array',
    ];

    /**
     * Obtener la respuesta analizada
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    /**
     * Obtener el formulario al que pertenece
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(FormSurvey::class, 'form_survey_id');
    }
}
