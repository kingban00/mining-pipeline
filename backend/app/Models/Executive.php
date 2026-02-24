<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Executive extends Model
{
    use HasUuids;

    protected $fillable = [
        'fk_company_id',
        'name',
        'expertise',
        'technical_summary'
    ];

    protected $casts = [
        'expertise' => 'array',
        'technical_summary' => 'array'
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'fk_company_id');
    }
}
