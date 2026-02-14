<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
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

		$this->channelId = optional($this->channel)->id;

		$this->discussionId = $this->parameter('discussion_id');

		$this->initializeBox();
	}

	public function render()
	{
		return _Div(
			// Colonne 1: Liste des discussions (Rouge)
			_Rows(
				_FlexBetween(
					_Html('Discussions')->class('font-bold'),
					_FlexEnd(
						_Link()->icon(_Sax('archive-1'))
	                		->href($this->box == 1 ? 'discussions' : 'discussions.archive')
	                		->class('p-2')
	                		->class($this->box == 1 ? 'bg-level3 text-white rounded-full' : 'text-gray-600'),
						_Link()->icon(_Sax('trash'))
	                		->href($this->box == 2 ? 'discussions' : 'discussions.trash')
	                		->class('p-2')
	                		->class($this->box == 2 ? 'bg-level3 text-white rounded-full' : 'text-gray-600'),
						_Link()->icon(_Sax('add'))->class('text-gray-600')
	                		->get('channel-settings')->inModal()
					)
            	)->class('px-2 py-4 text-xl border-b border-gray-200'),

				new ChannelsListTeam([
					'active_channel_id' => $this->channelId,
					'active_discussion_id' => $this->discussionId,
					'box' => $this->box,
				])

			)->class('bg-white border-r border-gray-200 h-screen overflow-y-auto')
			->style('width: 280px; flex-shrink: 0;'),

			// Colonne 2: Section messages (Bleue)
			_Panel(
				$this->channelId ?
					(new ChannelDiscussionsPanel([
						'channel_id' => $this->channelId,
						'box' => $this->box,
					])) :
					_Html('discussions.no-channels')->class('text-gray-600 text-center p-10')
			)->id('channel-view-panel')
			->class('bg-gray-100 h-screen flex-1 flex flex-col'),

			// Colonne 3: Panneau latéral (Vert)
			$this->channelId ?
				_Div(
					_Rows(
						_FlexBetween(
							_Html($this->channel->name)->class('font-bold text-lg'),
							_Link()->icon(_Sax('setting-2'))->class('text-gray-600')
								->get('channel-settings', ['id' => $this->channelId])->inModal()
						)->class('p-4 border-b border-gray-200'),

						_Html('Participants')->class('px-4 pt-4 pb-2 text-xs font-semibold text-gray-500 uppercase'),
						_Rows(
							collect([$this->channel->addedBy])
								->concat($this->channel->users)
								->unique('id')
								->map(function($user) {
									$avatarUrl = $user->avatar_path ?: 'https://ui-avatars.com/api/?name=' . urlencode($user->name);
									$isCurrentUser = $user->id === auth()->id();
									$badge = $isCurrentUser ? '<span class="ml-1 text-xs text-gray-500">(vous)</span>' : '';
									return _Flex(
										_Img($avatarUrl)->class('w-8 h-8 rounded-full mr-2 object-cover'),
										_Html($user->name . $badge)->class('text-sm text-gray-700')
									)->class('px-4 py-2 hover:bg-gray-50 transition-colors');
								})->toArray()
						),

						_Html('Pièces jointes')->class('px-4 pt-6 pb-2 text-xs font-semibold text-gray-500 uppercase'),
						_Div(
							// TODO: Liste des fichiers partagés
							_Html('Aucune pièce jointe')->class('text-sm text-gray-600 px-4 py-2')
						)
					)
				)->class('bg-white border-l border-gray-200 h-screen overflow-y-auto')
				->style('width: 320px; flex-shrink: 0;')
			: null

		)->class('flex')
		->style('margin-top:-2vh');
	}
}
