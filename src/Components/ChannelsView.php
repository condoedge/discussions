<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Condoedge\Utils\Kompo\Common\Form;

class ChannelsView extends Form
{
    use DiscussionBoxTrait;

    public const RIGHT_PANEL_ID = 'discussions-right-panel';

    protected $channel;
    protected $channelId;
    protected $discussionId;

    public $containerClass = '';

    public function created()
    {
        $this->channel = $this->parameter('channel_id')
            ? Channel::with('users', 'addedBy')->find($this->parameter('channel_id'))
            : Channel::queryForUser()->with('users', 'addedBy')->first();

        $this->channelId = optional($this->channel)->id;
        $this->discussionId = $this->parameter('discussion_id');

        $this->initializeBox();
    }

    public function render()
    {
        return _Div(
            $this->leftColumn(),
            $this->middleColumn(),
            $this->channelId ? $this->rightColumn() : null,
        )->class('flex bg-gray-50')
         ->style('margin-top:-2vh; min-height: calc(100vh - 2vh);');
    }

    // ── LEFT COLUMN ──

    protected function leftColumn()
    {
        return _Rows(
            $this->leftHeader(),
            $this->searchAndFilters(),
            $this->boxTabs(),
            _Panel(
                new ChannelsListTeam([
                    'active_channel_id' => $this->channelId,
                    'active_discussion_id' => $this->discussionId,
                    'box' => $this->box,
                ]),
            )->id('channels-list-panel'),
        )->class('bg-white border-r border-gray-200 overflow-y-auto flex-shrink-0')
         ->style('width: 300px; height: calc(100vh - 2vh);');
    }

    protected function searchAndFilters()
    {
        return _Rows(
            _Input()->name('channel_search', false)
                ->placeholder('discussions.search-placeholder')
                ->class('mx-4 !mb-0 text-sm')
                ->onChange(fn ($e) => $e->selfPost('filterChannels')->withAllFormValues()->inPanel('channels-list-panel')),
            _Flex(
                $this->filterPill('all', 'discussions.filter-all'),
                $this->filterPill('unread', 'discussions.filter-unread'),
                $this->filterPill('mentions', 'discussions.filter-mentions'),
                $this->filterPill('pinned', 'discussions.filter-pinned'),
            )->class('gap-1 px-4 py-2 flex-wrap'),
        )->class('pt-3');
    }

    protected function filterPill(string $key, string $labelKey)
    {
        $active = request('filter', 'all') === $key;

        return _Button($labelKey)
            ->selfPost('filterChannels', ['filter' => $key])->withAllFormValues()
            ->inPanel('channels-list-panel')
            ->class('text-xs px-3 py-1 rounded-full whitespace-nowrap '
                . ($active ? 'bg-greenmain !text-white font-semibold' : '!bg-gray-100 !text-gray-600 hover:!bg-gray-200'));
    }

    public function filterChannels()
    {
        return new ChannelsListTeam([
            'active_channel_id' => $this->channelId,
            'active_discussion_id' => $this->discussionId,
            'box' => $this->box,
            'channel_search' => request('channel_search'),
            'filter' => request('filter', 'all'),
        ]);
    }

    protected function leftHeader()
    {
        return _Rows(
            _FlexBetween(
                _Flex(
                    _Sax('messages', 22)->class('text-greenmain'),
                    _Html('discussions.page-title')->class('font-bold text-lg'),
                )->class('gap-2 items-center'),
                _Link()->icon(_Sax('add-circle', 22))->class('text-greenmain')
                    ->get('channel-settings')->inModal(),
            )->class('px-4 pt-5 pb-3'),
        );
    }

    protected function boxTabs()
    {
        $tab = fn ($route, $iconName, $boxIndex) => _Link()->icon(_Sax($iconName, 16))
            ->href($route)
            ->class('p-2 rounded-md ' . ($this->box == $boxIndex ? 'bg-greenmain text-white' : 'text-gray-500 hover:bg-gray-100'));

        return _Flex(
            $tab('discussions', 'message', 0),
            $tab($this->box == 1 ? 'discussions' : 'discussions.archive', 'archive-1', 1),
            $tab($this->box == 2 ? 'discussions' : 'discussions.trash', 'trash', 2),
        )->class('gap-1 px-4 pb-3 border-b border-gray-100');
    }

    // ── MIDDLE COLUMN ──

