<?php

declare(strict_types=1);

namespace Api\Mcp;

use Api\DataTransferObjects\ApiStatus;
use Mcp\Server\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * MCP tools for an AI assistant. The class is built by the Kipchak container, so its constructor can ask for any
 * service the API has, such as an entity manager.
 */
final class StatusTools
{
    /**
     * Reports whether the API is running.
     */
    #[McpTool(name: 'status', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function status(): ApiStatus
    {
        return new ApiStatus();
    }
}
