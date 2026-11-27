<?php

namespace App\Http\Controllers;

use App\Services\AiReportSuggestionService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportAiSuggestionController extends Controller
{
    public function __construct(private AiReportSuggestionService $suggestions)
    {
    }

    public function __invoke(Request $request, AuditService $audit)
    {
        abort_if($request->user()?->is_banned, 403);
        $request->validate([
            'consent' => ['accepted'],
            'photo' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $result = $this->suggestions->suggest($request->user(), $request->file('photo'));
        } catch (\RuntimeException $e) {
            $audit->log($request->user(), 'report_ai.suggestion_failed', 'report_draft', (string) $request->user()->id, [
                'reason' => $e->getCode() === 429 ? 'limit' : 'provider_unavailable',
            ]);
            throw ValidationException::withMessages(['ai' => $e->getMessage()]);
        }

        $audit->log($request->user(), 'report_ai.suggestion_created', 'report_draft', (string) $request->user()->id, [
            'provider_id' => $result['provider_id'],
            'model' => $result['model'],
            'category_id' => $result['category_id'],
            'confidence' => $result['confidence'],
        ]);

        return response()->json(['data' => $result]);
    }
}
