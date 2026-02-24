<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBase extends Model
{
    use HasUuids;

    protected $fillable = [
        'fk_company_id',
        'raw_content',
        'embedding'
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'fk_company_id');
    }
}
