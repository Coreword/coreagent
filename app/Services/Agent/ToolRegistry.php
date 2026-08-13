<?php

namespace App\Services\Agent;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    public function register(ToolInterface $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Neutral tool schema — each LlmProviderInterface adapter converts this to
     * its own wire format (OpenAI-style "parameters", Anthropic-style
     * "input_schema") internally, so one shape here is enough for every provider.
     *
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    public function toSchemaArray(): array
    {
        return collect($this->tools)->map(fn (ToolInterface $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->parametersSchema(),
        ])->values()->all();
    }
}
