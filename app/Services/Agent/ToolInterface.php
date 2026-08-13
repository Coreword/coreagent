<?php

namespace App\Services\Agent;

use App\Models\User;

interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema object, e.g. ['type' => 'object', 'properties' => [...], 'required' => [...]]
     */
    public function parametersSchema(): array;

    /**
     * @param  array  $arguments
     * @return array result data — will be JSON-encoded back to the LLM as the tool result
     */
    public function execute(array $arguments, User $user): array;
}
