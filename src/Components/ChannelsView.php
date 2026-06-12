<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\DiscussionBox;
use Condoedge\Utils\Kompo\Common\Form;

class ChannelsView extends Form
{
	use DiscussionBoxTrait;

	protected $channel;
	protected $channelId;
	protected $discussionId;

	public $containerClass = '';

	public function created()
	{
		$this->channel = $this->parameter('channel_id') ?
							Channel::with('users', 'addedBy')->find($this->parameter('channel_id')) :
							Channel::queryForUser()->with('users', 'addedBy')->first();

		if ($this->channel && !auth()->user()->can('view', $this->channel)) {
			abort(403);
		}

		$this->channelId = optional($this->channel)->id;

		$this->discussionId = $this->parameter('discussion_id');

		$this->initializeBox();
	}

	public function render()
	{
		return _Div(
			// Column 1: channels list
			_Rows(
				_FlexBetween(
					_Html('discussions.title')->class('font-bold'),
					_FlexEnd(
						_Link()->icon(_Sax('archive-1'))
							->balloon('discussions.show-archived', 'down')
	                		->href($this->box == DiscussionBox::BOX_ARCHIVE ? 'discussions' : 'discussions.archive')
	                		->class('p-2')
	                		->class($this->box == DiscussionBox::BOX_ARCHIVE ? 'bg-level3 text-white rounded-full' : 'text-gray-600'),
						_Link()->icon(_Sax('trash'))
							->balloon('discussions.show-deleted', 'down')
	                		->href($this->box == DiscussionBox::BOX_TRASH ? 'discussions' : 'discussions.trash')
	                		->class('p-2')
	                		->class($this->box == DiscussionBox::BOX_TRASH ? 'bg-level3 text-white rounded-full' : 'text-gray-600'),
						_Link()->icon(_Sax('add'))->class('text-gray-600')
	                		->get('channel-settings')->inModal()
					)
            	)->class('px-2 py-4 text-xl border-b border-gray-200 shrink-0'),

				new ChannelsListTeam([
					'active_channel_id' => $this->channelId,
					'active_discussion_id' => $this->discussionId,
					'box' => $this->box,
				])

			)->class('discussions-sidebar bg-white border-r border-gray-200 h-full flex flex-col')
			->style('width: 280px; flex-shrink: 0;'),

			// Column 2: messages panel + composer (the composer sits outside the
			// messages Query so panel refreshes never interrupt typing)
			_Panel(
				$this->channelId ?
					ChannelDiscussionsPanel::withComposer($this->channelId, $this->box) :
					_Html(__('discussions.no-channels'))->class('text-gray-600 text-center p-10')
			)->id('channel-view-panel')
			->class('bg-gray-100 h-full flex-1 flex flex-col min-w-0'),

			// Column 3: details side panel
			$this->channelId ?
				_Div(
					_Rows(
						_FlexBetween(
							_Html($this->channel->name)->class('font-bold text-lg'),
							_Link()->icon(_Sax('setting-2'))->class('text-gray-600')
								->get('channel-settings', ['id' => $this->channelId])->inModal()
						)->class('p-4 border-b border-gray-200'),

						_Html('discussions.participants')->class('px-4 pt-4 pb-2 text-xs font-semibold text-gray-500 uppercase'),
						_Rows(
							$this->channel->participants()
								->map(function($user) {
									$isCurrentUser = $user->id === auth()->id();
									$badge = $isCurrentUser ? '<span class="ml-1 text-xs text-gray-500">'.__('discussions.you').'</span>' : '';
									return _Flex(
										_Img(discussionsAvatarUrl($user))->class('w-8 h-8 rounded-full mr-2 object-cover'),
										_Html($user->name . $badge)->class('text-sm text-gray-700')
									)->class('px-4 py-2 hover:bg-gray-50 transition-colors');
								})->toArray()
						),

						_Html('discussions.attachments')->class('px-4 pt-6 pb-2 text-xs font-semibold text-gray-500 uppercase'),
						_Div(
							// TODO: list the files shared in this channel
							_Html('discussions.no-attachments')->class('text-sm text-gray-600 px-4 py-2')
						)
					)
				)->class('bg-white border-l border-gray-200 h-full overflow-y-auto')
				->style('width: 320px; flex-shrink: 0;')
			: null

		)->class('discussions-page flex');
	}
}
