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
							Channel::find($this->parameter('channel_id')) :
							Channel::queryForUser()->first();

		$this->channelId = optional($this->channel)->id;

		$this->discussionId = $this->parameter('discussion_id');

		$this->initializeBox();
	}

	public function render()
	{
		return _Div(
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
	                		->get('channel-settings')->inPanel('channel-view-panel')
					)
            	)->class('px-2 py-4 text-xl border-b border-gray-200'),

				new ChannelsListTeam([
					'active_channel_id' => $this->channelId,
					'active_discussion_id' => $this->discussionId,
					'box' => $this->box,
				])
				
			)->class('bg-white border-r border-gray-200')
			->style('grid-column:1;grid-row:1'),
			_Panel(
				$this->channelId ?
					new ChannelDiscussionsPanel([
						'channel_id' => $this->channelId,
						'box' => $this->box,
					]) :
					_Html('discussions.no-channels')->class('text-gray-600 text-center p-10')
			)->id('channel-view-panel')
			->class('overflow-x-hidden bg-gray-100')
			->closable()
			->class('md:w-2/3vw xl:w-3/5vw 2xl:w-2/3vw')
			->style('grid-column:2;grid-row:1')
		)->class('grid')
		->style('grid-template-columns: minmax(0, 1fr) auto;margin-top:-2vh');
	}
}
