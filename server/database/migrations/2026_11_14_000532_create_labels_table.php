<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('name', 120);
            $table->string('name_key', 160);
            $table->string('slug');
            $table->boolean('featured')->default(false);
            $table->timestamps();
            $table->unique(['kind', 'name_key']);
            $table->unique(['kind', 'slug']);
        });

        foreach (['trip' => 'title', 'hotel' => 'name'] as $type => $title) {
            $offers = $type.'s';
            Schema::create($type.'_labels', function (Blueprint $table) use ($type, $offers): void {
                $table->foreignId($type.'_id')->constrained($offers)->cascadeOnDelete();
                $table->foreignId('label_id')->constrained()->cascadeOnDelete();
                $table->primary([$type.'_id', 'label_id']);
                $table->index(['label_id', $type.'_id']);
            });
            Schema::table($offers, fn (Blueprint $table) => $table->text('search_text')->nullable());
            DB::table($offers)->select('id', $title, 'destination')->chunkById(200, function ($rows) use ($offers, $title): void {
                foreach ($rows as $row) {
                    DB::table($offers)->where('id', $row->id)->update([
                        'search_text' => Str::ascii(mb_strtolower($row->{$title}.' '.$row->destination)),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['trip', 'hotel'] as $type) {
            Schema::dropIfExists($type.'_labels');
            Schema::table($type.'s', fn (Blueprint $table) => $table->dropColumn('search_text'));
        }
        Schema::dropIfExists('labels');
    }
};
