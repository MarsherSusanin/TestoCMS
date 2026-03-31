<?php

namespace App\Modules\Core\DTO;

class RobotsDirectivesDto
{
    public function __construct(
        public bool $index = true,
        public bool $follow = true,
        public bool $noarchive = false,
        public bool $nosnippet = false,
    ) {
    }

    public static function fromArray(?array $input): self
    {
        $input ??= [];

        return new self(
            index: (bool) ($input['index'] ?? true),
            follow: (bool) ($input['follow'] ?? true),
            noarchive: (bool) ($input['noarchive'] ?? false),
            nosnippet: (bool) ($input['nosnippet'] ?? false),
        );
    }

    public function toMetaContent(): string
    {
        $parts = [];
        $parts[] = $this->index ? 'index' : 'noindex';
        $parts[] = $this->follow ? 'follow' : 'nofollow';

        if ($this->noarchive) {
            $parts[] = 'noarchive';
        }

        if ($this->nosnippet) {
            $parts[] = 'nosnippet';
        }

        return implode(',', $parts);
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'follow' => $this->follow,
            'noarchive' => $this->noarchive,
            'nosnippet' => $this->nosnippet,
        ];
    }
}
