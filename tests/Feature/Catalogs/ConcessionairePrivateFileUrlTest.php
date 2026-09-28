<?php

declare(strict_types=1);

use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\DocumentType;
use App\Models\User;
use App\Support\PdfAssetLoader;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(PermissionsSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function privateFileUrlUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

    return $user;
}

function concessionaireWithPrivateFiles(): Concessionaire
{
    $type = ConcessionaireType::query()->create([
        'code' => 'PNAT',
        'name' => 'Persona Natural',
        'is_active' => true,
    ]);
    $documentType = DocumentType::query()->create([
        'code' => 'V',
        'name' => 'Cédula',
        'mask' => '########',
        'is_active' => true,
    ]);

    return Concessionaire::query()->create([
        'concessionaire_type_id' => $type->getKey(),
        'full_name' => 'Cesionario con archivos',
        'document_type_id' => $documentType->getKey(),
        'document_number' => '12345678',
        'fiscal_address' => 'Dirección de prueba',
        'email' => 'archivos@example.com',
        'photo_path' => 'concessionaires/photos/photo.avif',
        'id_document_path' => 'concessionaires/id_documents/document.pdf',
        'is_active' => true,
    ]);
}

it('uses public disk urls in local development', function () {
    Storage::fake('public');
    config(['filesystems.uploads_disk' => 'public']);

    $concessionaire = concessionaireWithPrivateFiles();
    $photoUrl = Storage::disk('public')->url($concessionaire->photo_path);
    $documentUrl = Storage::disk('public')->url($concessionaire->id_document_path);

    $this->actingAs(privateFileUrlUser())
        ->get(route('catalogs.concessionaire.show', $concessionaire))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.photo_url', $photoUrl)
            ->where('item.id_document_url', $documentUrl)
        );
});

it('uses one hour temporary urls for a private s3 disk', function () {
    Carbon::setTestNow('2026-09-27 12:00:00');
    config([
        'filesystems.uploads_disk' => 's3',
        'filesystems.disks.s3.driver' => 's3',
    ]);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration): string => 'https://private.r2.test/'.$path.'?expires='.$expiration->getTimestamp(),
    );

    $concessionaire = concessionaireWithPrivateFiles();
    $expiration = now()->addHour()->getTimestamp();

    $this->actingAs(privateFileUrlUser())
        ->get(route('catalogs.concessionaire.show', $concessionaire))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.photo_url', 'https://private.r2.test/'.$concessionaire->photo_path.'?expires='.$expiration)
            ->where('item.id_document_url', 'https://private.r2.test/'.$concessionaire->id_document_path.'?expires='.$expiration)
        );
});

it('only exposes the photo temporary url in the index rows', function () {
    Carbon::setTestNow('2026-09-27 12:00:00');
    config([
        'filesystems.uploads_disk' => 's3',
        'filesystems.disks.s3.driver' => 's3',
    ]);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration): string => 'https://private.r2.test/'.$path.'?expires='.$expiration->getTimestamp(),
    );

    $concessionaire = concessionaireWithPrivateFiles();

    $this->actingAs(privateFileUrlUser())
        ->get(route('catalogs.concessionaire.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.photo_url', 'https://private.r2.test/'.$concessionaire->photo_path.'?expires='.now()->addHour()->getTimestamp())
            ->missing('rows.0.id_document_url')
        );
});

it('loads photos from the configured disk for generated documents', function () {
    Storage::fake('s3');
    config(['filesystems.uploads_disk' => 's3']);

    $path = 'concessionaires/photos/photo.png';
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    Storage::disk('s3')->put($path, $png);

    $asset = app(PdfAssetLoader::class)->uploadedImage($path);

    expect($asset['mime'])->toBe('image/png')
        ->and($asset['base64'])->toBe(base64_encode($png));
});

it('gracefully ignores an avif photo that cannot be decoded', function () {
    Storage::fake('s3');
    config(['filesystems.uploads_disk' => 's3']);

    $path = 'concessionaires/photos/photo.avif';
    Storage::disk('s3')->put($path, 'invalid-avif');

    expect(app(PdfAssetLoader::class)->uploadedImage($path))->toBe([
        'base64' => null,
        'mime' => null,
    ]);
});

it('converts avif photos when the runtime can encode and decode them', function () {
    if (! class_exists(Imagick::class)) {
        $this->markTestSkipped('Imagick is unavailable.');
    }

    $image = new Imagick;
    $probe = new Imagick;
    $avif = '';

    try {
        $image->newImage(2, 2, new ImagickPixel('red'));
        $image->setImageFormat('avif');
        $avif = $image->getImageBlob();

        $probe->readImageBlob($avif);
        $probe->setIteratorIndex(0);
        $probe->setImageFormat('png');
        if ($probe->getImageBlob() === '') {
            throw new RuntimeException('ImageMagick produced an empty PNG.');
        }
    } catch (Throwable $exception) {
        $this->markTestSkipped('Functional AVIF codec is unavailable: '.$exception->getMessage());
    } finally {
        $image->clear();
        $probe->clear();
    }

    Storage::fake('s3');
    config(['filesystems.uploads_disk' => 's3']);

    $path = 'concessionaires/photos/photo.avif';
    Storage::disk('s3')->put($path, $avif);

    $asset = app(PdfAssetLoader::class)->uploadedImage($path);

    expect($asset['mime'])->toBe('image/png')
        ->and($asset['base64'])->not->toBeNull()
        ->and(substr((string) base64_decode((string) $asset['base64']), 1, 3))->toBe('PNG');
});
