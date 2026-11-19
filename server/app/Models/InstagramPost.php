<?php

namespace App\Models;

use Database\Factories\InstagramPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A post shown in the homepage Instagram section. By default the public page shows Instagram's official
 * embed (photo, profile, caption). An employee can instead upload an own photo with title and text,
 * which renders as a card in the site's own design and loads nothing from Instagram.
 */
#[Fillable(['url', 'image', 'title', 'description', 'posted_on', 'sort'])]
class InstagramPost extends Model
{
    /** @use HasFactory<InstagramPostFactory> */
    use HasFactory;

    public const MAX_POSTS = 12;

    /** Matches post and reel links, e.g. https://www.instagram.com/p/De2C00fFw_8/?igsh=… */
    public const URL_PATTERN = '~^https?://(?:www\.)?instagram\.com/(?:[A-Za-z0-9_.]+/)?(p|reel|reels)/([A-Za-z0-9_-]{5,40})/?(?:[?#].*)?$~';

    protected static function booted(): void
    {
        static::saving(function (InstagramPost $post): void {
            $post->url = self::normalizeUrl($post->url) ?? $post->url;
        });
    }

    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
            'sort' => 'integer',
        ];
    }

    /** @param Builder<InstagramPost> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /** Canonical link without tracking parameters, or null when the URL is not an Instagram post/reel. */
    public static function normalizeUrl(?string $url): ?string
    {
        if (! is_string($url) || ! preg_match(self::URL_PATTERN, trim($url), $match)) {
            return null;
        }
        $kind = $match[1] === 'p' ? 'p' : 'reel';

        return 'https://www.instagram.com/'.$kind.'/'.$match[2].'/';
    }

    /** The post code from a link, e.g. "De2C00fFw_8", or null for an invalid URL. */
    public static function shortcode(?string $url): ?string
    {
        return is_string($url) && preg_match(self::URL_PATTERN, trim($url), $match) ? $match[2] : null;
    }

    /** Instagram's public embed page for an admin-only preview, or null for an invalid URL. */
    public static function embedUrl(?string $url): ?string
    {
        $normalized = self::normalizeUrl($url);

        return $normalized === null ? null : $normalized.'embed/';
    }

    /** Instagram's official embed with photo, profile and caption, for posts without an own photo. */
    public function publicEmbedUrl(): ?string
    {
        $normalized = self::normalizeUrl($this->url);

        return $normalized === null ? null : $normalized.'embed/captioned/';
    }

    public function imageUrl(): ?string
    {
        return filled($this->image) ? Storage::disk('public')->url($this->image) : null;
    }
}
