<?php

namespace App\Modules\Content\Services;

use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/** Database locks shared by every writer, including CLI and long-lived workers. */
class ContentMutationGuard
{
    public function lockMedia(): void
    {
        $this->lock('media');
    }

    public function lockCategories(): void
    {
        // All category mutations also serialize introduction/removal of media references.
        $this->lockMedia();
        $this->lock('categories');
    }

    private function lock(string $key): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Content mutation guards require an active transaction.');
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite ignores FOR UPDATE. Acquire its writer lock before reading
            // references, rather than attempting a deferred read-to-write upgrade.
            DB::table('cms_mutation_guards')->where('key', $key)->update(['key' => $key]);
        }
        $guard = DB::table('cms_mutation_guards')->where('key', $key)->lockForUpdate()->first();
        if ($guard === null) {
            throw new RuntimeException('The CMS mutation guard is missing. Run the CMS migrations before writing content.');
        }
    }
}
