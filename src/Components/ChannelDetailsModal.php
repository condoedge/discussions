<?php

namespace Kompo\Discussions\Components;

use Condoedge\Utils\Kompo\Common\Modal;
use Kompo\Auth\Facades\TeamModel;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\DiscussionBox;

/**
 * Read-only "details" screen for a channel, opened from the conversation/list gear.
 * Shows identity, team context, participants and attachments, with an edit affordance
 * (pencil) that opens ChannelSettingsForm and Archive/Delete actions at the bottom.
 *
 * NOTE: lives in the package for now (host app overrides will be ported back later).
 */
class ChannelDetailsModal extends Modal
{
    public $model = Channel::class;

    public $_Title = 'discussions.details';

    public function created()
    {
        if (!$this->model->id || !auth()->user()->can('view', $this->model)) {
            abort(403);
        }
    }

    /** Pencil in the header opens the edit form — the "modifier les informations" affordance. */
    public function headerButtons()
    {
        if (!auth()->user()->can('update', $this->model)) {
            return null;
        }

        return _Link()->icon(_Sax('edit-2', 20))->class('text-greenmain p-1')
            ->get('channel-settings', ['id' => $this->model->id])->inModal();
    }

    public function body()
    {
        $channel = $this->model;
        $participants = $channel->participants();
        $initial = mb_strtoupper(mb_substr($channel->display ?: '?', 0, 1));

        return _Rows(
            // Identity
            _Rows(
                _Flex(_Html($initial)->class('text-white font-bold text-3xl'))
                    ->class('w-20 h-20 rounded-full bg-greenmain flex items-center justify-center'),
                _Html($channel->display)->class('text-lg font-bold text-greendark mt-3'),
                _Html(($channel->is_private ? __('discussions.private') : __('discussions.public'))
                    . ' · ' . $participants->count() . ' ' . __('discussions.participants-lc'))
                    ->class('text-sm text-gray-500'),
            )->class('items-center gap-1 pb-4'),

            $this->contextCard($channel),

            _Html('discussions.participants')->class('discussions-detail-label'),
            _Rows(
                ...array_filter(array_merge(
                    $participants->map(fn ($user) => $this->participantRow($user))->all(),
                    [$this->addParticipantRow()],
                )),
            )->class('discussions-detail-list'),

            _Html('discussions.attachments')->class('discussions-detail-label'),
            _Rows(
                _Flex(_Sax('document-1', 26))->class('justify-center text-gray-300'),
                _Html('discussions.no-attachments')->class('text-sm text-gray-400 text-center'),
            )->class('discussions-detail-card items-center gap-2 py-6'),

            // Actions
            _Flex(
                _Button('discussions.archive')->outlined()->class('flex-1')
                    ->selfPost('archiveChannel')->redirect('discussions'),
                _DeleteLink('discussions.delete')->byKey($channel)->button()
                    ->class('flex-1 discussions-delete-btn')->redirect('discussions'),
            )->class('gap-3 mt-5'),
        )->class('gap-1 pb-2');
    }

    /** Team-context card: parent hierarchy as a breadcrumb + the channel's team as a pill. */
    protected function contextCard(Channel $channel)
    {
        $team = $channel->team_id ? TeamModel::find($channel->team_id) : null;

        if (!$team) {
            return null;
        }

        $breadcrumb = method_exists($team, 'getParentTeamsHierarchyLabel')
            ? $team->getParentTeamsHierarchyLabel(true)
            : null;

        return _Rows(
            _Flex(
                _Sax('location', 15)->class('text-greenmain'),
                _Html('discussions.context')->class('text-xs font-semibold text-gray-400 uppercase'),
            )->class('items-center gap-2'),
            !$breadcrumb ? null : _Html(str_replace(' / ', ' › ', $breadcrumb))->class('text-sm text-gray-500'),
            _Html($team->team_name)->class('discussions-context-pill'),
        )->class('discussions-detail-card gap-2');
    }

    protected function participantRow($user)
    {
        $isSelf = (int) $user->id === (int) auth()->id();
        $suffix = $isSelf ? ' <span class="text-xs text-gray-400">' . __('discussions.you') . '</span>' : '';

        return _Flex(
            _Img(discussionsAvatarUrl($user))->class('w-9 h-9 rounded-full object-cover flex-shrink-0'),
            _Rows(
                _Html($user->name . $suffix)->class('text-sm font-semibold text-greendark'),
                _Html($this->roleLabel($user))->class('text-xs text-gray-400'),
            )->class('flex-1 min-w-0'),
        )->class('items-center gap-3 px-3 py-2.5');
    }

    protected function addParticipantRow()
    {
        if (!auth()->user()->can('update', $this->model)) {
            return null;
        }

        return _Flex(
            _Flex(_Sax('add', 20))->class('w-9 h-9 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center flex-shrink-0'),
            _Html('discussions.add-participant')->class('text-sm font-semibold text-greendark'),
        )->class('items-center gap-3 px-3 py-2.5 cursor-pointer')
            ->get('channel-settings', ['id' => $this->model->id])->inModal();
    }

    /** Owner gets the owner label; everyone else is a member (super-admin refinement TBD). */
    protected function roleLabel($user): string
    {
        return (int) $user->id === (int) $this->model->added_by
            ? __('discussions.owner')
            : __('discussions.member');
    }

    /** Move every discussion of this channel to the archive box. */
    public function archiveChannel()
    {
        $this->model->discussions->each(fn ($discussion) => $discussion->updateBox(DiscussionBox::BOX_ARCHIVE));
    }
}
