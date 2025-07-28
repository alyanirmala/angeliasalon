<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
    'name',
    'category_id',
    'price',
    'description',
    'image',
    'duration', // tambahkan ini
    ];


    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
