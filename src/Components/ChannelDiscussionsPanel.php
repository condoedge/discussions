<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Components\Traits\ScrollToOnLoadTrait;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Condoedge\Utils\Kompo\Common\Query;

class ChannelDiscussionsPanel extends Query
{
    use DiscussionBoxTrait,
        ScrollToOnLoadTrait;

    public $id = 'channel-discussion-panel';

    public $topPagination = true;
    public $bottomPagination = false;

    public $paginationType = 'Scroll';

    protected $channelId;
    protected $channel;
    protected $discussionId;
    protected $discussion;
    protected $lastRenderedAuthorId;
    protected $lastRenderedDate;
    protected $firstUnreadRendered = false;

    public function booted()
    {
        $this->activateScroll('#discussion-card-', '.channel-scroll');
    }

    public function created()
    {
        $this->channelId = $this->store('channel_id') ?: $this->parameter('id');
        $this->channel = Channel::find($this->channelId);
        $this->discussionId = $this->store('discussion_id') ?: $this->parameter('discussion_id');
        $this->discussion = $this->discussionId ? Discussion::find($this->discussionId) : null;

        $securityChannel = $this->discussion ? $this->discussion->channel : $this->channel;
        if ($securityChannel && !auth()->user()->can('view', $securityChannel)) {
            abort(403);
        }

        $this->itemsWrapperClass = 'channel-scroll overflow-y-auto mini-scroll py-4 px-4 space-y-1';
        $this->itemsWrapperStyle = $this->discussionId
            ? 'height: calc(100vh - 260px);'
            : 'height: calc(100vh - 380px);';

        $this->pusherRefresh = Discussion::pusherRefresh();

        $this->initializeBox();
    }

    public function query()
    {
        if ($this->discussionId && $this->discussion) {
            $replies = $this->discussion->discussions()
                ->with('addedBy', 'reactions')
                ->orderBy('created_at')
                ->get()
                ->all();

            return array_merge([$this->discussion], $replies);
        }

        if ($this->channel) {
            $query = $this->channel->discussions()
                ->whereNull('discussion_id')
                ->with('box', 'addedBy', 'discussions.addedBy', 'channel', 'reactions', 'pinnedBy')
                ->orderByDesc('created_at');

            return $this->box
                ? $query->whereHas('box', fn ($q) => $q->where('box', $this->box))
                : $query->doesntHave('box');
        }
    }

    // ── TOP: header + pinned banner ──

    public function top()
    {
        if (!$this->channel) {
            return null;
        }

        return _Rows(
            $this->chatHeader(),
            $this->pinnedBanner(),
        );
    }

    protected function chatHeader()
    {
        $memberCount = $this->channel->users->count() + 1;
        $otherUsers = $this->channel->users->take(4);

        return _FlexBetween(
            !$this->discussionId
                ? _Flex(
                    _Flex(
                        _Sax($this->channel->getIconName(), 18)->class('text-white'),
                    )->class('w-10 h-10 rounded-full items-center justify-center flex-shrink-0')
                     ->style('background-color: ' . $this->channel->getColorHex() . ';'),
                    _Rows(
                        _Flex(
                            _Link($this->channel->name ?? $this->channel->display)
                                ->class('font-bold text-base truncate')
                                ->href('discussions', ['channel_id' => $this->channelId]),
                            $this->channel->is_private
                                ? _Sax('lock', 12)->class('text-gray-400')
                                : null,
                        )->class('gap-1.5 items-center'),
                        $this->channel->description
                            ? _Html($this->channel->description)->class('text-xs text-gray-500 truncate')
                            : _Html($memberCount . ' ' . __('discussions.members'))->class('text-xs text-gray-500'),
                    )->class('gap-0 min-w-0'),
                )->class('gap-3 items-center min-w-0')
                : _Flex(
                    _Link($this->channel->name)->icon('arrow-left')
                        ->class('text-lg text-level1')
                        ->href('discussions', ['channel_id' => $this->channelId]),
                    _Html('•')->class('text-gray-300'),
                    _Html($this->discussion->subject)->class('truncate font-semibold'),
                )->class('gap-2 items-center'),

            _FlexEnd(
                (!auth()->user()->can('delete', $this->channel) || $this->discussionId) ? null
                    : _DeleteLink()->byKey($this->channel)->class('text-sm text-gray-500 mr-2')
                        ->redirect('discussions'),
                _Link()->icon(_Sax('setting-2', 18))->class('text-gray-500')
                    ->get('channel-settings', ['id' => $this->channelId])->inModal(),
            )->class('gap-2 flex-shrink-0'),
        )->class('p-4 bg-white border-b border-gray-200');
    }

