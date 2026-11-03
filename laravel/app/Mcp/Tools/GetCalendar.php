<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_calendar')]
#[Title('Calendario post programmati')]
#[Description('Post programmati (non ancora usciti) in un mese, come la pagina Calendarizza: utile per trovare i giorni liberi prima di programmare nuovi post.')]
#[IsReadOnly]
class GetCalendar extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($data['month'])
            ? Carbon::createFromFormat('Y-m-d', "{$data['month']}-01")->startOfMonth()
            : now()->startOfMonth();

        $posts = Post::query()
            ->visibleTo($this->me(), $this->scopedAccountId($request))
            ->scheduledBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth())
            ->with('user:id,name')
            ->orderBy('published_at')
            ->get()
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title ?: '(senza titolo)',
                'account' => ['id' => $p->user_id, 'name' => $p->user?->name],
                'published_at' => $p->published_at->toIso8601String(),
                'channels' => $p->activeChannels(),
            ])
            ->values()
            ->all();

        return Response::structured([
            'month' => $month->format('Y-m'),
            'timezone' => config('app.timezone'),
            'posts' => $posts,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $this->accountIdSchema($schema),
            'month' => $schema->string()->pattern('^\d{4}-\d{2}$')->description('Mese YYYY-MM. Default: mese corrente.'),
        ];
    }
}
