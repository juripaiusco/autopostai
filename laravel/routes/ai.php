<?php

use App\Http\Middleware\McpAuditLog;
use App\Http\Middleware\McpOriginGuard;
use App\Http\Middleware\McpTokenAuth;
use App\Mcp\Servers\FaPer3Server;
use Laravel\Mcp\Facades\Mcp;

// Server MCP di FaPer3 (caricato da laravel/mcp, fuori dal gruppo web: niente
// sessione né CSRF). Ordine: origine → throttle → token → log.
Mcp::web('/mcp', FaPer3Server::class)
    ->middleware([McpOriginGuard::class, 'throttle:mcp', McpTokenAuth::class, McpAuditLog::class]);
