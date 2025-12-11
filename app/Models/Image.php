<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'original_path',
        '256_path',
        '512_path',
        '1024_path',
        'checksum',
    ];
}
