<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Form;

class ChannelSettingsForm extends Form
{
	public $model = Channel::class;

	public $style = 'height:calc(100vh - 200px)';

	public function beforeSave()
	{
		$this->model->setTeamId();
	}

	public function response()
	{
		return redirect()->route('discussions', [
			'channel_id' => $this->model->id
		]);
	}

	public function render()
	{
		return _Div(

	        _FlexBetween(
	        	_Columns(
	        		_Rows(
		        		_MiniTitle('discussions.title')->class('mb-2'),
						_Input()->placeholder('discussions.channel-title')->name('name')
					),
	        		_Rows(
		        		_MiniTitle('discussions.owner')->class('mb-3'),
						_Html(auth()->user()->name),
					),
	        	)->class('flex-auto'),
				$this->model->id ?

					_Link('discussions.back')->icon('arrow-left')->get('channel', [
						'id' => $this->model->id
					])->inPanel('channel-view-panel') :

					_Link('discussions.back')->icon('arrow-left')->href('discussions')
			)->alignStart(),

	        _MiniTitle('discussions.members')->class('mb-2'),

			_MultiSelect()->placeholder(__('discussions.add-members'))->name('users')
	        	->options(currentTeam()->users()->where('users.id', '!=', auth()->user()->id)->get()
	        		->mapWithKeys(fn($user) => [
	        			$user->id => _Html($user->name)
	        		])
	        	),

			_SubmitButton('discussions.save'),

		)->class('p-4');
	}

	public function rules()
	{
		return [
			'name' => 'required',
		];
	}
}
