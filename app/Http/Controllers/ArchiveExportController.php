<?php

namespace App\Http\Controllers;

use App\Exports\ArchivePresenceExport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use viki\Service\Models\Elequent\Archive;

class ArchiveExportController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Archive $archive): BinaryFileResponse
    {
        $this->authorize('view', $archive);

        $date = Carbon::parse($archive->date);
        $workplaceName = $archive->workplace?->name ?? 'archive';
        $safeName = Str::slug($workplaceName, '_');
        $fileName = sprintf('archive_%s_%s.xlsx', $safeName, $date->format('Y_m'));

        return Excel::download(new ArchivePresenceExport($archive), $fileName);
    }
}
