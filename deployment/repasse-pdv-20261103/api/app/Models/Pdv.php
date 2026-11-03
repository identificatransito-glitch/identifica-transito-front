<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pdv extends Model
{
    use SoftDeletes;

    protected $table = 'points_of_sale';

    protected $fillable = [
        'name',
        'cnpj',
        'address',
        'phone',
        'responsible',
        'map_link',
        'active',
        'commission_percentage',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'commission_percentage' => 'decimal:2',
        ];
    }

    public function commissionAmount(float $salesAmount): float
    {
        return round($salesAmount * (float) ($this->commission_percentage ?? 0) / 100, 2);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
