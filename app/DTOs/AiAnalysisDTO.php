<?php

namespace App\DTOs;

class AiAnalysisDTO
{
    public function __construct(
        public readonly string $summary,
        public readonly array $keyPoints,
        public readonly ?string $suggestedResponse,
        public readonly float $confidence,
    ) {}

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'keyPoints' => $this->keyPoints,
            'suggestedResponse' => $this->suggestedResponse,
            'confidence' => $this->confidence,
        ];
    }
}
