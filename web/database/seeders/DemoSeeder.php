<?php

namespace Database\Seeders;

use App\Actions\GameSession\MapImportFailure;
use App\Actions\GameSession\ParseSavedVariablesFile;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Jobs\ImportSavedVariablesFileJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\GameSession\SavedVariables\SessionSpool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@whereivebeen.local';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $fixture = base_path('tests/Fixtures/whereivebeen/WhereIveBeen.lua');

        if (! file_exists($fixture)) {
            $this->command->warn('Demo fixture is missing, nothing to seed.');

            return;
        }

        $this->call([EventTypeSeeder::class, MapSeeder::class]);

        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'password' => Hash::make(self::PASSWORD),
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );

        $contents = (string) file_get_contents($fixture);
        $path = 'imports/uploads/'.$user->id.'/demo.lua';

        Storage::disk(SessionSpool::DISK)->put($path, $contents);

        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'filename' => 'WhereIveBeen.lua',
            'file_size' => strlen($contents),
            'status' => ImportBatchStatusEnum::NEW,
        ]);

        (new ImportSavedVariablesFileJob(SessionSpool::DISK, $path, $user, $batch->id))
            ->handle(app(ParseSavedVariablesFile::class), app(MapImportFailure::class));

        $this->command->info(sprintf(
            'Demo user %s (password: %s) — %d sessions queued.',
            self::EMAIL,
            self::PASSWORD,
            (int) $batch->fresh()?->sessions_queued,
        ));
    }
}
