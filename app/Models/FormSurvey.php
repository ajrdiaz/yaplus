<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSurvey extends Model
{
    protected $fillable = [
        'form_id',
        'sheet_id',
        'title',
        'description',
        'form_url',
        'responses_count',
        // Contexto de negocio
        'product_name',
        'product_description',
        'target_audience',
        'research_goal',
        'additional_context',
    ];

    protected $casts = [
        'responses_count' => 'integer',
    ];

    /**
     * Obtener las respuestas del formulario
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /**
     * Obtener los análisis del formulario
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(FormResponseAnalysis::class);
    }
}
