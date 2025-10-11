<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CopyGeneration extends Model
{
    protected $fillable = [
        'buyer_persona_id',
        'buyer_persona_type',
        'product_id',
        'copy_type',
        'name',
        'headline',
        'subheadline',
        'body',
        'cta',
        'additional_data',
        'character_count',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];

    /**
     * Relación polimórfica con buyer personas
     */
    public function buyerPersona(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relación con producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Tipos de copy disponibles
     */
    public static function getCopyTypes(): array
    {
        return [
            'facebook_ad' => 'Anuncio Facebook/Instagram',
            'google_ad' => 'Anuncio Google Ads',
            'landing_hero' => 'Hero de Landing Page',
            'email_subject' => 'Asunto de Email',
            'email_body' => 'Cuerpo de Email',
            'instagram_post' => 'Post de Instagram',
            'linkedin_post' => 'Post de LinkedIn',
            'twitter_thread' => 'Hilo de Twitter/X',
        ];
    }
}
