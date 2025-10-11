<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use viki\Service\Models\Elequent\Archive;

class ArchivePresenceExport implements FromView
{
    protected Archive $archive;

    public function __construct(Archive $archive)
    {
        $this->archive = $archive->loadMissing('workplace');
    }

    public function view(): View
    {
        $date = Carbon::parse($this->archive->date);
        $data = json_decode($this->archive->json_data, true) ?? [];

        return view('exports.archive-presence', [
            'archive' => $this->archive,
            'archiveData' => $data,
            'date' => $date,
        ]);
    }
}
