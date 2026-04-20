<?php

namespace Kompo\Discussions\Components;

use App\Models\User;
use App\View\Components\CKEditorExtended;
use Condoedge\Utils\Kompo\Common\Form;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Kompo\Discussions\Models\DiscussionMention;

class DiscussionForm extends Form
{
    public $model = Discussion::class;

    protected $channelId;
    protected $channel;
    protected $discussionId;
    protected $discussion;

    public function created()
    {
        $this->discussionId = $this->store('discussion_id') ?: $this->parameter('discussion_id');
        $this->discussion = $this->discussionId ? Discussion::find($this->discussionId) : null;

        $this->channelId = $this->store('channel_id') ?: $this->discussion?->channel_id;
        $this->channel = Channel::find($this->channelId);
    }

    public function beforeSave()
    {
        $this->model->channel_id = $this->channelId;
        $this->model->discussion_id = $this->discussionId;
    }

    public function afterSave()
    {
        if ($this->model->discussion_id) {
            Discussion::findOrFail($this->model->discussion_id)->boxes()->delete();
        }

        $this->persistMentions();
    }

    public function completed()
    {
        Discussion::pusherBroadcast();
        $this->model->pushSentEvent();
    }

    // ── LAYOUT ──

    public function render()
    {
        return _Rows(
            $this->composerHeader(),
            _CKEditor()->name('html')
                ->class('ckNoToolbar mb-0')
                ->focusOnLoad(),
            $this->composerToolbar(),
        )->class('relative p-3 rounded-xl bg-white border border-gray-200 shadow-sm')
         ->id('discussion-ckeditor' . $this->discussionId);
    }

    protected function composerHeader()
    {
        if ($this->discussionId) {
            return _Flex(
                _Sax('direct-right', 14)->class('text-gray-400'),
                _Html(__('discussions.replying-to') . ' ' . ($this->discussion?->subject ?? ''))
                    ->class('text-xs text-gray-500 italic truncate'),
            )->class('gap-1 items-center mb-1');
        }

        return _Rows(
            _Div(
                _Rows(
                    _Input()->placeholder('discussions.subject-optional')->name('subject')
                        ->class('!mb-1 text-sm font-semibold')
                        ->dontSubmitOnEnter(),
                )->class('w-full'),
                _Link()->icon(_Sax('close-circle', 14))
                    ->toggleId('subject-input')
                    ->class('absolute top-1.5 right-1 text-gray-400 hover:text-gray-600'),
            )->id('subject-input')->class('relative hidden'),
        );
    }

    protected function composerToolbar()
    {
        return _FlexBetween(
            _Flex(
                _Link()->icon(_Sax('text', 16))->class('text-gray-500 p-1.5 rounded hover:bg-gray-100')
                    ->toggleId('subject-input'),
                _MultiFile()->name('files')
                    ->class('asUploadButton mb-0')
                    ->extraAttributes(['team_id' => currentTeam()->id]),
                _Link()->icon(_Sax('emoji-happy', 16))->class('text-gray-500 p-1.5 rounded hover:bg-gray-100'),
            )->class('gap-1 items-center'),
            _SubmitButton('discussions.send')
                ->class('bg-greenmain !text-white px-4 py-1.5 rounded-lg text-sm font-semibold')
                ->refresh(
                    $this->discussionId
                        ? 'discussion-card-' . $this->discussionId
                        : 'channel-discussion-panel',
                ),
        )->class('mt-2');
    }

    // ── RULES ──

    public function rules()
    {
        return [
            'html' => 'required_without:files',
        ];
    }

    // ── MENTIONS ──

    protected function persistMentions(): void
    {
        $html = request('html', '');

        if (!$html) {
            return;
        }

        preg_match_all('/@([a-zA-ZÀ-ÿ0-9_\-\.]+)/u', $html, $matches);

        if (empty($matches[1])) {
            return;
        }

        $slugs = array_unique($matches[1]);

        $channelUserIds = $this->channel
            ? $this->channel->users->pluck('id')->concat([$this->channel->user_id])->unique()
            : collect();

        $userIds = User::whereIn('id', $channelUserIds)
            ->get()
            ->filter(fn ($user) => collect($slugs)->contains(fn ($slug) => stripos($user->name, $slug) !== false))
            ->pluck('id');

        foreach ($userIds as $userId) {
            if ($userId === auth()->id()) {
                continue;
            }

            DiscussionMention::firstOrCreate([
                'discussion_id' => $this->model->id,
                'user_id' => $userId,
            ]);

            // Reuse existing notification plumbing on Discussion
            if (method_exists($this->model, 'notify')) {
                $this->model->notify($userId);
            }
        }
    }
}
