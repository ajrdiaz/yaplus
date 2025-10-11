<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSurvey extends Model
{
    protected $fillable = [
        'product_id',
        'form_id',
        'sheet_id',
        'title',
        'description',
        'form_url',
        'responses_count',
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

    /**
     * Obtener los buyer personas del formulario
     */
    public function buyerPersonas(): HasMany
    {
        return $this->hasMany(BuyerPersona::class);
    }

    /**
     * Obtener el producto asociado
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
