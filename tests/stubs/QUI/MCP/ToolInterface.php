<?php

namespace QUI\MCP;

use Mcp\Server\Builder;

if (!interface_exists(ToolInterface::class)) {
    interface ToolInterface
    {
        public function register(Builder $serverBuilder): void;
    }
}