    protected function pinnedBanner()
    {
        $pinned = $this->channel->discussions()->whereNotNull('pinned_at')->orderByDesc('pinned_at')->first();

        if (!$pinned) {
            return null;
        }

        return _Flex(
            _Sax('attach-circle', 16)->class('text-warning'),
            _Rows(
                _Html('discussions.pinned-label')->class('text-[10px] uppercase tracking-wide text-gray-500'),
                _Html(\Str::limit(strip_tags($pinned->html ?? $pinned->summary ?? ''), 80))
                    ->class('text-sm text-gray-800 truncate'),
            )->class('gap-0 min-w-0 flex-1'),
            auth()->user()->can('delete', $this->channel)
                ? _Link()->icon(_Sax('close-circle', 14))->class('text-gray-400 hover:text-gray-600')
                    ->selfPost('unpinDiscussion', ['id' => $pinned->id])->refresh()
                : null,
        )->class('gap-2 items-center bg-warning bg-opacity-10 border-b border-warning border-opacity-30 px-4 py-2');
    }

    // ── MESSAGE RENDER ──

    public function render($discussion)
    {
        $authorId = $discussion->addedBy?->id;
        $isCurrentUser = (int) $authorId === (int) auth()->id();
        $currentDate = $discussion->created_at->format('Y-m-d');

        $parts = [];

        if ($this->lastRenderedDate !== $currentDate) {
            $parts[] = $this->dateSeparator($discussion->created_at);
            $this->lastRenderedDate = $currentDate;
            $this->lastRenderedAuthorId = null;
        }

        if (!$this->firstUnreadRendered && !$discussion->read) {
            $parts[] = $this->newMessagesSeparator();
            $this->firstUnreadRendered = true;
        }

        $grouped = $authorId === $this->lastRenderedAuthorId;
        $this->lastRenderedAuthorId = $authorId;

        $parts[] = $this->messageBubble($discussion, $isCurrentUser, $grouped);

        if (!$discussion->read) {
            $this->scrollToId = $this->scrollToId ?: $discussion->id;
            $discussion->markRead();
        }

        return _Rows(...$parts);
    }

    protected function dateSeparator($date)
    {
        $label = $date->isToday() ? __('discussions.today')
            : ($date->isYesterday() ? __('discussions.yesterday')
            : $date->translatedFormat('d F Y'));

        return _Flex(
            _Html()->class('flex-1 border-t border-gray-200'),
            _Html($label)->class('text-[10px] uppercase tracking-wide text-gray-500 px-3 py-0.5 rounded-full bg-gray-100'),
            _Html()->class('flex-1 border-t border-gray-200'),
        )->class('gap-3 items-center my-3');
    }

    protected function newMessagesSeparator()
    {
        return _Flex(
            _Html()->class('flex-1 border-t border-pink-400'),
            _Html('discussions.new-messages')->class('text-[10px] uppercase tracking-wide text-pink-500 font-semibold px-3'),
            _Html()->class('flex-1 border-t border-pink-400'),
        )->class('gap-2 items-center my-3');
    }

    protected function messageBubble($discussion, bool $isCurrentUser, bool $grouped)
    {
        $user = $discussion->addedBy;
        $avatarUrl = $user?->avatar_path ?: 'https://ui-avatars.com/api/?name=' . urlencode($user?->name ?? '?') . '&background=' . ltrim($this->channel->getColorHex(), '#') . '&color=fff';

        $bubbleClasses = $isCurrentUser
            ? 'bg-greenmain text-white rounded-l-2xl rounded-tr-2xl rounded-br-sm px-4 py-2'
            : 'bg-white border border-gray-200 rounded-r-2xl rounded-tl-2xl rounded-bl-sm px-4 py-2 text-gray-800';

        $body = $this->highlightMentions($discussion->html ?? $discussion->summary ?? '');

        $bubble = _Rows(
            !$grouped && !$isCurrentUser ? _Html($user?->name)->class('text-xs font-semibold text-gray-700 mb-0.5') : null,
            $discussion->subject ? _Html($discussion->subject)->class('text-xs font-semibold ' . ($isCurrentUser ? 'text-white opacity-80' : 'text-gray-700') . ' mb-1') : null,
            _Html($body)->class('text-sm'),
            $this->reactionsRow($discussion, $isCurrentUser),
            $this->threadPreview($discussion, $isCurrentUser),
        )->class($bubbleClasses)->style('max-width: 520px;');

        $avatar = $grouped
            ? _Html()->class('w-8 h-8 flex-shrink-0')
            : _Img($avatarUrl)->class('w-8 h-8 rounded-full object-cover flex-shrink-0');

        $wrapper = _Flex($avatar, $bubble);

        if ($isCurrentUser) {
            $wrapper = $wrapper->class('flex-row-reverse justify-start');
        }

        return $wrapper->class('gap-2 items-end ' . ($grouped ? 'mt-0.5' : 'mt-2'))
            ->id('discussion-card-' . $discussion->id);
    }

