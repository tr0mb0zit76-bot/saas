<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Services\Ai\AiInteractionRecorder;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Support\AiInteractionFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiToolAuditLogger
{
    public function __construct(
        private readonly AiInteractionRecorder $interactionRecorder,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function log(
        User $user,
        string $tool,
        array $arguments,
        bool $ok,
        ?string $errorMessage = null,
        AiInteractionFeature $feature = AiInteractionFeature::Mcp,
    ): void {
        $redactedArguments = $this->redact($arguments);

        $this->interactionRecorder->recordToolInvoked(
            $user,
            $feature,
            $tool,
            $redactedArguments,
            $ok,
            $errorMessage,
        );

        if (! Schema::hasTable('ai_tool_audit_logs')) {
            return;
        }

        try {
            $auditRow = [
                'user_id' => $user->id,
                'tool' => $tool,
                'arguments' => json_encode($redactedArguments, JSON_UNESCAPED_UNICODE),
                'ok' => $ok,
                'error_message' => $errorMessage,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('ai_tool_audit_logs', 'tenant_id')) {
                $auditRow['tenant_id'] = $user->tenant_id;
            }

            DB::table('ai_tool_audit_logs')->insert($auditRow);
        } catch (Throwable) {
            // Аудит не должен ломать вызов tool.
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function redact(array $arguments): array
    {
        // Reuse LLM sanitizer: nested PII field keys + email/phone patterns in free text.
        return $this->sanitizer->sanitizeStructured($arguments, 'default');
    }
}
