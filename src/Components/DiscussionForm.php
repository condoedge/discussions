<?php

namespace Kompo\Discussions\Components;

use App\Models\Activity;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use App\View\Components\CKEditorExtended;
use Kompo\Form;

class DiscussionForm extends Form
{
	public $model = Discussion::class;

	protected $channelId;
	protected $channel;
	protected $discussionId;

	public function created()
	{
		$this->discussionId = $this->parameter('discussion_id');
		$this->discussion = Discussion::find($this->discussionId);

		$this->channelId = $this->store('channel_id') ?: $this->discussion->channel_id;
		$this->channel = Channel::find($this->channelId);
	}

	public function beforeSave()
	{
		$this->model->channel_id = $this->channelId;
		$this->model->setUserId();
		$this->model->discussion_id = $this->discussionId;
	}

	public function afterSave()
	{
		if ($this->model->discussion_id) {
			Discussion::findOrFail($this->model->discussion_id)->boxes()->delete();
		}

		if (!$this->modelExists) {
			Activity::createFor(
				$this->model,
				__('activity.new-discussion-in').' <b>'.$this->model->channel->name.'</b>'.(
					$this->model->subject ? (' '.$this->model->subject) : ''
				),
			);
		}

		$this->processMentions();
	}

	public function completed()
	{
		Discussion::pusherBroadcast();
	}

	public function render()
	{
		return _Rows(
			$this->statusLink(),
	        _CKEditor()->name('html')
	        	->class('ckNoToolbar mb-0')
	        	->focusOnLoad(),
	        _FlexEnd(
				_MultiFile()->name('files')
					->class('asUploadButton mb-0')
					->extraAttributes([
						'team_id' => currentTeam()->id,
					]),
				_SubmitButton('Send')
					->outlined()
					->refresh(
						$this->discussionId ?
							('discussion-card-'.$this->discussionId) :
							'channel-discussion-panel'
					)
			)->class('absolute bottom-4 right-6')
		)->class('relative p-2')
		->id('discussion-ckeditor'.$this->discussionId);
	}

	protected function statusLink()
	{
		$textClass = 'block text-right text-sm text-gray-500';

		return $this->discussionId ?

			_Html(__('discussions.replying-to').' -> '.$this->discussion->subject)
				->class($textClass)->class('-mt-6 mb-1') :

			_Rows(
				_Link('')->icon('icon-plus')
					->toggleId('subject-input')
					->class($textClass)->class('py-4'),
				_Div(
					_Input()->placeholder('discussions.subject-optional')->name('subject')
						->class('pr-4 text-lg font-semibold')
						->dontSubmitOnEnter(),
					_Link()->icon('icon-times')
						->toggleId('subject-input')
						->class('absolute top-5 right-6 text-level1 text-lg')
				)->id('subject-input')
				->class('absolute pt-2 z-10 top-0 w-full')
			);
	}

	public function rules()
	{
		return [
			'html' => 'required_without:files'
		];
	}



	protected function processMentions()
	{
		//new mentions
		CKEditorExtended::parseText(request('html'), 'Discussion (ID'.$this->model->id.')')
			->each(function($mention){

				if(count($mention) == 3){

					if($mention[2] == 'User')
						$this->model->notify($mention[1]);

				}elseif(count($mention) == 2){

					//nothing for now

				}
			});

		//recreate notifications if mentionned in old ones
		$this->model->getAllMentionnedUsers()->reject(
            fn($userId) => $userId === auth()->user()->id
        )->each(function($userId){

        	if(!$this->model->notifications()->unread()->where('user_id', $userId)->count())
				$this->model->notify($userId);

        });
	}
}