    protected function highlightMentions(string $html): string
    {
        return preg_replace(
            '/@([a-zA-ZÀ-ÿ0-9_\-\.]+)/u',
            '<span class="inline-block px-1.5 py-0.5 rounded bg-greenmain bg-opacity-15 text-greenmain font-semibold">@$1</span>',
            $html,
        );
    }

    protected function reactionsRow($discussion, bool $isCurrentUser)
    {
        $grouped = $discussion->reactionsGrouped();

        if ($grouped->isEmpty()) {
            return null;
        }

        return _Flex(
            ...$grouped->map(fn ($r) => _Flex(
                _Html($r['emoji'])->class('text-base leading-none'),
                _Html((string) $r['count'])->class('text-xs'),
            )->class('gap-1 items-center px-2 py-0.5 rounded-full cursor-pointer '
                . ($r['me'] ? 'bg-greenmain bg-opacity-20 border border-greenmain' : 'bg-gray-100 border border-gray-200'))
              ->selfPost('addReaction', ['id' => $discussion->id, 'emoji' => $r['emoji']])->refresh()
            )->all(),
        )->class('gap-1 mt-1.5 flex-wrap');
    }

    protected function threadPreview($discussion, bool $isCurrentUser)
    {
        $count = $discussion->discussions->count();

        if (!$count) {
            return null;
        }

        $avatars = $discussion->discussions->take(3)->map(fn ($d) => $d->addedBy)->filter()->unique('id');

        return _Flex(
            _Flex(
                ...$avatars->map(fn ($u) => _Img($u->avatar_path ?: 'https://ui-avatars.com/api/?name=' . urlencode($u->name) . '&background=0a6e4e&color=fff')
                    ->class('w-5 h-5 rounded-full border-2 border-white -ml-1 first:ml-0'))->all(),
            )->class('items-center'),
            _Html($count . ' ' . __('discussions.replies'))->class('text-xs font-semibold '
                . ($isCurrentUser ? 'text-white opacity-90' : 'text-greenmain')),
        )->class('gap-2 items-center mt-1.5 pt-1.5 border-t '
            . ($isCurrentUser ? 'border-white border-opacity-20' : 'border-gray-100'));
    }

    // ── BOTTOM: composer ──

    public function bottom()
    {
        return _Panel(
            new DiscussionForm(null, [
                'channel_id' => $this->channelId,
                'discussion_id' => $this->discussionId,
            ]),
        )->id('channel-discussion-form')->class('max-w-4xl mx-auto w-full pl-9');
    }

    // ── ACTIONS ──

    public function addReaction($id)
    {
        Discussion::findOrFail($id)->toggleReaction(auth()->id(), request('emoji', '👍'));
    }

    public function pinDiscussion($id)
    {
        Discussion::findOrFail($id)->pin();

        return __('discussions.pinned-success');
    }

    public function unpinDiscussion($id)
    {
        Discussion::findOrFail($id)->unpin();

        return __('discussions.unpinned-success');
    }

    public function archiveDiscussion($id)
    {
        $this->updateDiscussionBox($id, 1);

        return 'discussions.discussion-archived';
    }

    public function unarchiveDiscussion($id)
    {
        $this->updateDiscussionBox($id, 0);

        return 'discussions.discussion-removed-archive';
    }

    public function trashDiscussion($id)
    {
        $this->updateDiscussionBox($id, 2);

        return 'discussions.discussion-trashed';
    }

    public function untrashDiscussion($id)
    {
        $this->updateDiscussionBox($id, 0);

        return 'discussions.discussion-removed-trash';
    }

    protected function updateDiscussionBox($id, $box)
    {
        if ($box) {
            Discussion::findOrFail($id)->updateBox($box);
        } else {
            Discussion::findOrFail($id)->box()->delete();
        }
    }

    public function noItemsFound()
    {
        return '<div class="text-gray-600 mb-4 p-4 text-center">' . __('discussions.no-discussions') . '</div>';
    }
}
