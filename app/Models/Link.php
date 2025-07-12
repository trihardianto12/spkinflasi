<?php

// app/Models/Link.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Link extends Model
{
    protected $table = 'blog_links';

    protected $fillable = [
        'title',
        'description',
        'url',
        'published_at',
        'image',
        'status'
    ];

    protected $dates = ['published_at'];

    // Accessor untuk URL gambar
    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    // Scope untuk berita approved
    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    // Format tanggal Indonesia
    
}