    protected function middleColumn()
    {
        return _Panel(
            $this->channelId
                ? new ChannelDiscussionsPanel([
                    'channel_id' => $this->channelId,
                    'discussion_id' => $this->discussionId,
                    'box' => $this->box,
                ])
                : $this->emptyChannelState(),
        )->id('channel-view-panel')
         ->class('bg-gray-50 flex-1 flex flex-col')
         ->style('height: calc(100vh - 2vh);');
    }

    protected function emptyChannelState()
    {
        return _Rows(
            _Rows(
                _Sax('messages', 48)->class('text-greenmain'),
            )->class('w-24 h-24 rounded-full bg-greenmain bg-opacity-10 items-center justify-center mb-6'),
            _Html('discussions.empty-state-title')->class('text-xl font-bold text-gray-800 mb-2 text-center'),
            _Html('discussions.empty-state-hint')->class('text-sm text-gray-500 text-center max-w-md mb-6'),
            _Button('discussions.create-channel')->icon(_Sax('add-circle', 18))
                ->class('bg-greenmain !text-white px-5 py-2 rounded-lg font-semibold')
                ->get('channel-settings')->inModal(),
        )->class('flex-1 items-center justify-center p-12');
    }

    // ── RIGHT COLUMN ──

    protected function rightColumn()
    {
        return _Rows(
            $this->rightHeader(),
            $this->rightTabs('members'),
            _Panel($this->membersList())->id(self::RIGHT_PANEL_ID)
                ->class('flex-1 overflow-y-auto mini-scroll'),
        )->class('bg-white border-l border-gray-200 flex-shrink-0')
         ->style('width: 320px; height: calc(100vh - 2vh);');
    }

    protected function rightHeader()
    {
        $memberCount = $this->channel->users->count() + 1;

        return _Rows(
            _FlexBetween(
                _Flex(
                    _Flex(
                        _Sax($this->channel->getIconName(), 20)->class('text-white'),
                    )->class('w-10 h-10 rounded-full items-center justify-center flex-shrink-0')
                     ->style('background-color: ' . $this->channel->getColorHex() . ';'),
                    _Rows(
                        _Flex(
                            _Html($this->channel->name ?? $this->channel->display)->class('font-bold truncate'),
                            $this->visibilityPill(),
                        )->class('gap-2 items-center min-w-0'),
                        _Html($memberCount . ' ' . __('discussions.members'))->class('text-xs text-gray-500'),
                    )->class('gap-0 min-w-0'),
                )->class('gap-3 items-center min-w-0'),
                _Link()->icon(_Sax('setting-2', 18))->class('text-gray-500')
                    ->get('channel-settings', ['id' => $this->channelId])->inModal(),
            ),
            $this->channel->description ? _Html($this->channel->description)->class('text-xs text-gray-500 mt-2') : null,
        )->class('px-4 py-4 border-b border-gray-100');
    }

    protected function visibilityPill()
    {
        $isPrivate = $this->channel->is_private;

        return _Flex(
            _Sax($isPrivate ? 'lock' : 'global', 10)->class($isPrivate ? 'text-gray-500' : 'text-greenmain'),
            _Html($isPrivate ? 'discussions.visibility-private' : 'discussions.visibility-public')
                ->class('text-[10px] uppercase tracking-wide'),
        )->class('gap-1 items-center px-2 py-0.5 rounded-full flex-shrink-0 '
            . ($isPrivate ? 'bg-gray-100 text-gray-600' : 'bg-greenmain bg-opacity-10 text-greenmain'));
    }

    protected function rightTabs(string $active)
    {
        $tab = fn ($key, $labelKey) => _Button($labelKey)
            ->class('flex-1 text-xs py-1.5 rounded-md whitespace-nowrap '
                . ($active === $key ? 'bg-greenmain !text-white shadow-sm font-semibold' : '!bg-transparent !text-greenmain'))
            ->selfPost('switchRightTab', ['tab' => $key])->inPanel(self::RIGHT_PANEL_ID);

        return _Flex(
            $tab('members', 'discussions.tab-members'),
            $tab('files', 'discussions.tab-files'),
            $tab('pinned', 'discussions.tab-pinned'),
        )->class('gap-1 p-1 mx-4 my-3 rounded-lg bg-gray-100');
    }

    public function switchRightTab()
    {
        return match (request('tab', 'members')) {
            'files' => $this->filesList(),
            'pinned' => $this->pinnedList(),
            default => $this->membersList(),
        };
    }

