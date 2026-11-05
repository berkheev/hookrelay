<?php

declare(strict_types=1);

namespace App\Sources;

final class SourceRegistry
{
    /** @var array<string, Source> */
    private array $sources = [];

    public function __construct(Source ...$sources)
    {
        foreach ($sources as $source) {
            $this->sources[$source->name] = $source;
        }
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return new self();
        }

        $config = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($config)) {
            throw new \InvalidArgumentException(sprintf('%s must contain a JSON object of sources.', $path));
        }

        $sources = [];
        foreach ($config as $name => $sourceConfig) {
            $sources[] = Source::fromArray((string) $name, (array) $sourceConfig);
        }

        return new self(...$sources);
    }

    public function get(string $name): ?Source
    {
        return $this->sources[$name] ?? null;
    }

    /**
     * @return array<string, Source>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
