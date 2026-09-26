<?php

namespace App\Outreach;

final readonly class GoogleSheetSyncReport
{
    public function __construct(
        public int $rowsRead = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $invalid = 0,
        public int $duplicates = 0,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'rows_read' => $this->rowsRead,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'invalid' => $this->invalid,
            'duplicates' => $this->duplicates,
        ];
    }
}
