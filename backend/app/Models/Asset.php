<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasUuids;

    protected $fillable = [
        'fk_company_id',
        'name',
        'commodities',
        'status',
        'country',
        'state_province',
        'town',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'commodities' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8'
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'fk_company_id');
    }
}
