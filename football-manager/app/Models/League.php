<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class League extends Model
{
    use HasFactory;

    protected $table = 'leagues';

    public $timestamps;

    protected $fillable = [
        'name',
        'season',
        'created_by',
    ];

    public function season(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function getSeason(): int
    {
        $season = collect($this->season())->first(function ($season) {
            return Carbon::now()->between($season->start_date, $season->end_date);
        });

        return $season->id;
    }
}
