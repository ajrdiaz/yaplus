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
}
