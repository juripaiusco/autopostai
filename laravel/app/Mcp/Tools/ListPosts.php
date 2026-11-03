<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_posts')]
#[Title('Elenco post')]
#[Description('Post visibili, come la Lista post: filtro (tutti, pubblicati, programmati, bozze), ricerca nel titolo, ordinamento, paginazione. Per il dettaglio di un post usa get_post.')]
#[IsReadOnly]
class ListPosts extends Tool
{
    private const FILTERS = ['tutti', 'pubblicati', 'programmati', 'bozze'];

    private const SORTABLE = ['published_at' => 'published_at', 'title' => 'title', 'comments' => 'comments_count'];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'filter' => ['nullable', Rule::in(self::FILTERS)],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTABLE))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $scope = fn () => Post::query()->visibleTo($this->me(), $this->scopedAccountId($request));
        $sortColumn = self::SORTABLE[$data['sort'] ?? 'published_at'];

        $query = $scope()->listFilter($data['filter'] ?? 'tutti');

        if (($data['search'] ?? '') !== '') {
            $query->where('title', 'like', '%'.$data['search'].'%');
        }

        // Come in UI: ordinando per data le bozze senza data restano in cima.
        if ($sortColumn === 'published_at') {
            $query->orderByRaw('published_at IS NULL DESC');
        }

        $posts = $query
            ->with('user:id,name')
            ->withCount('comments')
            ->orderBy($sortColumn, $data['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20, ['*'], 'page', $data['page'] ?? 1);

        return Response::structured([
            'counts' => collect(self::FILTERS)->mapWithKeys(fn ($f) => [$f => $scope()->listFilter($f)->count()])->all(),
            'page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
            'posts' => collect($posts->items())->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'account' => ['id' => $p->user_id, 'name' => $p->user?->name],
                'status' => $p->status(),
                'channels' => $p->activeChannels(),
                'published_at' => $p->published_at?->toIso8601String(),
                'comments' => $p->comments_count,
                'url' => route('posts.show', $p),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $this->accountIdSchema($schema),
            'filter' => $schema->string()->enum(self::FILTERS)->description('Default: tutti.'),
            'search' => $schema->string()->description('Testo da cercare nel titolo.'),
            'sort' => $schema->string()->enum(array_keys(self::SORTABLE))->description('Default: published_at.'),
            'direction' => $schema->string()->enum(['asc', 'desc'])->description('Default: desc.'),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(50)->description('Default: 20.'),
        ];
    }
}
