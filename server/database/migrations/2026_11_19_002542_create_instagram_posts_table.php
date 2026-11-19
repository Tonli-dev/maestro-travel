<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Editable Instagram posts for the homepage section. The three posts that were hard-coded in the
 * homepage template are carried over, so the public page looks the same right after migrating.
 */
return new class extends Migration
{
    /** @var list<array{url: string, image: string, title: string, description: string, posted_on: string}> */
    private const EXISTING_POSTS = [
        ['url' => 'https://www.instagram.com/p/De2C00fFw_8/', 'image' => 'instagram-italija.webp', 'title' => 'Shopping u Italiji', 'description' => 'Palmanova, Noventa di Piave i šetnja Trstom.', 'posted_on' => '2026-10-23'],
        ['url' => 'https://www.instagram.com/p/DekIIs0DavH/', 'image' => 'instagram-opatija.webp', 'title' => 'Opatija i Festival čokolade', 'description' => 'Jednodnevni izlet, slatki užici i šetnja uz more.', 'posted_on' => '2026-10-16'],
        ['url' => 'https://www.instagram.com/p/Dei8l5mj3HA/', 'image' => 'instagram-interliber.webp', 'title' => 'Interliber u Zagrebu', 'description' => 'Jedan dan za knjige, nove priče i istraživanje Zagreba.', 'posted_on' => '2026-10-16'],
    ];

    public function up(): void
    {
        Schema::create('instagram_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->string('image')->nullable();
            $table->string('title', 120)->nullable();
            $table->string('description', 300)->nullable();
            $table->date('posted_on')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (self::EXISTING_POSTS as $index => $post) {
            $image = 'instagram/'.$post['image'];
            $source = public_path('images/editorial/'.$post['image']);
            // Unit tests migrate an in-memory database many times; keep them from writing to real storage.
            if (! app()->runningUnitTests() && is_file($source) && ! Storage::disk('public')->exists($image)) {
                Storage::disk('public')->put($image, (string) file_get_contents($source));
            }
            DB::table('instagram_posts')->insert([
                ...$post, 'image' => $image, 'sort' => $index, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_posts');
    }
};
