<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormResponse extends Model
{
    protected $fillable = [
        'form_survey_id',
        'response_id',
        'respondent_email',
        'submitted_at',
        'raw_data',
        'combined_text',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'submitted_at' => 'datetime',
    ];

    /**
     * Obtener el formulario al que pertenece
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(FormSurvey::class, 'form_survey_id');
    }

    /**
     * Obtener el análisis de esta respuesta
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(FormResponseAnalysis::class);
    }
}
