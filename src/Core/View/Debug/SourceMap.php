<?php

namespace Beobles\Core\View\Debug;

class SourceMap
{
    /** @var array<int, array{file:string,line:int,column:int}> */
    private array $map = [];

    public function record(int $compiledLine, string $file, int $line, int $column): void
    {
        $this->map[$compiledLine] = [
            'file' => $file,
            'line' => $line,
            'column' => $column,
        ];
    }

    /** @return array{file:string,line:int,column:int}|null */
    public function resolve(int $compiledLine): ?array
    {
        return $this->map[$compiledLine] ?? null;
    }
}