    // ── RIGHT PANEL CONTENT ──

    protected function membersList()
    {
        $members = collect([$this->channel->addedBy])
            ->concat($this->channel->users)
            ->filter()
            ->unique('id');

        return _Rows(
            ...$members->map(fn ($user) => $this->memberRow($user))->all(),
        )->class('gap-1 p-2');
    }

    protected function memberRow($user)
    {
        $avatarUrl = $user->avatar_path ?: 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=0a6e4e&color=fff';
        $isCurrentUser = $user->id === auth()->id();

        return _Flex(
            _Img($avatarUrl)->class('w-9 h-9 rounded-full object-cover flex-shrink-0'),
            _Rows(
                _Html($user->name . ($isCurrentUser ? ' ' . __('discussions.you-suffix') : ''))
                    ->class('text-sm font-semibold truncate'),
                $isCurrentUser
                    ? _Html('discussions.current-user-label')->class('text-xs text-gray-400')
                    : null,
            )->class('gap-0 min-w-0 flex-1'),
        )->class('gap-3 items-center px-2 py-2 rounded-lg hover:bg-gray-50 transition');
    }

    protected function filesList()
    {
        $discussionIds = $this->channel->discussions()->pluck('id');
        $files = \Condoedge\Utils\Models\Files\File::where('morphable_type', 'discussion')
            ->whereIn('morphable_id', $discussionIds)
            ->with('addedBy')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        if ($files->isEmpty()) {
            return _Rows(
                _Sax('document-text', 40)->class('text-gray-300 mb-3 mx-auto'),
                _Html('discussions.no-files-yet')->class('text-sm text-gray-500 text-center'),
            )->class('items-center p-8');
        }

        return _Rows(
            ...$files->map(fn ($file) => $this->fileRow($file))->all(),
        )->class('gap-1 p-2');
    }

    protected function fileRow($file)
    {
        $extension = strtolower(pathinfo($file->name ?? '', PATHINFO_EXTENSION)) ?: 'file';
        $iconName = match (true) {
            in_array($extension, ['pdf']) => 'document-text',
            in_array($extension, ['xlsx', 'xls', 'csv']) => 'document-table',
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) => 'gallery',
            in_array($extension, ['doc', 'docx']) => 'document-normal',
            default => 'document-1',
        };

        return _Flex(
            _Sax($iconName, 20)->class('text-greenmain flex-shrink-0'),
            _Rows(
                _Html($file->name ?? $file->display_name ?? __('discussions.file'))->class('text-sm font-semibold truncate'),
                _Html(strtoupper($extension) . ($file->addedBy ? ' • ' . $file->addedBy->name : ''))
                    ->class('text-xs text-gray-500 truncate'),
            )->class('gap-0 min-w-0 flex-1'),
            $file->link ? _Link()->icon(_Sax('export-1', 14))->class('text-gray-400')
                ->href($file->link)->inNewTab() : null,
        )->class('gap-3 items-center px-2 py-2 rounded-lg hover:bg-gray-50 transition');
    }

    protected function pinnedList()
    {
        $pinned = $this->channel->discussions()->whereNotNull('pinned_at')
            ->with('addedBy', 'pinnedBy')
            ->orderByDesc('pinned_at')
            ->get();

        if ($pinned->isEmpty()) {
            return _Rows(
                _Sax('attach-circle', 40)->class('text-gray-300 mb-3 mx-auto'),
                _Html('discussions.no-pinned-yet')->class('text-sm text-gray-500 text-center'),
            )->class('items-center p-8');
        }

        return _Rows(
            ...$pinned->map(fn ($d) => $this->pinnedRow($d))->all(),
        )->class('gap-2 p-3');
    }

    protected function pinnedRow($discussion)
    {
        $preview = \Str::limit(strip_tags($discussion->html ?? $discussion->summary ?? ''), 100);

        return _Rows(
            _Flex(
                _Sax('attach-circle', 14)->class('text-warning flex-shrink-0'),
                _Html($discussion->addedBy?->name ?? '?')->class('text-xs font-semibold text-gray-700 truncate'),
                _Html($discussion->created_at->diffForHumans())->class('text-[10px] text-gray-400 ml-auto flex-shrink-0'),
            )->class('gap-1 items-center mb-1'),
            _Html($preview)->class('text-sm text-gray-800'),
        )->class('p-3 rounded-lg bg-warning bg-opacity-5 border border-warning border-opacity-20');
    }
}
