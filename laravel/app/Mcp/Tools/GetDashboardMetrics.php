<?php

namespace App\Mcp\Tools;

use App\Services\DashboardMetrics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_dashboard_metrics')]
#[Title('KPI dashboard')]
#[Description('Gli stessi indicatori della dashboard: post pubblicati, uscite sui canali, commenti e risposte AI degli ultimi 90 giorni con trend sui 90 precedenti e andamento settimanale, serie mensile di 12 mesi, ripartizione per canale (aperture newsletter incluse).')]
#[IsReadOnly]
class GetDashboardMetrics extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['account_id' => ['nullable', 'integer']]);

        return Response::structured([
            'period_days' => DashboardMetrics::PERIOD_DAYS,
            ...(new DashboardMetrics($this->me(), $this->scopedAccountId($request)))->toArray(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $this->accountIdSchema($schema),
        ];
    }
}
