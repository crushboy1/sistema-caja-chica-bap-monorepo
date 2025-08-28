<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\LogsActivity;

class Area extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'areas';

    protected $fillable = [
        'name',
        'description',
        'acronym',
        'centro_costo_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Define la relación con el modelo CentroCosto.
     * Un área pertenece a un centro de costo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'area_proyecto', 'id_area', 'id_proyecto');
    }
}
