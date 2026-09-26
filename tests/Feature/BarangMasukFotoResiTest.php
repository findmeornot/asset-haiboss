<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BarangMasukFotoResiTest extends TestCase
{
    use RefreshDatabase;

    public function test_foto_resi_bisa_diganti_dan_file_lama_terhapus()
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::fake('public');

        Storage::disk($disk)->put('barang-masuk/lama.jpg', 'old');
        $asset = Asset::factory()->create([
            'status' => 'baru_dilaporkan',
            'foto_resi' => 'barang-masuk/lama.jpg',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->post("/api/v1/barang-masuk/{$asset->ulid}/foto-resi", [
            'foto_resi' => UploadedFile::fake()->image('resi.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $newPath = $asset->fresh()->foto_resi;
        $this->assertNotSame('barang-masuk/lama.jpg', $newPath);
        Storage::disk($disk)->assertExists($newPath);
        Storage::disk($disk)->assertMissing('barang-masuk/lama.jpg');
    }

    public function test_foto_resi_wajib_berupa_gambar()
    {
        $asset = Asset::factory()->create(['status' => 'baru_dilaporkan']);

        Sanctum::actingAs(User::factory()->create());

        $this->post("/api/v1/barang-masuk/{$asset->ulid}/foto-resi", [
            'foto_resi' => UploadedFile::fake()->create('resi.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull($asset->fresh()->foto_resi);
    }
}
